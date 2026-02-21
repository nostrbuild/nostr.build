<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';

/**
 * Class BlossomFrontEndAPI
 *
 * @package nostr.build.libs
 */
class BlossomFrontEndAPI
{
  private $baseApiUrl;
  private $apiKey;

  /**
  * Constructor for the BlossomFrontEndAPI client.
   *
   * @param string $baseApiUrl The base URL for the API.
   * @param string $apiKey The API key for authentication.
   */
  public function __construct(string $baseApiUrl, string $apiKey)
  {
    $this->baseApiUrl = $baseApiUrl;
    $this->apiKey = $apiKey;
  }

  public function deleteMedia(string $npub, string $mediaHash): bool
  {
    $url = "{$this->baseApiUrl}/delete/{$npub}/{$mediaHash}";
    $response = $this->fetchRequest($url, 'DELETE');
    return $response['status'] === 200 && $response['response']['status'] === 'success';
  }

  public function banMedia(string $mediaHash, string $reason = 'TOS Violation or legal reasons'): bool
  {
    $url = "{$this->baseApiUrl}/ban/blob/{$mediaHash}";
    // Create JSON body with { reason: $reason }
    $body = json_encode(['reason' => $reason]);
    $response = $this->fetchRequest($url, 'PATCH', $body);
    return $response['status'] === 200 && $response['response']['status'] === 'success';
  }

  public function unbanMedia(string $mediaHash): bool
  {
    $url = "{$this->baseApiUrl}/unban/blob/{$mediaHash}";
    $response = $this->fetchRequest($url, 'PATCH');
    return $response['status'] === 200 && $response['response']['status'] === 'success';
  }

  public function banUser(string $npub, string $reason = 'Repeated TOS Violation or legal reasons'): bool
  {
    $url = "{$this->baseApiUrl}/ban/user/{$npub}";
    // Create JSON body with { reason: $reason }
    $body = json_encode(['reason' => $reason]);
    $response = $this->fetchRequest($url, 'PATCH', $body);
    return $response['status'] === 200 && $response['response']['status'] === 'success';
  }

  public function unbanUser(string $npub): bool
  {
    $url = "{$this->baseApiUrl}/unban/user/{$npub}";
    $response = $this->fetchRequest($url, 'PATCH');
    return $response['status'] === 200 && $response['response']['status'] === 'success';
  }

  public function updateAccount(string $npub, array $data): bool
  {
    $url = "{$this->baseApiUrl}/update/{$npub}";
    // Create JSON body from account data.
    $body = json_encode($data);
    error_log("Body: {$body}");
    $response = $this->fetchRequest($url, 'PATCH', $body);
    return $response['status'] === 200 && $response['response']['status'] === 'success';
  }

  private function fetchRequest(string $url, string $method = 'GET', ?string $body = null): array
  {
    error_log("Fetching request: {$url}");
    // Initialize cURL
    $ch = curl_init($url);
    // Set curl method
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    // Set body if it's not empty
    if (!empty($body) && $method !== 'GET') {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    // Set cURL options
    $bearer = signBlossomApiRequest($this->apiKey, $url, $method, $body);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer {$bearer}"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if ($response === false) {
      $error = curl_error($ch);
      $ch = null;
      throw new Exception("cURL request failed: {$error}");
    }
    // Depending on the returned HTTP status code, handle the response
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    // Close the cURL handle
    $ch = null;

    $returnVal = [
      'status' => $httpCode,
      'response' => json_decode($response, true),
    ];

    return $returnVal;
  }
}

/**
 * Signs an API request with the given key, URL, method, and body.
 *
 * @param string $key The API key used for signing the request.
 * @param string $url The URL of the API endpoint.
 * @param string|null $method The HTTP method to be used for the request (default is 'GET').
 * @param string|null $body The body of the request, if applicable (default is null).
 * @return string The signed API request.
 */
function signBlossomApiRequest(string $key, string $url, ?string $method = 'GET', ?string $body = null): string
{
  // If $body is not empty, and $method is 'GET', throw an exception
  if (!empty($body) && $method === 'GET') {
    throw new Exception('Invalid method for signing request.');
  }
  // Get body sha256 hash if it's not empty
  $bodySha256 = !empty($body) ? hash('sha256', $body) : 'SHA256';
  //$bodySha256 = 'SHA256';
  // Upper case the method
  $method = strtoupper($method);
  // Prepare payload
  $payload = "{$method}|{$url}|{$bodySha256}|" . time();
  // Generate HMAC signature
  $hmac = hash_hmac('sha256', $payload, $key, true);
  // Base64 encode the HMAC signature
  $base64Hmac = base64_encode($hmac);
  // Prepare the bearer token
  $bearer = "HMAC|SHA256|" . time() . "|" . $base64Hmac;
  return $bearer;
}
