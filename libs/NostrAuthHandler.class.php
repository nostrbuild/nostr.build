<?php
require_once __DIR__ . '/NostrEvent.class.php';
require_once __DIR__ . '/Bech32.class.php';

/**
 * Summary of NostrAuthHandler
 */
class NostrAuthHandler
{
  /**
   * Summary of event
   * @var 
   */
  private $event;
  /**
   * Summary of headers
   * @var 
   */
  private $headers;
  /**
   * Summary of request
   * @var 
   */
  private $request;

  /**
   * Summary of __construct
   * @param mixed $headers
   * @param mixed $request
   */
  public function __construct($headers, $request)
  {
    $this->headers = $headers;
    $this->request = $request;
  }

  /**
   * Summary of handle
   * @throws \Exception
   * @return void
   */
  public function handle()
  {
    // check if 'Authorization' header exists
    if (!isset($this->headers['Authorization'][0])) {
      throw new Exception("Missing Authorization header");
    }

    $auth = trim($this->headers['Authorization'][0]);

    // check if auth scheme is Nostr
    if (strpos($auth, "Nostr") !== 0) {
      throw new Exception("Invalid auth scheme");
    }

    $token = substr($auth, 6);
    $bToken = base64_decode($token);

    if (empty($token) || empty($bToken) || $bToken[0] != '{') {
      error_log("Invalid token: " . $token);
      throw new Exception("Invalid token");
    }

    try {
      $this->event = SignedNostrEvent::fromArray(json_decode($bToken, true));
    } catch (Exception $e) {
      error_log("Invalid event: " . $e);
      throw new Exception("Invalid nostr event");
    }

    // Initialize Event class
    $eventHandler = new NostrEvent();

    // Perform the checks here

    // 0. The signature MUST be valid.
    if (!$eventHandler->verifySignature($this->event)) {
      throw new Exception("Invalid signature");
    }

    // 1. The kind MUST be 27235.
    if ($this->event->kind != NostrEventKind::HttpAuth) {
      throw new Exception("Invalid kind");
    }

    // 2. The created_at MUST be within a reasonable time window (suggestion 60 seconds).
    if (abs(time() - $this->event->created_at) > 60) {
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


  /**
   * Summary of findTagValue
   * @param mixed $tag
   * @return mixed|null
   */
  private function findTagValue($tag)
  {
    foreach ($this->event->tags as $eventTag) {
      if ($eventTag[0] == $tag) {
        return $eventTag[1];
      }
    }
    return null;
  }

  /**
   * Summary of getPubKey
   * @return mixed|null
   */
  public function getPubKey()
  {
    return $this->event->pubkey ?? null;
  }

  /**
   * Summary of getNpub
   * @return string
   */
  public function getNpub()
  {
    $bech32 = new Bech32();
    return $bech32->convertHexToBech32('npub', $this->getPubKey());
  }
}
