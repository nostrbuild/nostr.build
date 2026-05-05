<?php

declare(strict_types=1);

/**
 * IP Blocklist with User Whitelist Override
 * ==========================================
 *
 * Single-query gatekeeper plus full CRUD for blocklist and whitelist entries.
 *
 * SCHEMA
 * ------
 *
CREATE TABLE ip_blocklist (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  start_ip   VARBINARY(16) NOT NULL,
  end_ip     VARBINARY(16) NOT NULL,
  cidr       VARCHAR(43)   NOT NULL,
  reason     VARCHAR(255),
  source     VARCHAR(64)   NOT NULL DEFAULT 'manual',
  banned_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP     NULL,
  UNIQUE KEY uq_range (start_ip, end_ip),
  INDEX idx_source (source)
) ENGINE=InnoDB;
 *
CREATE TABLE ip_whitelist (
  user_id    VARCHAR(255) NOT NULL PRIMARY KEY,
  reason     VARCHAR(255),
  added_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NULL
) ENGINE=InnoDB;
 *
 * NOTES
 * -----
 * - VARBINARY(16) stores both IPv4 and IPv6 via INET6_ATON.
 * - Ranges are non-overlapping by construction (dedup on insert).
 *   "ORDER BY start_ip DESC LIMIT 1" is optimal: the row with the largest
 *   start_ip <= query_ip is the only possible match.
 * - Anonymous lookups pass user_id = '' which disables the whitelist branch
 *   via the "? <> ''" guard. The whitelist NOT EXISTS subquery is
 *   uncorrelated and evaluated once per query.
 */

final class IpAccessControl
{
  /** Minimum prefix length to prevent accidentally banning huge swaths. */
  public const MIN_IPV4_PREFIX = 8;
  public const MIN_IPV6_PREFIX = 16;

  /** Hard ceiling on listBlocks/listWhitelist limits. */
  private const MAX_LIST_LIMIT = 1000;

  /** Batch size for purge operations to avoid long lock holds. */
  private const PURGE_BATCH_SIZE = 1000;

  /** Updatable columns for blocklist (whitelist of safe field names). */
  private const BLOCK_UPDATABLE = ['reason' => 's', 'source' => 's', 'expires_at' => 's'];
  private const WHITELIST_UPDATABLE = ['reason' => 's', 'expires_at' => 's'];

  private const LOOKUP_SQL = <<<'SQL'
        SELECT 1
        FROM ip_blocklist
        WHERE start_ip <= INET6_ATON(?)
          AND end_ip   >= INET6_ATON(?)
          AND (expires_at IS NULL OR expires_at > NOW())
          AND NOT EXISTS (
            SELECT 1 FROM ip_whitelist
            WHERE ? <> ''
              AND user_id = ?
              AND (expires_at IS NULL OR expires_at > NOW())
          )
        ORDER BY start_ip DESC
        LIMIT 1
        SQL;

  private ?mysqli_stmt $lookupStmt = null;
  private int $lookupStmtThreadId = 0;

  public function __construct(private readonly mysqli $db) {}

  public function __destruct()
  {
    // Connection may already be torn down during shutdown; suppress.
    try {
      $this->lookupStmt?->close();
    } catch (\Throwable) {
    }
  }

  // =========================================================================
  // HOT PATH
  // =========================================================================

