<?php
// This class requires installation of https://github.com/1ma/secp256k1-nostr-php
declare(strict_types=1);

/**
 * Summary of NostrEventKind
 */
enum NostrEventKind: int
{
  case Metadata = 0;
  case Text = 1;
  case RecommendRelay = 2;
  case Contacts = 3;
  case EncryptedDirectMessage = 4;
  case EventDeletion = 5;
  case Repost = 6;
  case Reaction = 7;
  case BadgeAward = 8;
  case ChannelCreation = 40;
  case ChannelMetadata = 41;
  case ChannelMessage = 42;
  case ChannelHideMessage = 43;
  case ChannelMuteUser = 44;
  case Blank = 255;
  case Report = 1984;
  case ZapRequest = 9734;
  case Zap = 9735;
  case RelayList = 10002;
  case ClientAuth = 22242;
  case HttpAuth = 27235;
  case ProfileBadge = 30008;
  case BadgeDefinition = 30009;
  case Article = 30023;
}

/**
 * Summary of NostrEvent
 */
class NostrEvent
{
  /**
   * Summary of getBlankEvent
   * @param NostrEventKind $kind
   * @return SignedNostrEvent
   */
  public function getBlankEvent(NostrEventKind $kind): SignedNostrEvent
  {
    return new SignedNostrEvent(
      '',
      '',
      '',
      $kind,
      '',
      [],
      0
    );
  }

  /**
   * Summary of finishEvent
   * @param SignedNostrEvent $event
   * @param string $privateKey
   * @return SignedNostrEvent
   */
  public function finishEvent(SignedNostrEvent $event, string $privateKey): SignedNostrEvent
  {
    $event->pubkey = $this->getPublicKey($privateKey);
    $event->id = $this->getEventHash($event);
    $event->sig = $this->getSignature($event, $privateKey);

    return $event;
  }

  /**
   * Summary of getPublicKey
   * @param string $privateKey
   * @return string
   */
  public function getPublicKey(string $privateKey): string
  {
    return secp256k1_nostr_derive_pubkey($privateKey);
  }

  /**
   * Summary of getEventHash
   * @param SignedNostrEvent $event
   * @return string
   */
  public function getEventHash(SignedNostrEvent $event): string
  {
    $serializedEvent = $this->serializeEvent($event);
    // Explicitly convert the serialized event to UTF-8
    $utf8SerializedEvent = mb_convert_encoding($serializedEvent, 'UTF-8');
    $eventHash = hash('sha256', $utf8SerializedEvent);
    return $eventHash;
  }

  /**
   * Summary of serializeEvent
   * @param SignedNostrEvent $event
   * @throws \Exception
   * @return string
   */
  public function serializeEvent(SignedNostrEvent $event): string
  {
    if (!$this->validateEvent($event)) {
      throw new \Exception("Can't serialize event with wrong or missing properties");
    }

    $eventData = [
      0,
      $event->pubkey,
      $event->created_at,
      $event->kind->value,
      $event->tags,
      $event->content
    ];

    return json_encode($eventData, JSON_UNESCAPED_SLASHES);
  }

  /**
   * Summary of validateEvent
   * @param SignedNostrEvent $event
   * @return bool
   */
  public function validateEvent(SignedNostrEvent $event): bool
  {
    // Check if pubkey is hex and 64 characters
    if (!ctype_xdigit($event->pubkey) || strlen($event->pubkey) != 64) {
      return false;
    }

    // Check if tags is an array and all its items are arrays of strings
    foreach ($event->tags as $tag) {
      if (!is_array($tag)) {
        return false;
      }
      foreach ($tag as $item) {
        if (!is_string($item)) {
          return false;
        }
      }
    }

    // All checks passed
    return true;
  }


  /**
   * Summary of verifySignature
   * @param SignedNostrEvent $event
   * @return bool
   */
  public function verifySignature(SignedNostrEvent $event): bool
  {
    // Hash event independently to verify signature
    $eventHash = $this->getEventHash($event);
    return secp256k1_nostr_verify($event->pubkey, $eventHash, $event->sig);
  }

  /**
   * Summary of getSignature
   * @param SignedNostrEvent $event
   * @param string $privateKey
   * @return string
   */
  public function getSignature(SignedNostrEvent $event, string $privateKey): string
  {
    $hash = $this->getEventHash($event);
    return secp256k1_nostr_sign($privateKey, $hash);
  }
}

class SignedNostrEvent
{
  /**
   * Summary of __construct
   * @param string $pubkey
   * @param string $id
   * @param string $sig
   * @param NostrEventKind $kind
   * @param string $content
   * @param array $tags
   * @param int $created_at
   */
  public function __construct(
    public string $pubkey,
    public string $id,
    public string $sig,
    public NostrEventKind $kind,
    public string $content,
    public array $tags,
    public int $created_at
  ) {
  }

  /**
   * Create a new SignedNostrEvent from an array.
   * @param array $data The array to convert.
   * @return SignedNostrEvent The new SignedNostrEvent object.
   * @throws Exception If a required key is missing from the array.
   */
  /*
  try {
    $signedNostrEvent = SignedNostrEvent::fromArray($array);
  } catch (Exception $e) {
      // Handle the exception...
  }
  */
  public static function fromArray(array $data): self
  {
    // Ensure all required keys are present in the array.
    $requiredKeys = ['pubkey', 'id', 'sig', 'kind', 'content', 'tags', 'created_at'];
    foreach ($requiredKeys as $key) {
      if (!array_key_exists($key, $data)) {
        throw new Exception("Key '$key' is required.");
      }
    }

    // Create the NostrEventKind object.
    // Modify this line as necessary based on the definition of NostrEventKind.
    $kind = NostrEventKind::from($data['kind']);

    // Create the SignedNostrEvent object.
    return new self(
      $data['pubkey'],
      $data['id'],
      $data['sig'],
      $kind,
      $data['content'],
      $data['tags'],
      $data['created_at']
    );
  }
}
