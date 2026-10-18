<?php

class CloudflareUploadWebhook
{
  private $apiSecret;
  private $apiEndpoint;
  private array $payload;


  public function __construct($apiSecret, $apiEndpoint)
  {
    $this->apiSecret = $apiSecret;
    $this->apiEndpoint = $apiEndpoint;
  }

  public function generateRandomString($length = 32)
  {
    try {
      return bin2hex(random_bytes($length / 2));
    } catch (Exception $e) {
      throw new Exception('Could not generate random string: ' . $e->getMessage());
    }
  }

  public function generateExpiry($duration = 60)
  {
    return time() + $duration;
  }

  public function generateHMACToken($randomString, $expiry)
  {
    return hash_hmac('sha256', $this->apiEndpoint . "/$randomString/$expiry", $this->apiSecret, true);
  }

  public function createPayload(
    string $fileHash,
    string $fileName,
    int $fileSize,
    string $fileMimeType,
    string $fileUrl,
    string $fileType, // 'video' | 'audio' | 'image' | "archive" | "document" | "text" | 'other'
    string $uploadAccountType, // 'free' | 'subscriber' | 'other'
    bool $shouldTranscode = false,
    ?int $uploadTime = null,
    ?string $fileOriginalUrl = null,
    ?string $uploadNpub = null,
    ?string $uploadUserUUID = null,
    ?string $uploadedFileInfo = null,
    ?string $orginalSha256Hash = null, // NIP-96 (kept for compatibility)
    ?string $currentSha256Hash = null, // Blossom
    bool $doVirusScan = false
  ): void {
    $uploadTime = $uploadTime ?? time();

    $this->payload = [
      'fileHash' => $fileHash,
      'fileName' => $fileName,
      'fileSize' => $fileSize,
      'fileMimeType' => $fileMimeType,
      'fileUrl' => $fileUrl,
      'fileType' => $fileType,
      'shouldTranscode' => $shouldTranscode,
      'uploadAccountType' => $uploadAccountType,
      'uploadTime' => $uploadTime,
      'orginalSha256Hash' => $orginalSha256Hash ?? '', // NIP96
      'currentSha256Hash' => $currentSha256Hash ?? '', // Blossom
      'doVirusScan' => $doVirusScan,
    ];

    if ($fileOriginalUrl !== null) {
      $this->payload['fileOriginalUrl'] = $fileOriginalUrl;
    }

    if ($uploadNpub !== null) {
      $this->payload['uploadNpub'] = $uploadNpub;
    }

    if ($uploadUserUUID !== null) {
      $this->payload['uploadUserUUID'] = $uploadUserUUID;
    }

    if ($uploadedFileInfo !== null) {
      $this->payload['uploadedFileInfo'] = $uploadedFileInfo;
    }
  }

  // The upload-manager worker answers 503 when it cannot start the upload's workflow, and
  // this sender used to give up after one attempt, so that upload was never moderated or
  // scanned (incl. PhotoDNA). Found 2026-10-18. The first attempt stays inline, as before;
  // only after a retryable failure is the webhook queued, and the queue is retried after
  // the response has been flushed (PHP-FPM), so retries never delay the uploader.
  private const RETRY_DELAYS_SECONDS = [2, 5]; // so 1 + 2 = 3 attempts in all
  private const CONNECT_TIMEOUT_SECONDS = 10;
  // Above the worker's worst case (it retries Workflows for up to ~36 s); a shorter
  // timeout could cut the worker off mid-retry.
  private const REQUEST_TIMEOUT_SECONDS = 45;
  // Cap on the deferred retries of one request, so an outage can't hold an FPM worker long.
  private const DEFERRED_BUDGET_SECONDS = 120;
  /** @var array<int, array{body: string, fileHash: string}> */
  private array $retryQueue = [];

