<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';

/**
 * Class Credits
 *
 * Thin client for the AI credit ledger. Credit reads (balance + history) and
 * grants now run in the account.nostr.build Worker, straight against the shared
 * ledger D1 — the only surviving use here is linking a generated media id onto
 * its debit row after a PHP-side Stability generation.
 *
 * @package nostr.build.libs
 */
class Credits
{
  private string $owner;
  private string $baseApiUrl;
  private string $apiKey;
  private mysqli $db;
  private Account $account;

  /**
   * Constructor for the Credits class.
   *
   * @param string $owner The user's stable uuid (an npub is also accepted for
   *   legacy callers; both resolve to the same account).
   * @param string $baseApiUrl The base URL for the API.
   * @param string $apiKey The API key for authentication.
   * @param mysqli $db The MySQLi database connection object.
   */
  public function __construct(string $owner, string $baseApiUrl, string $apiKey, mysqli $db)
  {
    $this->owner = $owner;
    $this->baseApiUrl = $baseApiUrl;
    $this->apiKey = $apiKey;
    $this->db = $db;
    // Accept either a uuid (current) or an npub (legacy); normalise to the
    // stable uuid so the account loads for key-less ("email") users too.
    $ownerUuid = resolveOwnerUuid($this->db, $owner) ?? '';
    $this->account = Account::fromUuid($ownerUuid, $this->db) ?? new Account('', $this->db, $ownerUuid);
  }

  public function updateTransactionWithMediaId(string $transactionId, string $mediaId): array
  {
    $url = $this->baseApiUrl . "/sd/credits/tx/{$transactionId}";
    $body = json_encode(['mediaId' => $mediaId]);
    return $this->fetchRequest($url, 'PUT', $body);
  }

  private function fetchRequest(string $url, string $method = 'GET', ?string $body = null): array
  {
    // Fetch the request
    // Initialize cURL
    $ch = curl_init($url);
    // Set curl method
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    // Set body if it's not empty
    if (!empty($body) && $method !== 'GET') {
      curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    // Set cURL options
    $bearer = signApiRequest($this->apiKey, $url, $method, $body);
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
    if ($httpCode !== 200) {
      throw new Exception("Unexpected HTTP code: HTTP {$httpCode}");
    }
    // Close the cURL handle
    $ch = null;

    return json_decode($response, true);
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
function signApiRequest(string $key, string $url, ?string $method = 'GET', ?string $body = null): string
{
  // If $body is not empty, and $method is 'GET', throw an exception
  if (!empty($body) && $method === 'GET') {
    throw new Exception('Invalid method for signing request.');
  }
  // Get body sha256 hash if it's not empty
  $bodySha256 = !empty($body) ? hash('sha256', $body) : 'SHA256';
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
