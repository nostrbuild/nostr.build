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
    // Adopt an account.nostr.build session (signed __Secure-npub cookie) into
    // $_SESSION before we read it below - keeps the non-ported admin tools and
    // the account-holder upload gate working now that /login lives in the Worker.
    $this->bridgeEcosystemSession();
    $this->isLoggedIn = isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true;
    $this->userLevel = $this->isLoggedIn ? $_SESSION["acctlevel"] : null;
    $this->accFlags = $this->isLoggedIn && isset($_SESSION["accflags"]) ? $_SESSION["accflags"] : null;
    $this->planExpired = $this->isLoggedIn && isset($_SESSION["planexpired"]) ? $_SESSION["planexpired"] : true;
    // Ecosystem cookie emission is now owned SOLELY by account.nostr.build (the
    // Worker refreshes __Secure-npub/user_level/plan_expired on its own authed
    // navigations). PHP deliberately no longer re-mints them: that makes the
    // Worker's issued TTL the HARD ceiling for a bridged session, so a stolen
    // __Secure-npub cannot be slid forward indefinitely from the PHP side. The
    // setSecureSignedCookie() method is kept intact below, just not called here.
    // if ($this->isLoggedIn && !empty($_SESSION["usernpub"])) {
    //   $userNpub = $_SESSION["usernpub"];
    //   $this->setSecureSignedCookie('npub', $userNpub, 86400); // 24 hours
    //   $this->setSecureSignedCookie('user_level', $this->userLevel, 86400); // 24 hours
    //   $this->setSecureSignedCookie('plan_expired', ($this->planExpired ? 'true' : 'false'), 86400); // 24 hours
    // }
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

  function isPlanExpired()
  {
    return $this->planExpired;
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
    int $expire = 15552000, // 180 days
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

  /**
   * Verify one of the ecosystem `__Secure-*` cookies. Both this app
   * (setSecureSignedCookie above) and the account.nostr.build Worker emit the
   * exact same wire format: `value.exp.base64(hmac_sha256("value.exp", secret))`,
   * URL-encoded (PHP url-decodes $_COOKIE for us). On success writes the raw
   * value (e.g. the npub) into $out and returns true. Constant-time compare,
   * rejects expired cookies. Identity only - privilege is re-read from the DB.
   */
  private function verifyEcosystemCookie(string $name, ?string &$out): bool
  {
    $raw = $_COOKIE['__Secure-' . $name] ?? '';
    if ($raw === '') {
      return false;
    }
    $secret = $_SERVER['NMS_COOKIE_SECRET_KEY'] ?? '';
    if ($secret === '') {
      return false;
    }
    $parts = explode('.', $raw);
    if (count($parts) < 3) {
      return false;
    }
    $sig = array_pop($parts);
    $exp = array_pop($parts);
    $value = implode('.', $parts); // the npub carries no dots
    if ($value === '' || !ctype_digit($exp) || (int) $exp < time()) {
      return false;
    }
    $expected = base64_encode(hash_hmac('sha256', $value . '.' . $exp, $secret, true));
    if (!hash_equals($expected, $sig)) {
      return false;
    }
    $out = $value;
    return true;
  }

  /**
   * Bridge an account.nostr.build session into this PHP app. Runs first in the
   * constructor, before $_SESSION is read. When a request carries a valid
   * `__Secure-npub` cookie but no matching PHP session, load the account by npub
   * (Account::fetchAccountData re-reads acctlevel/accflags from the DB - the
   * cookie is trusted for IDENTITY only) and mark the session logged-in, so the
   * non-ported admin tools and the account-holder upload gate keep working after
   * /login moved to the Worker. Once that cookie is gone/expired, a session WE
   * created is torn down (continuous re-check). Native PHP-login sessions (no
   * `nb_bridged` marker) are never touched.
   *
   * SECURITY MODEL - accepted, documented residuals. These are properties of the
   * shared .nostr.build ecosystem cookie (analytics / n-photos trust __Secure-npub
   * the same way); they are NOT specific to this bridge and do not grant an
   * attacker more privilege (acctlevel/accflags are ALWAYS re-read from the DB for
   * the cookie's npub, never taken from a cookie):
   *  - Identity, not proof-of-possession: the HMAC proves a legitimate emitter
   *    minted the cookie, not that the presenting browser owns that identity. An
   *    attacker who can plant a .nostr.build cookie (sibling XSS, subdomain
   *    takeover, or a Secure-cookie-setting MitM) can force a victim into the
   *    attacker's OWN account ("forced login"). Real fix is Worker-side: bind the
   *    signature to the session (sign npub|sessionId) and require the matching,
   *    server-revocable session before trusting it.
   *  - Non-revocable within TTL: verification is stateless, so a leaked cookie is
   *    valid until its exp. Bounded because PHP no longer slides the TTL (see the
   *    commented-out emission in __construct) - the Worker's issued exp is the hard
   *    ceiling, and Worker logout clears the triple in the user's own browser.
   *  - Single shared secret: NMS_COOKIE_SECRET_KEY has no current+previous verify
   *    window, so rotating it invalidates every live ecosystem cookie at once. Fix
   *    is a multi-secret verify list across the Worker and verifyEcosystemCookie.
   */
  private function bridgeEcosystemSession(): void
  {
    // Server-to-server (HMAC-proxied) calls carry identity in headers, not
    // cookies, and must never spin up a PHP session - skip them entirely.
    if (!empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['HTTP_X_ACCOUNTS_NPUB'])) {
      return;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
      return;
    }

    $npub = null;
    $valid = $this->verifyEcosystemCookie('npub', $npub);

    // Cookie gone/expired: tear down a session WE created; leave native ones be.
    if (!$valid) {
      if (!empty($_SESSION['nb_bridged'])) {
        $_SESSION = [];
        // session_destroy() emits the delete-cookie header - only safe pre-output.
        if (!headers_sent()) {
          session_destroy();
        }
      }
      return;
    }

    // Already logged in as this same identity - nothing to do.
    if (!empty($_SESSION['loggedin']) && ($_SESSION['usernpub'] ?? '') === $npub) {
      return;
    }

    // Never override a native PHP-login session (one the bridge did not create)
    // with a divergent cookie identity. npub is not stable, so a logged-in user
    // can legitimately carry a __Secure-npub for a different npub; only sessions
    // the bridge itself created (nb_bridged) are ours to re-point or clear below.
    if (!empty($_SESSION['loggedin']) && empty($_SESSION['nb_bridged'])) {
      return;
    }

    // Hydrate from the signed identity. Account::__construct -> fetchAccountData
    // -> setSessionParameters populates $_SESSION (level, flags, expiry) straight
    // from the DB, so privilege is never taken from the cookie.
    global $link;
    if (!($link instanceof mysqli)) {
      return;
    }
    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
    try {
      $account = new Account($npub, $link);
    } catch (\Throwable $e) {
      // The bridge is the FIRST thing the Permission constructor runs, so an
      // uncaught Account()/DB error here would 500 every cookie-carrying page
      // from the auth layer. Fail closed: log and stay anonymous this request.
      error_log('[session-bridge] account load failed: ' . $e->getMessage());
      return;
    }
    if (empty($account->getAccount()['id'])) {
      // npub no longer maps to an account - drop whatever setSessionParameters
      // wrote and stay anonymous. The native-session guard above guarantees the
      // session here is anonymous or already-bridged, never a native password
      // login, so this clear can't wipe a session we did not create.
      $_SESSION = [];
      return;
    }
    $_SESSION['loggedin'] = true;
    $_SESSION['nb_bridged'] = true;
    // Defend against session fixation on the anon -> authed elevation. Skip when
    // output already started (the new id cookie can't be sent); the hydrated
    // $_SESSION still stands either way.
    if (!headers_sent()) {
      session_regenerate_id(true);
    }
  }
}
