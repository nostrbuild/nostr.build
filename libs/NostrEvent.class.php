<?php
// This class requires installation of https://github.com/1ma/secp256k1-nostr-php
declare(strict_types=1);

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

// to call this enum run NostrEventKind::HttpAuth

/**
 * Summary of NostrEvent
 */
class NostrEvent
{
  /**
   * Summary of getBlankEvent
   * @param NostrEventKind $kind
   * @return array
   */
  public function getBlankEvent(NostrEventKind $kind): array
  {
    return [
      "kind" => $kind,
      "content" => "",
      "tags" => array(),
      "created_at" => 0
    ];
  }

  /**
   * Summary of finishEvent
   * @param array $event
   * @param string $privateKey
   * @return array
   */
  public function finishEvent(array $event, string $privateKey): array
  {
    $event['pubkey'] = $this->getPublicKey($privateKey);
    $event['id'] = $this->getEventHash($event);
    $event['sig'] = $this->getSignature($event, $privateKey);

    return $event;
  }

  /**
   * Summary of getPublicKey
   * @param string $privateKey
   * @return string
   */
  public function getPublicKey(string $privateKey): string
  {
    // use library function to derive public key
    return secp256k1_nostr_derive_pubkey($privateKey);
  }

  /**
   * Summary of getEventHash
   * @param array $event
   * @return string
   */
  public function getEventHash(array $event): string
  {
    $serializedEvent = $this->serializeEvent($event);
    $eventHash = hash("sha256", $serializedEvent);

    return $eventHash;
  }

  /**
   * Summary of serializeEvent
   * @param array $event
   * @throws \Exception
   * @return bool|string
   */
  public function serializeEvent(array $event): bool|string
  {
    if (!$this->validateEvent($event)) {
      throw new Exception("Can't serialize event with wrong or missing properties");
    }

    // We create an indexed array instead of an associative array 
    // to preserve the order of the properties
    $eventData = [
      0,
      $event['pubkey'],
      $event['created_at'],
      $event['kind'],
      $event['tags'],
      $event['content']
    ];

    return json_encode($eventData);
  }

  /**
   * Summary of validateEvent
   * @param array $event
   * @return bool
   */
  public function validateEvent(array $event): bool
  {
    // Checking if all properties exist
    if (
      !isset($event['kind']) ||
      !isset($event['content']) ||
      !isset($event['created_at']) ||
      !isset($event['pubkey']) ||
      !isset($event['tags']) ||
      !isset($event['id'])
    ) {
      return false;
    }

    // Checking types of properties
    if (
      !is_int($event['kind']) ||
      !is_string($event['content']) ||
      !is_int($event['created_at']) ||
      !is_string($event['pubkey'])
    ) {
      return false;
    }

    // Check if pubkey is hex and 64 characters
    if (!ctype_xdigit($event['pubkey']) || strlen($event['pubkey']) != 64) {
      return false;
    }

    // Check if tags is an array and all its items are arrays of strings
    if (!is_array($event['tags'])) {
      return false;
    }

    foreach ($event['tags'] as $tag) {
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
   * @param array $event
   * @return bool
   */
  public function verifySignature(array $event): bool
  {
    // Use library function to verify the signature
    return secp256k1_nostr_verify($event['pubkey'], $event['id'], $event['sig']);
  }

  /**
   * Summary of getSignature
   * @param array $event
   * @param string $privateKey
   * @return string
   */
  public function getSignature(array $event, string $privateKey): string
  {
    // We will assume that getEventHash is unchanged and returns a hex-encoded hash string
    $hash = $this->getEventHash($event);
    // Use library function to sign the hash
    return secp256k1_nostr_sign($privateKey, $hash);
  }
}
