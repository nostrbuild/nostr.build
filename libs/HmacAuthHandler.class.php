<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ServerRequestInterface;

/**
 * Summary of HmacAuthHandler
 * Class to handle HMAC authentication
 */

class HmacAuthHandler
{

  private $headers;
  private $body;
  private $request;
  private $secret;

  /**
   * Summary of __construct
   * @param Request $request
   */
  public function __construct(ServerRequestInterface $request, string $secret)
  {
    $this->headers = $request->getHeaders();
    $this->body = $request->getBody()->getContents();
    $this->request = $request;
    $this->secret = $secret;
  }

  /**
   * Summary of handle
   * @throws \Exception
   * @return void
   */
  public function authenticate()
  {
    // check if 'Authorization' header exists
    if (!isset($this->headers['Authorization'][0])) {
      throw new Exception("Missing Authorization header", 401);
    }

    $auth = trim($this->headers['Authorization'][0]);

    // check if auth scheme is Bearer
    if (strpos($auth, "Bearer") !== 0) {
      throw new Exception("Invalid auth scheme", 401);
    }

    $token = substr($auth, 7);
    $bToken = base64_decode($token);

    if (empty($token) || empty($bToken) || $bToken[0] != 'H') {
      error_log("Invalid token: " . $token);
      throw new Exception("Invalid token", 401);
    }

    $data = json_decode($bToken, true);
    if ($data === null) {
      error_log("Invalid token: " . $token);
      throw new Exception("Invalid token", 401);
    }

    $res = $this->verifyAuthorizationHeader([
      'method' => $this->request->getMethod(),
      'url' => $this->request->getUri(),
      'body' => $this->body,
      'secret' => $this->secret,
      'authorizationHeader' => $data['authorizationHeader']
    ]);

    if (!$res) {
      throw new Exception("Authentication failed", 401);
    }
  }

  /**
   * Creates an authorization header based on the given parameters.
   *
   * @param array $params The parameters used to create the authorization header.
   * @return string The generated authorization header.
   */
  public function createAuthorizationHeader(array $params): string
  {
    $method = $params['method'];
    $url = $params['url'];
    $body = $params['body'] ?? null;
    $secret = $params['secret'];

    $timestamp = time();
    $body_sha256_or_algo = $body ? hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), false) : 'SHA256';
    $payload = "{$method}|{$url}|{$body_sha256_or_algo}|{$timestamp}";
    $mac = hash_hmac('sha256', $payload, $secret, true);
    $mac_base64 = base64_encode($mac);

    return "Bearer HMAC|{$body_sha256_or_algo}|{$timestamp}|{$mac_base64}";
  }

  /**
   * Verifies the authorization header.
   *
   * @param array $params The parameters for verification.
   * @return bool Returns true if the authorization header is valid, false otherwise.
   */
  public function verifyAuthorizationHeader(array $params): bool
  {
    $method = $params['method'];
    $url = $params['url'];
    $body = $params['body'] ?? null;
    $secret = $params['secret'];
    $authorizationHeader = $params['authorizationHeader'];

    // Split the authorization header
    $parts = explode('|', str_replace('Bearer HMAC|', '', $authorizationHeader));

    if (count($parts) !== 4) {
      return false; // Invalid authorization header format
    }

    [$receivedBodyHash, $receivedTimestamp, $receivedMac] = $parts;

    $timestamp = (int)$receivedTimestamp;
    $body_sha256_or_algo = $body ? hash('sha256', json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION), false) : 'SHA256';

    // Verify body hash
    if ($receivedBodyHash !== $body_sha256_or_algo) {
      return false;
    }

    // Verify timestamp
    if (abs(time() - $timestamp) > 60) {
      return false;
    }

    $payload = "{$method}|{$url}|{$body_sha256_or_algo}|{$timestamp}";
    $expectedMac = base64_encode(hash_hmac('sha256', $payload, $secret, true));

    // Compare the received MAC with the expected MAC
    return hash_equals($expectedMac, $receivedMac);
  }
}
