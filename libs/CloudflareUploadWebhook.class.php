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
    string $fileType, // 'video' | 'audio' | 'image' | 'other'
    string $uploadAccountType, // 'free' | 'subscriber' | 'other'
    bool $shouldTranscode = false,
    int $uploadTime = null,
    string $fileOriginalUrl = null,
    string $uploadNpub = null,
    string $uploadedFileInfo = null,
    string $orginalSha256Hash = null // NIP-96
  ): void {
    $uploadTime = $uploadTime ?? time();  // Set default value if null

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
    ];

    if ($fileOriginalUrl !== null) {
      $this->payload['fileOriginalUrl'] = $fileOriginalUrl;
    }

    if ($uploadNpub !== null) {
      $this->payload['uploadNpub'] = $uploadNpub;
    }

    if ($uploadedFileInfo !== null) {
      $this->payload['uploadedFileInfo'] = $uploadedFileInfo;
    }
  }

  public function sendPayload(): array
  {
    try {
      $randomString = $this->generateRandomString();
      $expiry = $this->generateExpiry();
      $token = base64_encode($this->generateHMACToken($randomString, $expiry));
      $url = $this->apiEndpoint . "/$randomString/$expiry";
      // DEBUG
      error_log("Sending payload:" . json_encode($this->payload, JSON_UNESCAPED_SLASHES) . "\n");

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->payload, JSON_UNESCAPED_SLASHES));
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

      $response = curl_exec($ch);
      if ($response === false) {
        $errorCode = curl_errno($ch);
        $errorMessage = curl_error($ch);
        curl_close($ch);  // Always close the handle
        throw new Exception("cURL error ({$errorCode}): {$errorMessage}");
      }

      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      curl_close($ch);  // Always close the handle

      if ($httpCode != 200) {
        throw new Exception("HTTP request failed with code $httpCode: $response");
      }

      return json_decode($response, true) ?? [];
    } catch (Exception $e) {
      error_log("An error occurred: " . $e->getMessage() . "\n");
      return [];
    }
  }
}
