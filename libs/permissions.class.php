<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';

/**
 * Usage:
 * $perm = new Permission();
 *
 * if ($perm->validateLoggedin()) {
 *     if ($perm->validatePermissionsLevelEqual(1)) {
 *         // Code for users with level 1 permissions
 *     } elseif ($perm->validatePermissionsLevelEqual(2)) {
 *         // Code for users with level 2 permissions
 *     } else {
 *         // Code for users with other permission levels
 *     }
 * } else {
 *     // Code for users who are not logged in
 * }
 */
class Permission
{
  private $userLevel;
  private $isLoggedIn;
  private $accFlags;
  private $planExpired;


  function __construct()
  {
    #if (!isset($_SESSION) || session_status() === PHP_SESSION_NONE)
    #  session_start(); # Done in php.ini
    $this->isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
    $this->userLevel = $this->isLoggedIn ? $_SESSION["acctlevel"] : null;
    $this->accFlags = $this->isLoggedIn && isset($_SESSION["accflags"]) ? $_SESSION["accflags"] : null;
    $this->planExpired = $this->isLoggedIn && isset($_SESSION["planexpired"]) ? $_SESSION["planexpired"] : null;
    if ($this->isLoggedIn && !empty($_SESSION["usernpub"])) {
      // Set or update the cookie to expire in 1 hour
      $userNpub = $_SESSION["usernpub"];
      $this->setSecureSignedCookie('npub', $userNpub, 3600);
    }
  }

  function validateLoggedin()
  {
    return $this->isLoggedIn;
  }

  function validatePermissionsLevelEqual($requiredLevel)
  {
    return $this->isLoggedIn && $this->userLevel == $requiredLevel;
  }

  function validatePermissionsLevelAny(...$requiredLevels)
  {
    return $this->isLoggedIn && in_array($this->userLevel, $requiredLevels);
  }

  function validatePermissionsLevelMoreThanOrEqual($requiredLevel)
  {
    return $this->isLoggedIn && $this->userLevel >= $requiredLevel;
  }

  function validatePermissionsLevelLessThanOrEqual($requiredLevel)
  {
    return $this->isLoggedIn && $this->userLevel <= $requiredLevel;
  }

  function getUserLevel()
  {
    return $this->userLevel;
  }

  function isGuest()
  {
    return !$this->isLoggedIn;
  }

  function isAdmin()
  {
    return $this->validatePermissionsLevelEqual(99);
  }

  /**
   * JSON:
   * {
   *  "canModerate": true,
   *  "canViewStats": false,
   *  "canManageUsers": false,
   *  "canEditContent": true
   * }
   * 
   * $perm = new Permission();
   *
   *  if ($perm->hasPrivilege('canModerate')) {
   *      // User has moderation privileges
   *  } else {
   *      // User doesn't have moderation privileges
   *  }
   * 
   * To grant Moderator permissions, use the following SQL query (for now)
   * UPDATE users SET accflags = '{"canModerate": true, "canViewStats": false, "canManageUsers": false, "canEditContent": true}' WHERE usernpub = '<npub>';
   */
  function hasPrivilege($privilege): bool
  {
    return $this->isLoggedIn && isset($this->accFlags[$privilege]) && $this->accFlags[$privilege] === true;
  }

  private function setSecureSignedCookie(
    string $name,
    string $value,
    int $expire = 3600,
    string $path = '/',
    string $domain = '',
    bool $secure = true,
    bool $httponly = false
  ): void {
    $name = $secure ? '__Secure-' . $name : $name;
    $secret = $_SERVER['NMS_COOKIE_SECRET_KEY'];
    $expireTime = time() + $expire;
    $domain = empty($domain) ? '.' . /*$_SERVER['HTTP_HOST']*/ 'nostr.build' : $domain;
    // Sign the value with the secret key HMAC SHA256
    $value .= '.' . strval($expireTime);
    $sign = hash_hmac('sha256', $value, $secret, true);
    $value .= '.' . base64_encode($sign);
    setcookie($name, $value, $expireTime, $path, $domain, $secure, $httponly);
  }
}
