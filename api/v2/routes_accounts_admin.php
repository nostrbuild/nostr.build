<?php
/**
 * Proxied admin routes — User Management tool.
 *
 * Mounted at /api/v2/accounts/admin/users/* and reached only via the
 * account.nostr.build Worker proxy (HMAC + X-Accounts-Npub). Path mirror:
 *
 *   Browser → Worker:   /api/admin/users/{lookup,plan,extend,expiry,password-reset}
 *   Worker → PHP:       /api/v2/accounts/admin/users/{...}
 *
 * Security layers, outermost in:
 *   1. CSRF + session cookie verified by the Worker
 *   2. Worker-side admin gate (SessionDO snapshot accountLevel === 99) early-rejects
 *   3. HmacAuthMiddleware: only the Worker can hit these routes
 *   4. ProxiedAdminMiddleware: fresh DB read of acctlevel for X-Accounts-Npub
 *   5. Per-route input validation (npub regex, level enum, date range, days range)
 *   6. Per-route self-modify guard (target_npub !== admin_npub)
 *
 * Each successful mutation emits a `profile-changed` event via
 * WorkerEventsClient targeting the AFFECTED USER (not the admin) so their
 * other devices update. Password reset additionally emits `banned` to
 * force-logout the target user's other sessions.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/HmacAuthMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ProxiedAdminMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/WorkerEventsClient.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// ----- Helpers (local to this file; named to avoid clashing with route_*) -----

function aaJson(Response $response, array $data, int $status = 200): Response
{
  $response->getBody()->write(json_encode($data));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}

function aaError(Response $response, string $error, int $status = 400, array $extra = []): Response
{
  return aaJson($response, array_merge(['error' => $error], $extra), $status);
}

/**
 * Validate a target npub. Returns the trimmed npub or null on rejection.
 * Tighter than the cookie path's check because the admin tool is the
 * only legitimate caller and we want input errors surfaced cleanly.
 */
function aaValidNpub(?string $raw): ?string
{
  if (!is_string($raw)) return null;
  $npub = trim($raw);
  if ($npub === '' || strlen($npub) > 255) return null;
  // bech32 alphabet (lowercase letters + digits, minus the bech32-excluded
  // chars 1 b i o — but `npub1` is fixed prefix so just allow a..z 0..9).
  if (!preg_match('/^npub1[02-9ac-hj-np-z]+$/', $npub)) return null;
  return $npub;
}

/** Build the lookup response payload from an Account. Mirrors the field
 *  names the TS handler validates against. */
function aaUserSnapshot(Account $account): array
{
  $info = $account->getAccountInfo();
  return [
    'npub'               => $account->getNpub(),
    'userId'             => $account->getAccountNumericId(),
    'nym'                => $info['nym'] ?? null,
    'pfpUrl'             => $info['ppic'] ?? null,
    'acctlevel'          => (int) ($info['acctlevel'] ?? 0),
    'planStartDate'      => $info['plan_start_date'] ?? null,
    'planUntilDate'      => $info['plan_until_date'] ?? null,
    'subscriptionPeriod' => $info['subscription_period'] ?? null,
    'remainingDays'      => $account->getRemainingSubscriptionDays(),
    'isExpired'          => $account->isExpired(),
    'npubVerified'       => (bool) ($info['npub_verified'] ?? false),
    'allowNpubLogin'     => (bool) ($info['allow_npub_login'] ?? true),
    'accountFlags'       => $info['accflags'] ?? null,
    'banned'             => (bool) ($info['banned'] ?? false),
    'banReason'          => (string) ($info['ban_reason'] ?? ''),
    'createdAt'          => $info['created_at'] ?? null,
  ];
}

/** Emit a profile-changed event for the AFFECTED user (target_npub).
 *  Swallows failures — webhook hiccups must not fail the admin action. */
function aaEmitProfileChanged(?int $targetUserId, array $changed): void
{
  if ($targetUserId === null) return;
  try {
    (new WorkerEventsClient())->emitProfileChanged($targetUserId, null, $changed);
  } catch (\Throwable $e) {
    error_log('admin/users: emitProfileChanged failed: ' . $e->getMessage());
  }
}

// ----- Routes -----