  /**
   * Send the current payload. Returns the worker's decoded response on success, [] on a
   * failure that won't be retried, and ['status' => 'retry-queued'] when it was queued
   * for the deferred retries.
   */
  public function sendPayload(): array
  {
    // Invalid UTF-8 (e.g. in a User-Agent) would make json_encode return false and send an empty body.
    $body = (string)json_encode($this->payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    $fileHash = (string)($this->payload['fileHash'] ?? '');

    $outcome = $this->attempt($body, $fileHash, 1);
    if ($outcome !== null) {
      return $outcome;
    }
    $deferred = function_exists('fastcgi_finish_request');
    if (!$deferred && PHP_SAPI !== 'cli') {
      // Retrying here would hold the uploader's response; without FPM there's no flush.
      error_log("Upload webhook gave up for $fileHash: no retries without PHP-FPM\n");
      return [];
    }
    $first = $this->retryQueue === [];
    $this->retryQueue[] = ['body' => $body, 'fileHash' => $fileHash];
    if (!$deferred) {
      $this->drainRetryQueue(); // CLI: nobody waits on a response
      return [];
    }
    if ($first) {
      register_shutdown_function(function (): void {
        try {
          // The session lock is released only after shutdown functions; free it first.
          if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
          }
        } catch (\Throwable $e) {
          error_log("Upload webhook: session close before retries failed: " . $e->getMessage() . "\n");
        }
        fastcgi_finish_request();
        $this->drainRetryQueue();
      });
    }
    return ['status' => 'retry-queued'];
  }

  /** Retry every queued webhook in passes (all 2nd attempts, then all 3rd) within the budget. */
  private function drainRetryQueue(): void
  {
    try {
      $deadline = time() + self::DEFERRED_BUDGET_SECONDS;
      $attempt = 1;
      foreach (self::RETRY_DELAYS_SECONDS as $delay) {
        $attempt++;
        if ($this->retryQueue === [] || time() + $delay + self::REQUEST_TIMEOUT_SECONDS > $deadline) {
          break;
        }
        sleep($delay);
        $pending = [];
        foreach ($this->retryQueue as $item) {
          if (time() + self::REQUEST_TIMEOUT_SECONDS > $deadline || $this->attempt($item['body'], $item['fileHash'], $attempt) === null) {
            $pending[] = $item;
          }
        }
        $this->retryQueue = $pending;
      }
    } catch (\Throwable $e) {
      error_log("Upload webhook deferred retries failed: " . $e->getMessage() . "\n");
    }
    foreach ($this->retryQueue as $item) {
      error_log("Upload webhook gave up for {$item['fileHash']}; the upload was not handed to the worker\n");
    }
    $this->retryQueue = [];
  }

  /**
   * One delivery attempt. Returns the decoded response on success, [] on a failure a
   * retry won't fix (a 4xx other than 408/429), or null when it is worth retrying.
   */
  private function attempt(string $body, string $fileHash, int $attempt): ?array
  {
    $of = 1 + count(self::RETRY_DELAYS_SECONDS);
    try {
      [$httpCode, $response] = $this->post($body);
    } catch (\Throwable $e) {
      error_log("Upload webhook attempt $attempt/$of for $fileHash failed: " . $e->getMessage() . "\n");
      return null;
    }
    if ($httpCode === 200) {
      $res = json_decode($response, true);
      error_log("Response from webhook: " . json_encode($res, JSON_UNESCAPED_SLASHES) . "\n");
      return is_array($res) ? $res : [];
    }
    error_log("Upload webhook attempt $attempt/$of for $fileHash failed with HTTP $httpCode: " . substr((string)$response, 0, 300) . "\n");
    if ($httpCode === 408 || $httpCode === 429 || $httpCode >= 500) {
      return null;
    }
    return [];
  }

  /** One POST with a fresh token. Returns [httpCode, body]; throws on a cURL error. */
  private function post(string $body): array
  {
    $randomString = $this->generateRandomString();
    $expiry = $this->generateExpiry();
    $token = base64_encode($this->generateHMACToken($randomString, $expiry));
    $url = $this->apiEndpoint . "/$randomString/$expiry";

    $ch = curl_init($url);
    if ($ch === false) {
      throw new Exception('curl_init failed');
    }
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Bearer $token",
      "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT_SECONDS);
    curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT_SECONDS);

    $response = curl_exec($ch);
    if ($response === false) {
      $errorCode = curl_errno($ch);
      $errorMessage = curl_error($ch);
      $ch = null;
      throw new Exception("cURL error ({$errorCode}): {$errorMessage}");
    }
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ch = null;
    return [$httpCode, $response];
  }
}
