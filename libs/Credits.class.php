<?php

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';

/**
 * Class Credits
 *
 * This class is responsible for managing credits within the application.
 * It provides methods to add, remove, and query credits for users.
 *
 * @package nostr.build.libs
 */
class Credits
{
  private string $userNpub;
  private string $baseApiUrl;
  private string $apiKey;
  private mysqli $db;
  private Account $account;

  /**
   * Constructor for the Credits class.
   *
   * @param string $userNpub The user's public key in Nostr.
   * @param string $baseApiUrl The base URL for the API.
   * @param string $apiKey The API key for authentication.
   * @param mysqli $db The MySQLi database connection object.
   */
  public function __construct(string $userNpub, string $baseApiUrl, string $apiKey, mysqli $db)
  {
    $this->userNpub = $userNpub;
    $this->baseApiUrl = $baseApiUrl;
    $this->apiKey = $apiKey;
    $this->db = $db;
    $this->account = new Account($this->userNpub, $this->db);
  }

  private function getUserParameters(): array
  {
    return [
      'user_npub' => $this->account->getNpub(),
      'user_level' => $this->account->getAccountLevelInt(),
      'user_sub_period' => $this->account->getSubscriptionPeriod()
    ];
  }

  /**
   * Retrieves the current credits balance.
   *
   * @return array An associative array containing the credits balance information.
   */
  public function getCreditsBalance(): array
  {
    $url = $this->baseApiUrl . '/sd/credits';
    $userParams = $this->getUserParameters();
    // Get user npub, level and subscription period
    $userNpub = urlencode($userParams['user_npub'] ?? '');
    $userLevel = urlencode("{$userParams['user_level']}" ?? '0');
    $userSubPeriod = urlencode($userParams['user_sub_period'] ?? '1y');
    // Constract the request url with query parameters
    $url .= "?user_npub={$userNpub}&user_level={$userLevel}&user_sub_period={$userSubPeriod}";

    return $this->fetchRequest($url);
  }

  /**
   * Top up credits for a user.
   *
   * @param int $amount The amount of credits to top up.
   * @param string $invoiceId The ID of the invoice associated with the top-up.
   * @param array $invoiceDetails An array containing details of the invoice.
   * 
   * @return array An array containing the result of the top-up operation.
   */
  public function topupCredits(int $amount, string $invoiceId, array $invoiceDetails): array
  {
    // The API ensures idempotency by checking if the invoiceId already exists
    $url = $this->baseApiUrl . '/sd/credits';
    $userParams = $this->getUserParameters();
    // Get user npub, level and subscription period
    $body = json_encode([
      'user_npub' => $userParams['user_npub'],
      'userLevel' => $userParams['user_level'],
      'userSubscriptionLength' => $userParams['user_sub_period'],
      'amount' => $amount,
      'invoiceId' => $invoiceId,
      'invoiceDetails' => json_encode($invoiceDetails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $this->fetchRequest($url, 'POST', $body);
  }

  /**
   * Retrieves the transaction history.
   *
   * @param string $type The type of transactions to retrieve. Default is "all".
   * @param int|null $limit The maximum number of transactions to retrieve. Default is null.
   * @param int|null $offset The number of transactions to skip before starting to collect the result set. Default is null.
   * @return array An array containing the transaction history.
   */
  public function getTransactionsHistory(string $type = "all", ?int $limit = null, ?int $offset = null): array
  {
    // Type can be "all", "credit", "debit"
    $url = $this->baseApiUrl . "/sd/credits/{$type}";
    $userParams = $this->getUserParameters();
    // Get user npub, level and subscription period
    $userNpub = urlencode($userParams['user_npub'] ?? '');
    $userLevel = urlencode("{$userParams['user_level']}" ?? '0');
    $userSubPeriod = urlencode($userParams['user_sub_period'] ?? '1y');
    // Constract the request url with query parameters
    $url .= "?user_npub={$userNpub}&user_level={$userLevel}&user_sub_period={$userSubPeriod}";
    if (!empty($limit)) {
      $offset = $offset ?? 0;
      $url .= "&limit={$limit}&offset={$offset}";
    }

    return $this->fetchRequest($url);
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
      curl_close($ch);
      throw new Exception("cURL request failed: {$error}");
    }
    // Depending on the returned HTTP status code, handle the response
    $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    if ($httpCode !== 200) {
      throw new Exception("Unexpected HTTP code: HTTP {$httpCode}");
    }
    // Close the cURL handle
    curl_close($ch);

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
