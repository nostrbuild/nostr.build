<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/functions/session.php';

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


  function __construct()
  {
    if (!isset($_SESSION) || session_status() === PHP_SESSION_NONE)
      session_start();
    $this->isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
    $this->userLevel = $this->isLoggedIn ? $_SESSION["acctlevel"] : null;
    $this->accFlags = $this->isLoggedIn && isset($_SESSION["accflags"]) ? $_SESSION["accflags"] : null;
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
  function hasPrivilege($privilege)
  {
    return $this->isLoggedIn && isset($this->accFlags->{$privilege}) && $this->accFlags->{$privilege} === true;
  }
}
