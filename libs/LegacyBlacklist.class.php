<?php

declare(strict_types=1);

/**
 * Thin wrapper around the legacy `blacklist` table (npub / ip / user_agent).
 *
 * SCHEMA (kept as-is for the MySQL → next-DB migration):
 *
 *   CREATE TABLE blacklist (
 *     id         INT AUTO_INCREMENT PRIMARY KEY,
 *     npub       VARCHAR(255) NULL,
 *     ip         VARCHAR(45)  NULL,
 *     user_agent VARCHAR(255) NULL,
 *     timestamp  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
 *     reason     VARCHAR(255) NULL,
 *     KEY (npub), KEY (ip)
 *   );
 *
 * NOTES
 * -----
 * - Plain string match on `ip`; no CIDR semantics. CIDR-style blocking lives
 *   in IpAccessControl (ip_blocklist). This class is the stop-gap manager
 *   for the historical npub-ban store still used by the upload pipeline.
 * - Existing INSERTs in routes_admin.php / PhotoDNA / NCMECReportHandler are
 *   left untouched on purpose; this class only adds a managed surface.
 */
final class LegacyBlacklist
{
  /** Hard ceiling for list endpoints. */
  private const MAX_LIST_LIMIT = 1000;

  public function __construct(private readonly mysqli $db) {}

  // ---------------------------------------------------------------------------
  // READ
  // ---------------------------------------------------------------------------

  /**
   * @param array{q?:string,limit?:int,offset?:int} $opts
   *   q — substring match on npub OR ip (case-insensitive on npub).
   * @return list<array<string,mixed>>
   */
  public function list(array $opts = []): array
  {
    $limit  = $this->clampLimit((int) ($opts['limit']  ?? 100));
    $offset = max(0, (int) ($opts['offset'] ?? 0));
    $q      = isset($opts['q']) ? trim((string) $opts['q']) : '';

    if ($q !== '') {
      $like = '%' . $q . '%';
      $sql  = 'SELECT id, npub, ip, user_agent, reason,
                      DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:%s") AS timestamp
                 FROM blacklist
                WHERE npub LIKE ? OR ip LIKE ?
                ORDER BY id DESC
                LIMIT ? OFFSET ?';
      return $this->fetchAll($sql, 'ssii', [$like, $like, $limit, $offset]);
    }

    $sql = 'SELECT id, npub, ip, user_agent, reason,
                   DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:%s") AS timestamp
              FROM blacklist
             ORDER BY id DESC
             LIMIT ? OFFSET ?';
    return $this->fetchAll($sql, 'ii', [$limit, $offset]);
  }

  public function count(string $q = ''): int
  {
    if ($q !== '') {
      $like = '%' . $q . '%';
      $row  = $this->fetchOne(
        'SELECT COUNT(*) AS c FROM blacklist WHERE npub LIKE ? OR ip LIKE ?',
        'ss',
        [$like, $like],
      );
    } else {
      $row = $this->fetchOne('SELECT COUNT(*) AS c FROM blacklist', '', []);
    }
    return (int) ($row['c'] ?? 0);
  }

  public function findById(int $id): ?array
  {
    return $this->fetchOne(
      'SELECT id, npub, ip, user_agent, reason,
              DATE_FORMAT(timestamp, "%Y-%m-%d %H:%i:%s") AS timestamp
         FROM blacklist WHERE id = ?',
      'i',
      [$id],
    );
  }

  public function isNpubBanned(string $npub): bool
  {
    if ($npub === '') return false;
    $row = $this->fetchOne(
      'SELECT 1 AS x FROM blacklist WHERE npub = ? LIMIT 1',
      's',
      [$npub],
    );
    return $row !== null;
  }

  public function isIpBanned(string $ip): bool
  {
    if ($ip === '') return false;
    $row = $this->fetchOne(
      'SELECT 1 AS x FROM blacklist WHERE ip = ? LIMIT 1',
      's',
      [$ip],
    );
    return $row !== null;
  }

  // ---------------------------------------------------------------------------
  // WRITE
  // ---------------------------------------------------------------------------

  /**
   * Insert a new entry. Caller must supply at least one of npub/ip — a row
   * with both columns null is meaningless.
   *
   * @return int Inserted row id.
   * @throws InvalidArgumentException If neither npub nor ip is provided.
   */
  public function add(?string $npub, ?string $ip, ?string $userAgent, ?string $reason): int
  {
    // Normalize: trim, then collapse empty strings to null so we don't
    // store meaningless "" rows.
    $norm = static fn(?string $v): ?string => ($v === null || trim($v) === '') ? null : trim($v);
    $npub      = $norm($npub);
    $ip        = $norm($ip);
    $userAgent = $norm($userAgent);
    $reason    = $norm($reason);

    if ($npub === null && $ip === null) {
      throw new InvalidArgumentException('At least one of npub or ip is required');
    }

    if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP) === false) {
      throw new InvalidArgumentException("Invalid IP: $ip");
    }

    $stmt = $this->db->prepare(
      'INSERT INTO blacklist (npub, ip, user_agent, reason) VALUES (?, ?, ?, ?)'
    );
    try {
      $stmt->bind_param('ssss', $npub, $ip, $userAgent, $reason);
      $stmt->execute();
      return (int) $this->db->insert_id;
    } finally {
      $stmt->close();
    }
  }

  public function removeById(int $id): bool
  {
    return $this->exec('DELETE FROM blacklist WHERE id = ?', 'i', [$id]) > 0;
  }

  public function removeAllByNpub(string $npub): int
  {
    if ($npub === '') return 0;
    return $this->exec('DELETE FROM blacklist WHERE npub = ?', 's', [$npub]);
  }

  /**
   * Delete rows matching (npub, reason) inserted within [start, end] inclusive.
   * Used by NCMEC false-match unblacklisting.
   *
   * @param string $start MySQL DATETIME ("Y-m-d H:i:s")
   * @param string $end   MySQL DATETIME ("Y-m-d H:i:s")
   */
  public function removeByNpubReasonInWindow(string $npub, string $reason, string $start, string $end): int
  {
    if ($npub === '') return 0;
    return $this->exec(
      'DELETE FROM blacklist WHERE npub = ? AND reason = ? AND timestamp BETWEEN ? AND ?',
      'ssss',
      [$npub, $reason, $start, $end],
    );
  }

  // ---------------------------------------------------------------------------
  // INTERNAL
  // ---------------------------------------------------------------------------

  private function clampLimit(int $n): int
  {
    if ($n < 1) return 1;
    if ($n > self::MAX_LIST_LIMIT) return self::MAX_LIST_LIMIT;
    return $n;
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
