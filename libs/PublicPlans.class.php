<?php

declare(strict_types=1);

/**
 * Plan catalog for the marketing pages, read from the account app.
 *
 * account.nostr.build owns the catalog (prices, tiers, feature bullets). This
 * class fetches its public projection so the PHP pages can show real pricing
 * without keeping a second copy that silently drifts — the divergence problem
 * this whole endpoint exists to solve. Nothing here interprets the catalog; it
 * only caches and hands back the decoded payload.
 *
 * Cache layout (dir: sys_get_temp_dir() . '/nb-feeds', shared with TorExitList):
 *   plans_<locale>.json   the last good payload; filemtime() is the freshness
 *                         clock, so there is no separate metadata file
 *
 * Failure policy is deliberately soft, because this only feeds a marketing
 * section: a fetch error serves the cached copy however old it is (stale beats
 * empty), and if there has never been a good copy `get()` returns null and the
 * caller renders its no-pricing fallback. A marketing page must never 500
 * because the plans API had a bad minute.
 */
final class PublicPlans
{
  public const ENDPOINT = 'https://account.nostr.build/api/public/plans';
  /** Matches the endpoint's own Cache-Control max-age. */
  public const MAX_AGE_SECONDS = 600;
  /** Don't hammer the origin while it is down. */
  public const RETRY_BACKOFF_SECONDS = 60;
  public const MAX_RESPONSE_BYTES = 256 * 1024;

  private const CONNECT_TIMEOUT_SECONDS = 2;
  private const TOTAL_TIMEOUT_SECONDS = 4;

  private readonly string $cacheFile;

  /** @var array<string,mixed>|null|false Per-request memo; false = not resolved yet. */
  private array|null|false $payload = false;

  public function __construct(
    private readonly string $locale = 'en',
    ?string $cacheDir = null,
  ) {
    $dir = $cacheDir ?? sys_get_temp_dir() . '/nb-feeds';
    // Locale is part of the filename, so keep it to the shape a locale tag has.
    $safeLocale = preg_match('/^[a-z]{2}$/', $this->locale) === 1 ? $this->locale : 'en';
    $this->cacheFile = $dir . '/plans_' . $safeLocale . '.json';
  }

  /**
   * The decoded payload, or null when we have never successfully fetched one.
   *
   * @return array<string,mixed>|null
   */
  public function get(): ?array
  {
    if ($this->payload !== false) {
      return $this->payload;
    }

    $cached = $this->readCache();
    $age = $this->cacheAge();

    if ($cached !== null && $age !== null && $age < self::MAX_AGE_SECONDS) {
      return $this->payload = $cached;
    }

    // Stale or missing. Back off if we tried very recently and still have a
    // copy to serve — a failing origin shouldn't cost every visitor a timeout.
    if ($cached !== null && $age !== null && $age < self::RETRY_BACKOFF_SECONDS) {
      return $this->payload = $cached;
    }

    $fresh = $this->fetch();
    if ($fresh !== null) {
      $this->writeCache($fresh);
      return $this->payload = $fresh;
    }

    // Fetch failed. Touch the cache file so the backoff applies to the next
    // request, then serve whatever we last had.
    if ($cached !== null) {
      @touch($this->cacheFile);
    }
    return $this->payload = $cached;
  }

  /**
   * Just the plan list, or [] when unavailable — the shape templates want.
   *
   * @return list<array<string,mixed>>
   */
  public function plans(): array
  {
    $payload = $this->get();
    $plans = $payload['plans'] ?? null;
    return is_array($plans) ? array_values($plans) : [];
  }

  /** Lowest 1-year price across the catalog, or null when unavailable. */
  public function fromPriceUsd(): ?int
  {
    $prices = [];
    foreach ($this->plans() as $plan) {
      if (isset($plan['priceUsd']) && is_int($plan['priceUsd'])) {
        $prices[] = $plan['priceUsd'];
      }
    }
    return $prices === [] ? null : min($prices);
  }

  // =========================================================================
  // INTERNALS
  // =========================================================================

  /** @return array<string,mixed>|null */
  private function fetch(): ?array
  {
    $url = self::ENDPOINT . '?lang=' . rawurlencode($this->locale);
    $ch = curl_init($url);
    if ($ch === false) {
      return null;
    }
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
      CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT_SECONDS,
      CURLOPT_FOLLOWLOCATION => false,
      CURLOPT_ACCEPT_ENCODING => '',
      CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    // No curl_close(): deprecated in PHP 8.5 and a no-op since 8.0 — the handle
    // is freed when $ch goes out of scope.

    if (!is_string($body) || $status !== 200 || strlen($body) > self::MAX_RESPONSE_BYTES) {
      return null;
    }

    $decoded = json_decode($body, true);
    // Only accept a payload that actually carries plans — a valid-JSON error
    // body must not overwrite a good cache.
    if (!is_array($decoded) || !isset($decoded['plans']) || !is_array($decoded['plans']) || $decoded['plans'] === []) {
      return null;
    }
    return $decoded;
  }

  /** @return array<string,mixed>|null */
  private function readCache(): ?array
  {
    if (!is_readable($this->cacheFile)) {
      return null;
    }
    $raw = @file_get_contents($this->cacheFile);
    if (!is_string($raw)) {
      return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
  }

  private function cacheAge(): ?int
  {
    $mtime = @filemtime($this->cacheFile);
    return $mtime === false ? null : max(0, time() - $mtime);
  }

  /** @param array<string,mixed> $payload */
  private function writeCache(array $payload): void
  {
    $dir = dirname($this->cacheFile);
    if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
      return;
    }
    $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
      return;
    }
    // Same-directory rename is atomic on POSIX, so readers never see a partial
    // file. getmypid() keeps concurrent FPM workers off each other's temp file.
    $tmp = $this->cacheFile . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $encoded) === false) {
      return;
    }
    if (!@rename($tmp, $this->cacheFile)) {
      @unlink($tmp);
    }
  }
}
