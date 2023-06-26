<?php

/**
 * NIP-98 Implementation for PHP (untested, don't use yet)
 * Uses: https://github.com/swentel/nostr-php
 */

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

class NostrAuthHandler
{
  private $event;
  private $headers;
  private $request;

  public function __construct($headers, $request)
  {
    $this->headers = $headers;
    $this->request = $request;
  }

  public function handle()
  {
    // check if 'Authorization' header exists
    if (!isset($this->headers['Authorization'])) {
      throw new Exception("Missing Authorization header");
    }

    $auth = trim($this->headers['Authorization']);

    // check if auth scheme is Nostr
    if (strpos($auth, "Nostr") !== 0) {
      throw new Exception("Invalid auth scheme");
    }

    $token = substr($auth, 6);
    $bToken = base64_decode($token);

    if (empty($token) || empty($bToken) || $bToken[0] != '{') {
      throw new Exception("Invalid token");
    }

    $this->event = json_decode($bToken, true);

    if (empty($this->event)) {
      throw new Exception("Invalid nostr event");
    }

    // Perform the checks here

    // 0. The signature MUST be valid.
    // TODO: Must be implemented before use!

    // 1. The kind MUST be 27235.
    if ($this->event['kind'] != 27235) {
      throw new Exception("Invalid kind");
    }

    // 2. The created_at MUST be within a reasonable time window (suggestion 60 seconds).
    if (abs(time() - $this->event['created_at']) > 60) {
      throw new Exception("Timestamp out of range");
    }

    // 3. The u tag MUST be exactly the same as the absolute request URL (including query parameters).
    $url = $this->findTagValue('u');
    if ($url != $this->request->getUri()) {
      throw new Exception("Invalid url tag");
    }

    // 4. The method tag MUST be the same HTTP method used for the requested resource.
    $method = $this->findTagValue('method');
    if ($method != $this->request->getMethod()) {
      throw new Exception("Invalid method tag");
    }
  }

  private function findTagValue($tag)
  {
    foreach ($this->event['tags'] as $eventTag) {
      if ($eventTag[0] == $tag) {
        return $eventTag[1];
      }
    }
    return null;
  }

  public function getPubKey()
  {
    // TODO: Implement this
  }
}
