<?php

require_once __DIR__ . '/NostrEvent.class.php';
require_once __DIR__ . '/Bech32.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';

class NostrClient
{
  private $apiSecret;
  private $apiEndpoint;

  public function __construct($apiSecret, $apiEndpoint)
  {
    $this->apiSecret = $apiSecret;
    $this->apiEndpoint = $apiEndpoint;
  }

  private function generateRandomString($length = 32)
  {
    try {
      return bin2hex(random_bytes($length / 2));
    } catch (Exception $e) {
      throw new Exception('Could not generate random string: ' . $e->getMessage());
    }
  }

  private function generateExpiry($duration = 60)
  {
    return time() + $duration;
  }

  private function generateHMACToken($randomString, $expiry)
  {
    $hashData = $this->apiEndpoint . "/$randomString/$expiry";
    return hash_hmac('sha256', $hashData, $this->apiSecret, true);
  }

  private function sendRequest($url, $content, $randomString, $expiry)
  {
    $maxRetries = 3;
    $retryDelay = 1;

    for ($retry = 0; $retry < $maxRetries; $retry++) {
      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($content));
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . base64_encode($this->generateHMACToken($randomString, $expiry)),
      ]);

      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      $ch = null;

      if ($httpCode >= 200 && $httpCode < 300) {
        return json_decode($response, true);
      } else {
        echo "Request failed with HTTP status code: $httpCode. Retrying in {$retryDelay}s...\n";
        sleep($retryDelay);
        $retryDelay *= 2;
      }
    }

    throw new Exception("Request failed after $maxRetries retries.");
  }

  public function sendDm(string $toNpubHex, string | array $message, bool $otp = false)
  {
    try {
      $randomString = $this->generateRandomString();
      $expiry = $this->generateExpiry();

      $url = $this->apiEndpoint . "/$randomString/$expiry";

      $content = [
        [
          'type' => 'dm',
          'to' => $toNpubHex,
          'message' => $message,
          'otp' => $otp,
        ]
      ];

      return $this->sendRequest($url, $content, $randomString, $expiry);
    } catch (Exception $e) {
      echo "An error occurred: " . $e->getMessage() . "\n";
      return false;
    }
  }

  public function sendPresignedNote(string $presignedNote)
  {
    try {
      $randomString = $this->generateRandomString();
      $expiry = $this->generateExpiry();

      $url = $this->apiEndpoint . "/$randomString/$expiry";

      $content = [
        [
          'type' => 'presigned_note',
          'presigned_note' => json_decode($presignedNote, true),
        ]
      ];

      return $this->sendRequest($url, $content, $randomString, $expiry);
    } catch (Exception $e) {
      echo "An error occurred: " . $e->getMessage() . "\n";
      return false;
    }
  }

  public function getNpubInfo(string $npubHex): array
  {
    $apiQueryUrl = SiteConfig::getNostrApiBaseUrl() . urlencode($npubHex);

    // Initialize and set cURL options
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $apiQueryUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HEADER => false,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    ]);

    // Execute cURL and close
    $response = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $ch = null;

    // Handle cURL errors
    if ($response === false || $curlErrNo !== CURLE_OK) {
      error_log("Error fetching account data from Nostr API");
      return [];
    }

    // Decode JSON
    $responseData = json_decode($response ?? '{}');
    if (json_last_error() !== JSON_ERROR_NONE || $responseData === null) {
      error_log("Error decoding JSON response from Nostr API: " . json_last_error_msg());
      return [];
    }
    $responseData = json_decode($responseData->content) ?? [];
    return [
      'name' => $responseData->name ?? '',
      'wallet' => $responseData->lud16 ?? '',
      'picture' => $responseData->picture ?? '',
    ];
  }
}
