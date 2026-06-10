<?php

declare(strict_types=1);

/**
 * Tor exit-node list with a per-server file cache.
 *
 * Policy: uploads from accounts without an active paid plan are rejected when
 * the client IP is a known Tor exit (enforced in UploadValidator). This class
 * only maintains and queries the IP set.
 *
 * Cache layout (default dir: sys_get_temp_dir() . '/nb-feeds'):
 *   tor_exits.txt            one canonical IP per line; filemtime() is the
 *                            freshness clock (no separate metadata file)
 *   tor_exits.lock           flock election target; its mtime is the
 *                            retry-backoff marker (last refresh attempt)
 *   tor_exits.txt.tmp.<pid>  transient; rename()d over the live file
 *
 * Concurrency:
 *   - same server: flock(LOCK_EX|LOCK_NB) elects exactly one FPM worker; the
 *     OS releases the lock on process death, so a crash cannot wedge refreshes
 *   - cross server: none needed — each server behind the VIP refreshes its own
 *     cache off its own traffic share
 *   - readers vs writer: same-directory rename() is atomic on POSIX
 *
 * The refresh runs in a shutdown function and calls fastcgi_finish_request()
 * before any network I/O, so it never delays a response. All failure modes
 * keep the previous file (stale beats empty) and lookups fail open.
 */
final class TorExitList
{
  public const FEED_URL = 'https://check.torproject.org/exit-addresses';
  public const MAX_AGE_SECONDS = 6 * 3600;
  /** Min seconds between refresh attempts (also applies after failures). */
  public const RETRY_BACKOFF_SECONDS = 300;
  /** Refuse to swap if the parsed list is suspiciously small (normal: 1-2k). */
  public const MIN_EXPECTED_IPS = 100;
  public const MAX_RESPONSE_BYTES = 5 * 1024 * 1024;

  private const CONNECT_TIMEOUT_SECONDS = 5;
  private const TOTAL_TIMEOUT_SECONDS = 15;

  private readonly string $cacheDir;
  private readonly string $listFile;
  private readonly string $lockFile;

  /** @var array<string,true>|null Per-request memoized IP set. */
  private ?array $ipSet = null;

  public function __construct(
    ?string $cacheDir = null,
    private readonly string $feedUrl = self::FEED_URL,
  ) {
    $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/nb-feeds';
    $this->listFile = $this->cacheDir . '/tor_exits.txt';
    $this->lockFile = $this->cacheDir . '/tor_exits.lock';
  }

  // =========================================================================
  // HOT PATH
  // =========================================================================

  /**
   * True when $ip is a known Tor exit. Fails open: missing/unreadable cache
   * file or malformed input returns false.
   */
  public function contains(string $ip): bool
  {
    $canon = self::canonicalizeIp($ip);
    if ($canon === null) return false;
    return isset($this->loadSet()[$canon]);
  }

  /** @return array<string,true> */
  private function loadSet(): array
  {
    if ($this->ipSet !== null) return $this->ipSet;
    $this->ipSet = [];
    $lines = @file($this->listFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) return $this->ipSet; // no cache yet — fail open
    foreach ($lines as $line) {
      $this->ipSet[$line] = true; // file is written canonical by refresh()
    }
    return $this->ipSet;
  }

  // =========================================================================
  // REFRESH SCHEDULING
  // =========================================================================

  /**
   * Cheap per-request probe: one stat() (PHP stat-cached). If the list is
   * missing/stale and no attempt happened within the backoff window, register
   * the refresh to run at shutdown (post-response). Never throws.
   */
  public function maybeScheduleRefresh(): void
  {
    try {
      $mtime = @filemtime($this->listFile);
      if ($mtime !== false && $mtime > time() - self::MAX_AGE_SECONDS) {
        return; // fresh
      }
      $attempt = @filemtime($this->lockFile);
      if ($attempt !== false && $attempt > time() - self::RETRY_BACKOFF_SECONDS) {
        return; // a worker tried recently — back off
      }
      register_shutdown_function(function (): void {
        try {
          $this->refresh();
        } catch (\Throwable $e) {
          error_log('TorExitList: refresh failed: ' . $e->getMessage());
        }
      });
    } catch (\Throwable $e) {
      error_log('TorExitList: scheduling failed: ' . $e->getMessage());
    }
  }

