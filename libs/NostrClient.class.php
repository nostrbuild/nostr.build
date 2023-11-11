<?php
require_once __DIR__ . '/NostrEvent.class.php';
require_once __DIR__ . '/Bech32.class.php';


class NostrClient
{
  private $apiSecret;
  private $apiEndpoint;

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
    $hashData = $this->apiEndpoint . "/$randomString/$expiry";
    return hash_hmac('sha256', $hashData, $this->apiSecret, true);
  }

  public function sendDm(string $toNpubHex, string $message)
  {
    try {
      $randomString = $this->generateRandomString();
      $expiry = $this->generateExpiry();
      $token = base64_encode($this->generateHMACToken($randomString, $expiry));

      $url = $this->apiEndpoint . "/$randomString/$expiry";

      $content = [
        [
          'type' => 'dm', // 'dm', 'event', 'presigned_note'
          'to' => $toNpubHex,
          'message' => $message,
        ]
      ];

      $options = [
        'http' => [
          'method' => 'POST',
          'header' => "Authorization: Bearer " . $token,
          'content' => json_encode($content), // API expects array of messages
        ],
      ];

      $context = stream_context_create($options);
      $response = @file_get_contents($url, false, $context);

      if ($response === false) {
        $error = error_get_last();
        throw new Exception("HTTP request failed: {$error['message']}");
      }

      return json_decode($response, true);
    } catch (Exception $e) {
      echo "An error occurred: " . $e->getMessage() . "\n";
      return false;
    }
  }
}
