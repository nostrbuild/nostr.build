<?php
// This class requires installation of https://github.com/1ma/secp256k1-nostr-php
declare(strict_types=1);

class NostrEventKind
{
  const Metadata = 0;
  const Text = 1;
  const RecommendRelay = 2;
  const Contacts = 3;
  const EncryptedDirectMessage = 4;
  const EventDeletion = 5;
  const Repost = 6;
  const Reaction = 7;
  const BadgeAward = 8;
  const ChannelCreation = 40;
  const ChannelMetadata = 41;
  const ChannelMessage = 42;
  const ChannelHideMessage = 43;
  const ChannelMuteUser = 44;
  const Blank = 255;
  const Report = 1984;
  const ZapRequest = 9734;
  const Zap = 9735;
  const RelayList = 10002;
  const ClientAuth = 22242;
  const HttpAuth = 27235;
  const ProfileBadge = 30008;
  const BadgeDefinition = 30009;
  const Article = 30023;
}


/**
 * Summary of NostrEvent
 */
class NostrEvent
{
  /**
   * Summary of getBlankEvent
   * @param mixed $kind
   * @return array
   */
  public function getBlankEvent($kind)
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
   * @param mixed $event
   * @param mixed $privateKey
   * @return mixed
   */
  public function finishEvent($event, $privateKey)
  {
    $event['pubkey'] = $this->getPublicKey($privateKey);
    $event['id'] = $this->getEventHash($event);
    $event['sig'] = $this->getSignature($event, $privateKey);

    return $event;
  }

  /**
   * Summary of getPublicKey
   * @param mixed $privateKey
   * @return mixed
   */
  public function getPublicKey($privateKey)
  {
    // use library function to derive public key
    return secp256k1_nostr_derive_pubkey($privateKey);
  }

  /**
   * Summary of getEventHash
   * @param mixed $event
   * @return string
   */
  public function getEventHash($event)
  {
    $serializedEvent = $this->serializeEvent($event);
    $eventHash = hash("sha256", $serializedEvent);

    return $eventHash;
  }

  /**
   * Summary of serializeEvent
   * @param mixed $event
   * @throws \Exception
   * @return bool|string
   */
  public function serializeEvent($event)
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
   * @param mixed $event
   * @return bool
   */
  public function validateEvent($event)
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
   * @param mixed $event
   * @return mixed
   */
  public function verifySignature($event)
  {
    // Use library function to verify the signature
    return secp256k1_nostr_verify($event['pubkey'], $event['id'], $event['sig']);
  }

  /**
   * Summary of getSignature
   * @param mixed $event
   * @param mixed $privateKey
   * @return mixed
   */
  public function getSignature($event, $privateKey)
  {
    // We will assume that getEventHash is unchanged and returns a hex-encoded hash string
    $hash = $this->getEventHash($event);
    // Use library function to sign the hash
    return secp256k1_nostr_sign($privateKey, $hash);
  }
}
