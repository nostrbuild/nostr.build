<?php

class CloudflarePurger
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
    return hash_hmac('sha256', "/$randomString/$expiry", $this->apiSecret, true);
  }

  public function purgeFiles($files, $cacheInvalidation = false)
  {
    try {
      $randomString = $this->generateRandomString();
      $expiry = $this->generateExpiry();
      $token = base64_encode($this->generateHMACToken($randomString, $expiry));

      $url = $this->apiEndpoint . "/$randomString/$expiry";
      $targetFiles = $files;
      if ($cacheInvalidation) {
        /* Cache invalidation */
        /*
        	invalidationType: 'url' | 'wildcard';
          invalidationValue: string | string[];
        */
        $targetFiles = [
          'invalidationType' => 'url',
          'invalidationValue' => $files,
        ];
      }

      $options = [
        'http' => [
          'method' => 'POST',
          'header' => "Authorization: Bearer " . $token,
          'content' => json_encode($targetFiles),
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

/* Usage
*try {
*    $apiSecret = $_SERVER['NB_API_SECRET'];  // replace with your actual API secret
*    $apiEndpoint = $_SERVER['NB_API_PURGE_URL'];// replace with your actual API endpoint
*
*    $purger = new CloudflarePurger($apiSecret, $apiEndpoint);
*
*    $filesToPurge = ['file1.png', 'file2.jpg'];  // replace with actual filenames
*    $result = $purger->purgeFiles($filesToPurge);
*
*    if ($result !== false) {
*        print_r($result);
*    }
*} catch (Exception $e) {
*    echo "An error occurred: " . $e->getMessage() . "\n";
*}
*/