$app->group('/accounts/admin/users', function (RouteCollectorProxy $group) {

  /**
   * GET /accounts/admin/users/lookup?npub=...
   * Fetch full snapshot for the admin's user-card display.
   */
  $group->get('/lookup', function (Request $request, Response $response) {
    global $link;
    $npub = aaValidNpub($request->getQueryParams()['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    return aaJson($response, aaUserSnapshot($account));
  });

  /**
   * POST /accounts/admin/users/plan
   * Body: { npub, level: 0..99, period: '1y'|'2y'|'3y' }
   * Sets the user's level + plan dates. Level 0 demotes to Free (clears plan dates).
   */
  $group->post('/plan', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub  = aaValidNpub($body['npub'] ?? null);
    $level = isset($body['level']) && is_int($body['level']) ? $body['level'] : null;
    $period = isset($body['period']) && is_string($body['period']) ? $body['period'] : null;

    if ($npub === null)   return aaError($response, 'invalid-npub', 400);
    if ($level === null || !in_array($level, [0, 1, 2, 3, 4, 5, 10, 89, 99], true)) {
      return aaError($response, 'invalid-level', 400);
    }
    if ($period === null || !in_array($period, ['1y', '2y', '3y'], true)) {
      return aaError($response, 'invalid-period', 400);
    }

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $result = $account->adminSetPlan($level, $period);
    } catch (\InvalidArgumentException $e) {
      return aaError($response, 'invalid-input', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
      error_log("admin/users/plan failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged($account->getAccountNumericId(), ['accountLevel', 'remainingDays']);
    return aaJson($response, ['ok' => true, 'planUntilDate' => $result['planUntilDate'], 'level' => $result['level']]);
  });

  /**
   * POST /accounts/admin/users/extend
   * Body: { npub, days: 1..3650 }
   * Adds N days to plan_until_date (from current expiry, or today if expired).
   */
  $group->post('/extend', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    $days = isset($body['days']) && is_int($body['days']) ? $body['days'] : null;

    if ($npub === null)  return aaError($response, 'invalid-npub', 400);
    if ($days === null || $days < 1 || $days > 3650) {
      return aaError($response, 'invalid-days', 400);
    }

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $newEnd = $account->adminExtendSubscription($days);
    } catch (\InvalidArgumentException $e) {
      return aaError($response, 'invalid-input', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
      error_log("admin/users/extend failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged($account->getAccountNumericId(), ['remainingDays']);
    return aaJson($response, ['ok' => true, 'planUntilDate' => $newEnd]);
  });

  /**
   * POST /accounts/admin/users/expiry
   * Body: { npub, date: 'YYYY-MM-DD' (today or later) }
   */
  $group->post('/expiry', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    $date = isset($body['date']) && is_string($body['date']) ? $body['date'] : null;

    if ($npub === null) return aaError($response, 'invalid-npub', 400);
    if ($date === null) return aaError($response, 'invalid-date', 400);

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $newEnd = $account->adminSetExpiryDate($date);
    } catch (\InvalidArgumentException $e) {
      return aaError($response, 'invalid-input', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
      error_log("admin/users/expiry failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged($account->getAccountNumericId(), ['remainingDays']);
    return aaJson($response, ['ok' => true, 'planUntilDate' => $newEnd]);
  });

  /**
   * POST /accounts/admin/users/verify-npub
   * Body: { npub, verified: bool }
   * Toggle the npub_verified marker. Usually set by NIP-05/DM verification —
   * this is the admin override for support cases.
   */
  $group->post('/verify-npub', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    // Strict boolean — no string coercion. Mirrors password-reset's killSessions.
    if (!isset($body['verified']) || !is_bool($body['verified'])) {
      return aaError($response, 'invalid-verified', 400);
    }
    $verified = $body['verified'];

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $account->adminSetNpubVerified($verified);
    } catch (\Throwable $e) {
      error_log("admin/users/verify-npub failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged($account->getAccountNumericId(), ['npubVerified']);
    return aaJson($response, ['ok' => true, 'npubVerified' => $verified]);
  });

  /**
   * POST /accounts/admin/users/allow-npub-login
   * Body: { npub, allow: bool }
   * Toggle allow_npub_login. When false, future npub-based logins are
   * blocked — existing sessions stay live (use password-reset with
   * killSessions to also kick them).
   */
  $group->post('/allow-npub-login', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    if (!isset($body['allow']) || !is_bool($body['allow'])) {
      return aaError($response, 'invalid-allow', 400);
    }
    $allow = $body['allow'];

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $account->adminSetAllowNpubLogin($allow);
    } catch (\Throwable $e) {
      error_log("admin/users/allow-npub-login failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged($account->getAccountNumericId(), ['allowNostrLogin']);
    return aaJson($response, ['ok' => true, 'allowNpubLogin' => $allow]);
  });

  /**
   * POST /accounts/admin/users/password-reset
   * Body: { npub, killSessions?: bool (default false) }
   * Generates a random password, persists hashes, returns plaintext ONCE.
   * Optionally force-logs-out the target user's other sessions —
   * defaults OFF because the typical case is "user forgot password,
   * admin shares the new one out-of-band, user keeps using their
   * already-authenticated phone." Switch ON for the
   * compromised-account / disgruntled-employee case.
   */
  $group->post('/password-reset', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    // Optional, default false. Accept actual booleans only — string
    // "false"/"true" coercion is too lenient for an action this destructive.
    $killSessions = isset($body['killSessions']) ? $body['killSessions'] === true : false;

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $plaintext = $account->adminResetPassword();
    } catch (\Throwable $e) {
      error_log("admin/users/password-reset failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    // Optional force-logout: clears every active session for the target
    // user. Done via the existing `banned` event arm; not a literal ban,
    // just reusing the broadcast-and-close machinery.
    $targetUserId = $account->getAccountNumericId();
    if ($killSessions && $targetUserId !== null) {
      try {
        (new WorkerEventsClient())->emitBanned($targetUserId);
      } catch (\Throwable $e) {
        error_log('admin/users/password-reset: emitBanned failed: ' . $e->getMessage());
      }
    }

    // NOTE: we deliberately do NOT log the plaintext. error_log of this
    // payload would defeat the entire "shown once" guarantee.
    return aaJson($response, [
      'ok' => true,
      'password' => $plaintext,
      'sessionsKilled' => $killSessions,
    ]);
  });

})
  ->add(new ProxiedAdminMiddleware())
  ->add(new HmacAuthMiddleware());