  /**
   * Extract the real end-user client IP for the current request.
   *
   * Lookup order:
   *   1) $_SERVER['CLIENT_REQUEST_INFO'] JSON → realIp
   *      Set by nginx for direct nostr.build origin requests, and overwritten
   *      by api/v2/routes_blossom.php with the worker-supplied client info for
   *      blossom-band proxied requests.
   *   2) HTTP_CF_CONNECTING_IP / HTTP_X_REAL_IP / REMOTE_ADDR fallbacks for any
   *      code path that runs outside the upload route handlers.
   *
   * Each candidate is normalised — bracketed IPv6 (`[::1]:8080`), IPv6 zone
   * identifiers (`fe80::1%eth0`), and IPv4-with-port (`1.2.3.4:5678`) all get
   * reduced to a bare IP before validation. Returns null when no usable IP
   * can be determined (e.g. CLI invocation).
   */
  public static function extractClientIp(): ?string
  {
    $info = $_SERVER['CLIENT_REQUEST_INFO'] ?? null;
    if (is_string($info) && $info !== '') {
      $decoded = json_decode($info, true);
      if (is_array($decoded) && !empty($decoded['realIp'])) {
        $ip = self::normalizeIpString((string) $decoded['realIp']);
        if ($ip !== null) return $ip;
      }
    }
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $k) {
      $v = $_SERVER[$k] ?? null;
      if (!is_string($v) || $v === '') continue;
      $ip = self::normalizeIpString($v);
      if ($ip !== null) return $ip;
    }
    return null;
  }

  /**
   * Strip the common transport-layer wrappers that can show up in proxy
   * headers and reduce to a bare IP. Returns null when the input does not
   * resolve to a valid IPv4/IPv6 address.
   *
   * Handles:
   *   - bracketed IPv6 with port:  "[2001:db8::1]:8080" -> "2001:db8::1"
   *   - bracketed IPv6 only:       "[2001:db8::1]"      -> "2001:db8::1"
   *   - IPv6 zone id:              "fe80::1%eth0"       -> "fe80::1"
   *   - IPv4 with port:            "1.2.3.4:5678"       -> "1.2.3.4"
   * Does NOT handle XFF lists ("a, b, c"); this is intentional — every
   * source we read is documented to carry a single client IP.
   */
  private static function normalizeIpString(string $raw): ?string
  {
    $raw = trim($raw);
    if ($raw === '') return null;

    // Bracketed IPv6, with or without port.
    if ($raw[0] === '[') {
      $closeBracket = strpos($raw, ']');
      if ($closeBracket === false) return null;
      $raw = substr($raw, 1, $closeBracket - 1);
    } else {
      // Unbracketed IPv4 with port — distinguishable from IPv6 by having
      // exactly one colon (IPv6 always has at least two).
      if (substr_count($raw, ':') === 1) {
        $raw = explode(':', $raw, 2)[0];
      }
    }

    // IPv6 zone identifier (link-local context, never globally routable —
    // strip so the bare address can be validated and stored consistently).
    $pct = strpos($raw, '%');
    if ($pct !== false) {
      $raw = substr($raw, 0, $pct);
    }

    return filter_var($raw, FILTER_VALIDATE_IP) !== false ? $raw : null;
  }

  public function isBlocked(string $clientIp, string $userId = ''): bool
  {
    if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
      return true; // fail closed on malformed IP
    }

    $stmt = $this->getLookupStmt();
    $stmt->bind_param('ssss', $clientIp, $clientIp, $userId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $blocked = $stmt->num_rows > 0;
    $stmt->free_result();

    return $blocked;
  }

  /**
   * @return array{id:int,cidr:string,reason:?string,source:string,banned_at:string,expires_at:?string}|null
   */
  public function findBlock(string $clientIp, string $userId = ''): ?array
  {
    if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
      return null;
    }

    return $this->fetchOne(
      'SELECT id, cidr, reason, source,
                    DATE_FORMAT(banned_at,  "%Y-%m-%d %H:%i:%s") AS banned_at,
                    DATE_FORMAT(expires_at, "%Y-%m-%d %H:%i:%s") AS expires_at
             FROM ip_blocklist
             WHERE start_ip <= INET6_ATON(?)
               AND end_ip   >= INET6_ATON(?)
               AND (expires_at IS NULL OR expires_at > NOW())
               AND NOT EXISTS (
                 SELECT 1 FROM ip_whitelist
                 WHERE ? <> ?
                   AND user_id = ?
                   AND (expires_at IS NULL OR expires_at > NOW())
               )
             ORDER BY start_ip DESC LIMIT 1',
      'sssss',
      [$clientIp, $clientIp, $userId, '', $userId],
    );
  }

  private function getLookupStmt(): mysqli_stmt
  {
    // Detect connection drop/reconnect: thread_id changes on reconnect,
    // and any cached prepared statement from the prior connection is dead.
    $currentThreadId = $this->db->thread_id;
    if ($this->lookupStmt !== null && $this->lookupStmtThreadId !== $currentThreadId) {
      try {
        $this->lookupStmt->close();
      } catch (\Throwable) {
      }
      $this->lookupStmt = null;
    }

    if ($this->lookupStmt === null) {
      $this->lookupStmt = $this->db->prepare(self::LOOKUP_SQL);
      $this->lookupStmtThreadId = $currentThreadId;
    }

    return $this->lookupStmt;
  }

    // =========================================================================
    // BLOCKLIST — WRITE
    // =========================================================================

  /**
   * Insert a CIDR. Returns inserted row id, or null if an exact-range duplicate
   * already exists. Overlapping (but non-identical) ranges are allowed.
   *
   * @throws InvalidArgumentException for invalid or too-broad CIDRs
   * @throws mysqli_sql_exception on any non-duplicate database error
   */
  public function addBlock(
    string $cidr,
    ?string $reason = null,
    string $source = 'manual',
    ?string $expiresAt = null,
  ): ?int {
    $normalized = self::normalizeCidr($cidr);
    [$start, $end] = self::cidrToRange($normalized);

    $stmt = $this->db->prepare(
      'INSERT INTO ip_blocklist (start_ip, end_ip, cidr, reason, source, expires_at)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    try {
      $stmt->bind_param('ssssss', $start, $end, $normalized, $reason, $source, $expiresAt);
      try {
        $stmt->execute();
        return (int) $this->db->insert_id;
      } catch (\mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) { // ER_DUP_ENTRY
          return null;
        }
        throw $e;
      }
    } finally {
      $stmt->close();
    }
  }

  /**
   * Replace all entries from a given source atomically. Validates every
   * CIDR before touching the table so a bad input doesn't leave a partial
   * state.
   *
   * @param list<string> $cidrs
   */
  public function replaceBySource(string $source, array $cidrs, ?string $reason = null): int
  {
    if ($source === '') {
      throw new InvalidArgumentException('source must not be empty');
    }

    // Validate + normalize everything first.
    $prepared = [];
    foreach ($cidrs as $cidr) {
      $normalized = self::normalizeCidr($cidr);
      [$start, $end] = self::cidrToRange($normalized);
      $prepared[] = [$start, $end, $normalized];
    }

    $this->db->begin_transaction();
    try {
      $this->exec('DELETE FROM ip_blocklist WHERE source = ?', 's', [$source]);

      if ($prepared === []) {
        $this->db->commit();
        return 0;
      }

      $stmt = $this->db->prepare(
        'INSERT INTO ip_blocklist (start_ip, end_ip, cidr, reason, source) VALUES (?, ?, ?, ?, ?)'
      );
      try {
        $start = $end = $cidrStr = '';
        $stmt->bind_param('sssss', $start, $end, $cidrStr, $reason, $source);
        $count = 0;
        foreach ($prepared as [$s, $e, $c]) {
          $start = $s;
          $end = $e;
          $cidrStr = $c;
          $stmt->execute();
          $count++;
        }
      } finally {
        $stmt->close();
      }

      $this->db->commit();
      return $count;
    } catch (\Throwable $t) {
      $this->db->rollback();
      throw $t;
    }
  }

  public function removeBlock(int $id): bool
  {
    return $this->exec('DELETE FROM ip_blocklist WHERE id = ?', 'i', [$id]) > 0;
  }

  public function removeBlocksBySource(string $source): int
  {
    if ($source === '') {
      throw new InvalidArgumentException('source must not be empty');
    }
    return $this->exec('DELETE FROM ip_blocklist WHERE source = ?', 's', [$source]);
  }

  public function updateBlock(int $id, array $fields): bool
  {
    return $this->updateRow('ip_blocklist', 'id', $id, $fields, self::BLOCK_UPDATABLE);
  }

  /**
   * Delete expired blocks in batches to avoid long lock holds.
   * Returns total rows deleted.
   */
  public function purgeExpiredBlocks(): int
  {
    return $this->batchDelete(
      'DELETE FROM ip_blocklist WHERE expires_at IS NOT NULL AND expires_at <= NOW() LIMIT ?'
    );
  }

    // =========================================================================
    // BLOCKLIST — READ
    // =========================================================================

  /**
   * @param array{source?:string,active_only?:bool,limit?:int,offset?:int} $opts
   * @return list<array<string,mixed>>
   */
  public function listBlocks(array $opts = []): array
  {
    $where = [];
    $types = '';
    $values = [];

    if (isset($opts['source'])) {
      $where[] = 'source = ?';
      $types .= 's';
      $values[] = $opts['source'];
    }
    if (!empty($opts['active_only'])) {
      $where[] = '(expires_at IS NULL OR expires_at > NOW())';
    }

    $sql = 'SELECT id, INET6_NTOA(start_ip) AS start_ip, INET6_NTOA(end_ip) AS end_ip,
                       cidr, reason, source, banned_at, expires_at
                  FROM ip_blocklist'
      . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
      . ' ORDER BY banned_at DESC LIMIT ? OFFSET ?';

    $types .= 'ii';
    $values[] = $this->clampLimit($opts['limit']  ?? 100);
    $values[] = max(0, (int) ($opts['offset'] ?? 0));

    return $this->fetchAll($sql, $types, $values);
  }

  public function countBlocks(?string $source = null): int
  {
    if ($source === null) {
      $row = $this->fetchOne('SELECT COUNT(*) AS c FROM ip_blocklist', '', []);
    } else {
      $row = $this->fetchOne('SELECT COUNT(*) AS c FROM ip_blocklist WHERE source = ?', 's', [$source]);
    }
    return (int) ($row['c'] ?? 0);
  }

  public function findBlockByCidr(string $cidr): ?array
  {
    $normalized = self::normalizeCidr($cidr);
    return $this->fetchOne(
      'SELECT id, cidr, reason, source, banned_at, expires_at
               FROM ip_blocklist WHERE cidr = ? LIMIT 1',
      's',
      [$normalized],
    );
  }

  // =========================================================================
  // WHITELIST
  // =========================================================================

  public function addToWhitelist(string $userId, ?string $reason = null, ?string $expiresAt = null): void
  {
    if ($userId === '') {
      throw new InvalidArgumentException('user_id must not be empty');
    }
    $this->exec(
      'INSERT INTO ip_whitelist (user_id, reason, expires_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), expires_at = VALUES(expires_at)',
      'sss',
      [$userId, $reason, $expiresAt],
    );
  }

  public function removeFromWhitelist(string $userId): bool
  {
    if ($userId === '') return false;
    return $this->exec('DELETE FROM ip_whitelist WHERE user_id = ?', 's', [$userId]) > 0;
  }

  public function isWhitelisted(string $userId): bool
  {
    if ($userId === '') return false;
    $row = $this->fetchOne(
      'SELECT 1 AS x FROM ip_whitelist
              WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())',
      's',
      [$userId],
    );
    return $row !== null;
  }

  public function updateWhitelist(string $userId, array $fields): bool
  {
    if ($userId === '') return false;
    return $this->updateRow('ip_whitelist', 'user_id', $userId, $fields, self::WHITELIST_UPDATABLE);
  }

  public function purgeExpiredWhitelist(): int
  {
    return $this->batchDelete(
      'DELETE FROM ip_whitelist WHERE expires_at IS NOT NULL AND expires_at <= NOW() LIMIT ?'
    );
  }

  /**
   * @param array{active_only?:bool,limit?:int,offset?:int} $opts
   * @return list<array<string,mixed>>
   */
  public function listWhitelist(array $opts = []): array
  {
    $sql = 'SELECT user_id, reason, added_at, expires_at FROM ip_whitelist';
    if (!empty($opts['active_only'])) {
      $sql .= ' WHERE expires_at IS NULL OR expires_at > NOW()';
    }
    $sql .= ' ORDER BY added_at DESC LIMIT ? OFFSET ?';

    return $this->fetchAll(
      $sql,
      'ii',
      [$this->clampLimit($opts['limit'] ?? 100), max(0, (int) ($opts['offset'] ?? 0))],
    );
  }

    // =========================================================================
    // CIDR HELPERS
    // =========================================================================

  /**
   * Normalize a CIDR or bare IP into canonical "ip/prefix" form.
   * Bare IPs become /32 (v4) or /128 (v6).
   */
  public static function normalizeCidr(string $cidr): string
  {
    $cidr = trim($cidr);
    if ($cidr === '') {
      throw new InvalidArgumentException('CIDR must not be empty');
    }

    if (str_contains($cidr, '/')) {
      [$ip, $prefixStr] = explode('/', $cidr, 2);
      if (!ctype_digit($prefixStr)) {
        throw new InvalidArgumentException("Invalid prefix in CIDR: $cidr");
      }
      $prefix = (int) $prefixStr;
    } else {
      $ip = $cidr;
      $prefix = -1; // sentinel: pick by family
    }

    $bin = @inet_pton($ip);
    if ($bin === false) {
      throw new InvalidArgumentException("Invalid IP: $cidr");
    }

    $isV4 = strlen($bin) === 4;
    if ($isV4) {
      if ($prefix === -1) $prefix = 32;
      if ($prefix < 0 || $prefix > 32) {
        throw new InvalidArgumentException("Invalid IPv4 prefix: $cidr");
      }
      if ($prefix < self::MIN_IPV4_PREFIX) {
        throw new InvalidArgumentException(
          "IPv4 prefix /$prefix too broad (minimum /" . self::MIN_IPV4_PREFIX . ')'
        );
      }
    } else {
      if ($prefix === -1) $prefix = 128;
      if ($prefix < 0 || $prefix > 128) {
        throw new InvalidArgumentException("Invalid IPv6 prefix: $cidr");
      }
      if ($prefix < self::MIN_IPV6_PREFIX) {
        throw new InvalidArgumentException(
          "IPv6 prefix /$prefix too broad (minimum /" . self::MIN_IPV6_PREFIX . ')'
        );
      }
    }

    // Canonicalize the IP string (e.g., "2001:0db8::1" -> "2001:db8::1")
    return inet_ntop($bin) . '/' . $prefix;
  }

  /**
   * Convert a normalized CIDR to [start, end] as 16-byte binary strings
   * matching INET6_ATON storage format.
   *
   * @return array{0:string,1:string}
   */
  public static function cidrToRange(string $cidr): array
  {
    if (!str_contains($cidr, '/')) {
      throw new InvalidArgumentException("CIDR must include prefix: $cidr");
    }

    [$ip, $prefixStr] = explode('/', $cidr, 2);
    $prefix = (int) $prefixStr;
    $bin = @inet_pton($ip);
    if ($bin === false) {
      throw new InvalidArgumentException("Invalid IP in CIDR: $cidr");
    }

    // Promote v4 to v4-mapped v6 form so storage matches INET6_ATON.
    if (strlen($bin) === 4) {
      if ($prefix < 0 || $prefix > 32) {
        throw new InvalidArgumentException("Invalid IPv4 prefix: $cidr");
      }
      $bin = "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff" . $bin;
      $prefix += 96;
    } elseif ($prefix < 0 || $prefix > 128) {
      throw new InvalidArgumentException("Invalid IPv6 prefix: $cidr");
    }

    $fullBytes = intdiv($prefix, 8);
    $remBits = $prefix % 8;
    $mask = str_repeat("\xff", $fullBytes);
    if ($remBits !== 0) {
      $mask .= chr((0xff << (8 - $remBits)) & 0xff);
    }
    $mask = str_pad($mask, 16, "\x00", STR_PAD_RIGHT);

    $start = $bin & $mask;
    $end   = $start | ~$mask;

    return [$start, $end];
  }

  // =========================================================================
  // INTERNAL
  // =========================================================================

  private function clampLimit(int|string $limit): int
  {
    $n = (int) $limit;
    if ($n < 1) return 1;
    if ($n > self::MAX_LIST_LIMIT) return self::MAX_LIST_LIMIT;
    return $n;
  }

  /**
   * @param array<string,string> $allowed Map of column name -> mysqli type letter
   */
  private function updateRow(
    string $table,
    string $keyCol,
    int|string $keyVal,
    array $fields,
    array $allowed,
  ): bool {
    if ($fields === []) return false;

    $sets = [];
    $types = '';
    $values = [];
    foreach ($fields as $col => $val) {
      if (!isset($allowed[$col])) {
        throw new InvalidArgumentException("Field not updatable: $col");
      }
      $sets[] = "`$col` = ?";
      $types .= $allowed[$col];
      $values[] = $val;
    }

    $types .= is_int($keyVal) ? 'i' : 's';
    $values[] = $keyVal;

    $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE `$keyCol` = ?";
    return $this->exec($sql, $types, $values) > 0;
  }

  private function batchDelete(string $sqlWithLimitPlaceholder): int
  {
    $total = 0;
    $batch = self::PURGE_BATCH_SIZE;
    do {
      $deleted = $this->exec($sqlWithLimitPlaceholder, 'i', [$batch]);
      $total += $deleted;
    } while ($deleted === $batch);
    return $total;
  }

  private function exec(string $sql, string $types, array $values): int
  {
    $stmt = $this->db->prepare($sql);
    try {
      if ($types !== '') {
        $stmt->bind_param($types, ...$values);
      }
      $stmt->execute();
      return $stmt->affected_rows;
    } finally {
      $stmt->close();
    }
  }

  private function fetchOne(string $sql, string $types, array $values): ?array
  {
    $rows = $this->fetchAll($sql, $types, $values);
    return $rows[0] ?? null;
  }

  /** @return list<array<string,mixed>> */
  private function fetchAll(string $sql, string $types, array $values): array
  {
    $stmt = $this->db->prepare($sql);
    try {
      if ($types !== '') {
        $stmt->bind_param($types, ...$values);
      }
      $stmt->execute();
      $result = $stmt->get_result();
      if ($result === false) return [];
      $rows = [];
      while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
      }
      $result->free();
      return $rows;
    } finally {
      $stmt->close();
    }
  }
}