  /**
   * Download, filter, and atomically swap the list. Designed to run in a
   * shutdown function but safe to call directly (CLI, tests).
   *
   * @return bool true when the list on disk is fresh on return (whether this
   *              worker refreshed it or another one just did)
   */
  public function refresh(): bool
  {
    if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0775, true) && !is_dir($this->cacheDir)) {
      error_log('TorExitList: cannot create cache dir ' . $this->cacheDir);
      return false;
    }

    $lockFh = @fopen($this->lockFile, 'c');
    if ($lockFh === false) {
      error_log('TorExitList: cannot open lock file ' . $this->lockFile);
      return false;
    }

    try {
      if (!flock($lockFh, LOCK_EX | LOCK_NB)) {
        return false; // another worker is refreshing right now
      }

      // Double-check under the lock: another worker may have refreshed
      // between our staleness probe and now. The stat cache may hold the
      // pre-refresh result from earlier in this request.
      clearstatcache(true, $this->listFile);
      $mtime = @filemtime($this->listFile);
      if ($mtime !== false && $mtime > time() - self::MAX_AGE_SECONDS) {
        return true;
      }

      touch($this->lockFile); // start the retry-backoff window

      // Flush the response and close the client connection before any
      // network I/O. No-op outside php-fpm (CLI).
      if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
      }

      $body = $this->download();
      if ($body === null) {
        return false; // logged in download(); old file stays authoritative
      }

      $ips = self::parseExitAddresses($body);
      if (count($ips) < self::MIN_EXPECTED_IPS) {
        error_log('TorExitList: parsed only ' . count($ips)
          . ' IPs (floor ' . self::MIN_EXPECTED_IPS . '); keeping existing list');
        return false;
      }

      return $this->atomicWrite($ips);
    } finally {
      flock($lockFh, LOCK_UN);
      fclose($lockFh);
    }
  }

  // =========================================================================
  // INTERNAL
  // =========================================================================

  private function download(): ?string
  {
    $ch = curl_init($this->feedUrl);
    if ($ch === false) {
      error_log('TorExitList: curl_init failed');
      return null;
    }
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
      CURLOPT_TIMEOUT        => self::TOTAL_TIMEOUT_SECONDS,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS      => 3,
      CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
      CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_USERAGENT      => 'nostr.build-tor-exit-sync/1.0',
      CURLOPT_MAXFILESIZE    => self::MAX_RESPONSE_BYTES,
      CURLOPT_NOPROGRESS     => false,
      // MAXFILESIZE only works when Content-Length is advertised; the
      // progress callback enforces the cap on chunked responses too.
      CURLOPT_PROGRESSFUNCTION => function ($ch, $dlTotal, $dlNow): int {
        return $dlNow > self::MAX_RESPONSE_BYTES ? 1 : 0;
      },
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);

    if (!is_string($body) || $status !== 200) {
      error_log("TorExitList: download failed (status=$status, err=$err)");
      return null;
    }
    return $body;
  }

  /**
   * Extract exit IPs from TorDNSEL exit-addresses format. Only lines of the
   * form "ExitAddress <ip> <timestamp>" matter; everything else (ExitNode,
   * Published, LastStatus) is ignored.
   *
   * @return list<string> canonical, deduplicated
   */
  public static function parseExitAddresses(string $body): array
  {
    $set = [];
    foreach (preg_split('/\r?\n/', $body) as $line) {
      if (!str_starts_with($line, 'ExitAddress ')) continue;
      $ip = explode(' ', $line)[1] ?? '';
      $canon = self::canonicalizeIp($ip);
      if ($canon !== null) {
        $set[$canon] = true;
      }
    }
    return array_keys($set);
  }

  /**
   * Canonical text form so formatting variants ("2001:0DB8::1", v4-mapped
   * "::ffff:1.2.3.4") always match list entries.
   */
  private static function canonicalizeIp(string $ip): ?string
  {
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) return null;
    $bin = @inet_pton($ip);
    if ($bin === false) return null;
    // Unmap v4-mapped v6 so it matches the IPv4 entries the feed publishes.
    if (strlen($bin) === 16 && substr($bin, 0, 12) === "\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\xff\xff") {
      $bin = substr($bin, 12);
    }
    $canon = inet_ntop($bin);
    return $canon === false ? null : $canon;
  }

  /** @param list<string> $ips */
  private function atomicWrite(array $ips): bool
  {
    $tmp = $this->listFile . '.tmp.' . getmypid();
    $data = implode("\n", $ips) . "\n";
    if (@file_put_contents($tmp, $data) !== strlen($data)) {
      error_log('TorExitList: failed writing ' . $tmp);
      @unlink($tmp);
      return false;
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $this->listFile)) {
      error_log('TorExitList: rename to ' . $this->listFile . ' failed');
      @unlink($tmp);
      return false;
    }
    return true;
  }
}
