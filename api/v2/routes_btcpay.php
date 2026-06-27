<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once __DIR__ . '/helper_functions.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/HmacAuthMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/Promotions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Bech32.class.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Worker-facing internal plans endpoints (the account.nostr.build Worker calls
// these during the dual-run migration). HMAC-authed via NB_HMAC_SECRETS — the
// SAME shared secret the PHP->Worker events bridge uses — so only the Worker can
// reach them. Full paths: POST /api/v2/internal/plans/{set-plan,signup},
// GET /api/v2/internal/plans/{referrer,promotions}.
$app->group('/internal/plans', function (RouteCollectorProxy $group) {
  // Dumb plan mutation. The account Worker is the AUTHORITATIVE settlement
  // source: its PaymentWorkflow receives the BTCPay webhook, classifies on the
  // real paid BTC amount (paid-in-full = greenlight), and holds the verified
  // order facts (uuid/plan/period) in its own ledger. So PHP does NOT re-read
  // the invoice, re-check the amount, or resolve identity from metadata here -
  // it just applies the plan to the stable uuid. Idempotent (setPlan
  // short-circuits an already-applied plan). This replaces /activate +
  // fulfillInvoiceById for the in-app flow; keep PHP a dumb API.
  $group->post('/set-plan', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $uuid = is_array($data) ? trim((string)($data['uuid'] ?? '')) : '';
    $plan = is_array($data) ? (int)($data['plan'] ?? 0) : 0;
    $period = is_array($data) ? (string)($data['period'] ?? '1y') : '1y';
    $new = is_array($data) && !empty($data['new']);
    // Downgrade-on-renew: the Worker computed the exact new expiry (purchased
    // term + converted bonus days). setPlan stores it verbatim AFTER re-checking
    // the invariants. Absent for renewal/upgrade/signup (PHP computes the date).
    $planUntilOverride = is_array($data) && isset($data['planUntilOverride'])
      ? trim((string)$data['planUntilOverride'])
      : null;
    if ($planUntilOverride === '') {
      $planUntilOverride = null;
    }
    if ($uuid === '' || $plan <= 0) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'uuid and plan required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $account = Account::fromUuid($uuid, $link);
      if ($account === null) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'no-such-account']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      // setPlan reports whether it actually applied; relay it (+ the resulting
      // level/expiry) so the Worker can park a PAID-but-not-applied order for an
      // admin instead of silently marking it active.
      $status = $account->setPlan($plan, $period, $new, $planUntilOverride);
      $acct = $account->getAccount();
      $response->getBody()->write(json_encode([
        'ok' => true,
        'status' => $status,
        'npub' => $account->getNpub(),
        'level' => (int)($acct['acctlevel'] ?? 0),
        'planUntil' => $acct['plan_until_date'] ?? null,
      ]));
      return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/plans/set-plan error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'set-plan failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Create a new (level-0) account during in-app signup. The Worker proves npub
  // ownership in-app FIRST (DM one-time code OR NIP-07 signature) and only then
  // calls this with npubVerified=1, so the verified flag is trustworthy. Mirrors
  // plans/index.php's createAccount step.
  $group->post('/signup', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $npub = is_array($data) ? trim((string)($data['npub'] ?? '')) : '';
    $password = is_array($data) ? (string)($data['password'] ?? '') : '';
    $npubVerified = is_array($data) && !empty($data['npubVerified']) ? 1 : 0;

    $bech32 = new Bech32();
    if (!$bech32->isValidNpub1Address($npub)) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'invalid-npub']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    if (strlen($password) < 6) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'weak-password']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
      $account = new Account($npub, $link);
      if ($account->accountExists()) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'account-exists']));
        return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
      }
      // level 0, allow_npub_login=1 (new signups always enable Nostr login).
      $account->createAccount($password, 0, $npubVerified, 1);
      $uuid = $account->getAccountUuid();
      // Record clickwrap acceptance of the Terms + Privacy (version owned by the
      // Worker, which is where the documents live). Non-fatal: a missing column
      // before the migration is applied must never block account creation.
      $legalVersion = is_array($data) ? trim((string)($data['legalVersion'] ?? '')) : '';
      if ($legalVersion !== '') {
        try {
          $account->recordLegalAcceptance($legalVersion);
        } catch (\Throwable $e) {
          error_log('internal/plans/signup legal-acceptance record failed: ' . $e->getMessage());
        }
      }
      $response->getBody()->write(json_encode(['ok' => true, 'uuid' => $uuid, 'npub' => $npub]));
      return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (DuplicateUserException $e) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'account-exists']));
      return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/plans/signup error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'signup-failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // POST /api/v2/internal/plans/email-signup — create an npub-less (email)
  // account. The Worker has already proven inbox control (single-use signup
  // magic-link) before calling this, so the email is created already verified.
  // Mirrors /signup but keyed on email + name instead of an npub.
  $group->post('/email-signup', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $email = is_array($data) ? strtolower(trim((string)($data['email'] ?? ''))) : '';
    $password = is_array($data) ? (string)($data['password'] ?? '') : '';
    $name = is_array($data) ? trim((string)($data['name'] ?? '')) : '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'invalid-email']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    if (strlen($password) < 6) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'weak-password']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    try {
      $account = new Account('', $link);
      $account->createEmailAccount($email, $password, $name !== '' ? $name : null, 0);
      $uuid = $account->getAccountUuid();
      // Record clickwrap acceptance (same non-fatal handling as /signup).
      $legalVersion = is_array($data) ? trim((string)($data['legalVersion'] ?? '')) : '';
      if ($legalVersion !== '') {
        try {
          $account->recordLegalAcceptance($legalVersion);
        } catch (\Throwable $e) {
          error_log('internal/plans/email-signup legal-acceptance record failed: ' . $e->getMessage());
        }
      }
      $response->getBody()->write(json_encode(['ok' => true, 'uuid' => $uuid]));
      return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (DuplicateEmailException $e) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'email-exists']));
      return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/plans/email-signup error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'signup-failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Resolve a referral code to the referrer's public identity (npub + nym + pfp)
  // so the in-app /plans page can show a "Referred by" card, exactly like the
  // legacy plans/index.php did. Returns 404 for an unknown/invalid code.
  $group->get('/referrer', function (Request $request, Response $response) {
    global $link;
    $code = trim((string)($request->getQueryParams()['code'] ?? ''));
    if ($code === '' || !preg_match('/^[A-F0-9]{4}-[A-F0-9]{4}-[A-F0-9]{4}$/', $code)) {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'invalid-code']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      // Same validity gate the legacy page used (valid level, not expired).
      $npub = findNpubByReferralCode($link, $code);
      if (empty($npub)) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'not-found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      $acct = (new Account($npub, $link))->getAccount();
      // `level` lets the Worker apply the signup referral split itself (only
      // levels 1/2/10 earn) without PHP owning that credit logic - keep PHP dumb.
      // `uuid` is the stable ledger key the Worker credits the referrer bonus to
      // (npub is mutable); server-to-server only — the public /plans card drops it.
      $response->getBody()->write(json_encode([
        'ok' => true,
        'uuid' => $acct['uuid_id'] ?? null,
        'npub' => $npub,
        'nym' => $acct['nym'] ?? null,
        'ppic' => $acct['ppic'] ?? null,
        'level' => (int)($acct['acctlevel'] ?? 0),
      ]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/plans/referrer error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'lookup-failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Active promotions (perPlan + global) for the in-app checkout display. The
  // Worker applies the discount math in TS; PHP stays the data source.
  $group->get('/promotions', function (Request $request, Response $response) {
    global $link;
    try {
      $promotions = new Promotions($link);
      $all = $promotions->getAllCurrentPromotions();
      $rows = array_merge($all['perPlan'] ?? [], $all['global'] ?? []);
      $response->getBody()->write(json_encode(array_values($rows)));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/plans/promotions error: ' . $e->getMessage());
      $response->getBody()->write(json_encode([]));
      return $response->withHeader('Content-Type', 'application/json');
    }
  });
})->add(new HmacAuthMiddleware());

// Worker-facing internal account-lifecycle endpoints (HMAC-authed, same shared
// NB_HMAC_SECRETS as /internal/plans). Account deletion is orchestrated by the
// Worker; PHP stays a dumb setter that records the pending state + re-checks the
// expired invariant. Full paths: POST /api/v2/internal/account/request-deletion,
// POST /api/v2/internal/account/cancel-deletion.
$app->group('/internal/account', function (RouteCollectorProxy $group) {
  // Mark an EXPIRED account for deletion (30-day reversible window). The Worker
  // has already validated eligibility + the user's re-auth/typed-phrase; PHP
  // re-checks isExpired() (defence in depth) and is idempotent (a repeat keeps
  // the original deadline). 'rejected-not-expired' => the Worker surfaces an
  // error instead of pretending it scheduled.
  $group->post('/request-deletion', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $uuid = is_array($data) ? trim((string)($data['uuid'] ?? '')) : '';
    // Re-auth proof: the user's current password. The Worker has already checked
    // the session + typed phrase; PHP verifies the password against the stored
    // hash (defence in depth) before scheduling an irreversible deletion.
    $password = is_array($data) ? (string)($data['password'] ?? '') : '';
    if ($uuid === '') {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'uuid required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $account = Account::fromUuid($uuid, $link);
      if ($account === null) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'no-such-account']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      if ($password === '' || !$account->verifyPassword($password)) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'bad-password']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
      }
      $status = $account->requestDeletion();
      $deleteAfter = $account->getDeletionDeleteAfter();
      $response->getBody()->write(json_encode([
        'ok' => true,
        'status' => $status, // 'pending' | 'noop-pending' | 'rejected-not-expired'
        'deletionStatus' => $account->getDeletionStatus(),
        'deleteAfter' => $deleteAfter !== null ? strtotime($deleteAfter) : null,
      ]));
      return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/request-deletion error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'request-deletion failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Same as /request-deletion but WITHOUT a password — the Worker's Nostr re-auth
  // path. The Worker has already proven cryptographically (a fresh NIP-07
  // signature or a one-time DM code, the SAME proof the npub-verify flow uses)
  // that the session owner controls this account's npub, AND that the account has
  // nostr login enabled — so the fresh key proof REPLACES the password as the
  // re-auth factor. PHP still re-checks isExpired() inside requestDeletion() and
  // stays idempotent (a repeat keeps the original deadline). Trust rests on the
  // shared service HMAC (only the Worker can reach this), exactly like
  // /finalize-deletion and /dashboard/npub/mark-verified.
  $group->post('/request-deletion-verified', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $uuid = is_array($data) ? trim((string)($data['uuid'] ?? '')) : '';
    if ($uuid === '') {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'uuid required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $account = Account::fromUuid($uuid, $link);
      if ($account === null) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'no-such-account']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      $status = $account->requestDeletion();
      $deleteAfter = $account->getDeletionDeleteAfter();
      $response->getBody()->write(json_encode([
        'ok' => true,
        'status' => $status, // 'pending' | 'noop-pending' | 'rejected-not-expired'
        'deletionStatus' => $account->getDeletionStatus(),
        'deleteAfter' => $deleteAfter !== null ? strtotime($deleteAfter) : null,
      ]));
      return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/request-deletion-verified error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'request-deletion failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // The Worker's DeletionWorkflow wake-check: it sleeps the 30-day window, then
  // reads this before doing anything. 'pending' + due => proceed; anything else
  // (renewed/admin-cancelled, or not yet due) => the workflow exits without
  // touching media. This replaces the old cron work-list (the workflow owns the
  // timer now).
  $group->get('/deletion-state', function (Request $request, Response $response) {
    global $link;
    $uuid = trim((string)($request->getQueryParams()['uuid'] ?? ''));
    if ($uuid === '') {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'uuid required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $account = Account::fromUuid($uuid, $link);
      if ($account === null) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'no-such-account']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      $deleteAfter = $account->getDeletionDeleteAfter();
      $deleteAfterTs = $deleteAfter !== null ? strtotime($deleteAfter) : null;
      // Self-service ('pending') keeps the expired-only safety net: NEVER report
      // a now-active account as due (a paid reactivation clears the flag in
      // setPlan, but isExpired() is the irreversible-step guard in case any
      // cancel hook is missed — the Worker must not wipe a paying customer).
      // Admin terminations ('admin' = appealable, 'forced' = for-cause) are
      // explicit and may target ACTIVE accounts (GDPR/DMCA/ban), so they skip
      // the expired gate — only an admin cancel (status→'none') stops them.
      $status = $account->getDeletionStatus();
      $due = $deleteAfterTs !== null && $deleteAfterTs <= time() && (
        ($status === 'pending' && $account->isExpired())
        || $status === 'admin' || $status === 'forced'
      );
      $response->getBody()->write(json_encode([
        'ok' => true,
        'deletionStatus' => $account->getDeletionStatus(),
        'deleteAfter' => $deleteAfterTs,
        'due' => $due,
      ]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/deletion-state error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'deletion-state failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Terminal finalize, called by the DeletionWorkflow AFTER it has deleted the
  // user's media bytes from R2+E2. DB-ONLY: drop the users_images + folders rows
  // and reset the account to a blank free shell (keeps npub + login). PHP no
  // longer does any S3 work for deletion - the Worker owns the byte-deletion.
  // Re-checks 'pending' so a renewed/cancelled account is never finalized.
  $group->post('/finalize-deletion', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $uuid = is_array($data) ? trim((string)($data['uuid'] ?? '')) : '';
    if ($uuid === '') {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'uuid required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $account = Account::fromUuid($uuid, $link);
      if ($account === null) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'no-such-account']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      // Mirror /deletion-state: self-service stays expired-only; admin
      // terminations ('admin'/'forced') finalize regardless of expiry. Anything
      // else (cancelled/renewed/already-finalized) is an idempotent no-op.
      $status = $account->getDeletionStatus();
      $dueNow = ($status === 'pending' && $account->isExpired())
        || $status === 'admin' || $status === 'forced';
      if (!$dueNow) {
        $response->getBody()->write(json_encode(['ok' => true, 'status' => 'not-pending']));
        return $response->withHeader('Content-Type', 'application/json');
      }
      // DB-only row cleanup (bytes already gone, deleted by the Worker). The
      // users_nostr_images rows would cascade from users_images / users_nostr_notes,
      // but we clear child-first explicitly inside the same transaction. The
      // folders still need their own delete. users_nostr_notes is wiped inside
      // wipeForDeletion() — it owns the "account wiped" semantics.
      $link->begin_transaction();
      try {
        foreach (['users_nostr_images', 'users_images', 'users_images_folders'] as $table) {
          $del = $link->prepare("DELETE FROM {$table} WHERE user_uuid = ?");
          if (!$del) {
            throw new Exception('Failed to prepare finalize-deletion cleanup for ' . $table);
          }
          $del->bind_param('s', $uuid);
          if (!$del->execute()) {
            throw new Exception('Failed to delete rows from ' . $table . ': ' . $del->error);
          }
          $del->close();
        }
        // Reset the profile to a blank free shell (keeps npub + login).
        $account->wipeForDeletion();
        $link->commit();
      } catch (Throwable $e) {
        if (isset($del) && $del instanceof mysqli_stmt) {
          $del->close();
        }
        $link->rollback();
        throw $e;
      }

      $response->getBody()->write(json_encode(['ok' => true, 'status' => 'finalized']));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/finalize-deletion error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'finalize-deletion failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // ── Account-expiry renewal reminders (Worker-driven) ─────────────────────
  // PHP no longer sends any Nostr DMs. The Worker's hourly cron sends the
  // "your account expired / is expiring, please renew" DMs over event-cannon;
  // these two SQL-only endpoints expose the data. Cadence mirrors the old
  // NostrAuthMiddleware: paid/creator tiers (acctlevel not staff 89/99), expiring
  // within 30 days OR expired < 70 days, and not notified in the last 7 days.
  $group->get('/expiry-reminders-due', function (Request $request, Response $response) {
    global $link;
    try {
      $sql = "SELECT usernpub,
                CASE WHEN plan_until_date < NOW() THEN 'expired' ELSE 'expiring' END AS kind,
                CASE WHEN plan_until_date < NOW() THEN DATEDIFF(NOW(), plan_until_date)
                     ELSE DATEDIFF(plan_until_date, NOW()) END AS days
              FROM users
              WHERE plan_until_date IS NOT NULL
                AND acctlevel NOT IN (89, 99)
                AND (
                  (plan_until_date < NOW() AND DATEDIFF(NOW(), plan_until_date) < 70)
                  OR (plan_until_date >= NOW() AND DATEDIFF(plan_until_date, NOW()) <= 30)
                )
                AND (last_notification_date IS NULL OR DATEDIFF(NOW(), last_notification_date) > 7)
              ORDER BY plan_until_date ASC
              LIMIT 500";
      $result = $link->query($sql);
      $due = [];
      if ($result) {
        while ($row = $result->fetch_assoc()) {
          $due[] = [
            'npub' => $row['usernpub'],
            'kind' => $row['kind'],
            'days' => (int)$row['days'],
          ];
        }
        $result->free();
      }
      $response->getBody()->write(json_encode(['due' => $due]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/expiry-reminders-due error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['due' => []]));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Stamp last_notification_date = today for the npubs the Worker just DMed, so
  // the 7-day dedupe in /expiry-reminders-due holds. Idempotent.
  $group->post('/mark-reminded', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $npubs = (is_array($data) && isset($data['npubs']) && is_array($data['npubs'])) ? $data['npubs'] : [];
    $npubs = array_values(array_filter($npubs, fn($n) => is_string($n) && str_starts_with($n, 'npub1')));
    if (count($npubs) === 0) {
      $response->getBody()->write(json_encode(['ok' => true, 'marked' => 0]));
      return $response->withHeader('Content-Type', 'application/json');
    }
    try {
      $placeholders = implode(',', array_fill(0, count($npubs), '?'));
      $types = str_repeat('s', count($npubs));
      $stmt = $link->prepare("UPDATE users SET last_notification_date = CURDATE() WHERE usernpub IN ($placeholders)");
      $stmt->bind_param($types, ...$npubs);
      $stmt->execute();
      $marked = $stmt->affected_rows;
      $stmt->close();
      $response->getBody()->write(json_encode(['ok' => true, 'marked' => $marked]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/mark-reminded error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'mark-reminded failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // ── Long-inactivity auto-termination (Worker Sunday sweep) ───────────────
  // The Worker's weekly ExpiredTerminationSweepWorkflow schedules accounts that
  // have been EXPIRED for over 2 years for the SAME 30-day reversible deletion a
  // user self-service request creates (status 'pending'), tagged
  // category='inactivity' so the admin views/audit can tell it apart. Three
  // SQL-only endpoints: list candidates, schedule one (atomic/idempotent), list
  // the in-flight ones for the admin "Upcoming terminations" view. Banned
  // accounts are included on purpose; staff (89/99) are excluded.
  $group->post('/expired-termination/candidates', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $limit = is_array($data) ? (int)($data['limit'] ?? 100) : 100;
    $limit = max(1, min(500, $limit));
    $minDays = is_array($data) ? (int)($data['minExpiredDays'] ?? 730) : 730;
    $minDays = max(1, $minDays);
    // Offset paging for the admin "Upcoming terminations" view. The sweep itself
    // omits it (offset 0) and drains the set top-down via the exclusion list.
    $offset = is_array($data) ? max(0, (int)($data['offset'] ?? 0)) : 0;
    // Optional free-text search (nym / npub / uuid) for the admin view.
    $search = is_array($data) ? trim((string)($data['q'] ?? '')) : '';
    $withTotal = is_array($data) && !empty($data['withTotal']);
    // App-side hold list (D1) — the only thing PHP can't know on its own. Cap the
    // exclusion set so a runaway list can't blow the statement; holds are a small
    // admin-curated set in practice.
    $exclude = (is_array($data) && isset($data['excludeUuids']) && is_array($data['excludeUuids']))
      ? array_values(array_filter($data['excludeUuids'], fn($u) => is_string($u) && $u !== ''))
      : [];
    if (count($exclude) > 1000) {
      error_log('internal/account/expired-termination/candidates: hold-exclusion list of ' . count($exclude) . ' truncated to 1000 — excess held accounts may be swept');
      $exclude = array_slice($exclude, 0, 1000);
    }
    try {
      $where = "plan_until_date IS NOT NULL
                AND plan_until_date < DATE_SUB(NOW(), INTERVAL {$minDays} DAY)
                AND acctlevel NOT IN (89, 99)
                AND deletion_status = 'none'";
      $params = [];
      $types = '';
      if (count($exclude) > 0) {
        $ph = implode(',', array_fill(0, count($exclude), '?'));
        $where .= " AND uuid_id NOT IN ($ph)";
        foreach ($exclude as $u) { $params[] = $u; $types .= 's'; }
      }
      if ($search !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $where .= " AND (usernpub LIKE ? OR nym LIKE ? OR uuid_id LIKE ?)";
        $params[] = $like; $params[] = $like; $params[] = $like; $types .= 'sss';
      }
      $sql = "SELECT uuid_id, usernpub, nym, ppic, plan_until_date,
                     DATEDIFF(NOW(), plan_until_date) AS days_expired
              FROM users WHERE {$where}
              ORDER BY plan_until_date ASC
              LIMIT {$limit} OFFSET {$offset}";
      $stmt = $link->prepare($sql);
      if (count($params) > 0) { $stmt->bind_param($types, ...$params); }
      $stmt->execute();
      $res = $stmt->get_result();
      $candidates = [];
      while ($row = $res->fetch_assoc()) {
        $candidates[] = [
          'uuid' => $row['uuid_id'],
          'npub' => $row['usernpub'],
          'nym' => $row['nym'],
          'pfpUrl' => $row['ppic'],
          'planUntilDate' => $row['plan_until_date'],
          'daysExpired' => (int)$row['days_expired'],
        ];
      }
      $stmt->close();
      $total = null;
      if ($withTotal) {
        $cstmt = $link->prepare("SELECT COUNT(*) AS c FROM users WHERE {$where}");
        if (count($params) > 0) { $cstmt->bind_param($types, ...$params); }
        $cstmt->execute();
        $total = (int)($cstmt->get_result()->fetch_assoc()['c'] ?? 0);
        $cstmt->close();
      }
      $response->getBody()->write(json_encode(['ok' => true, 'candidates' => $candidates, 'total' => $total]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/expired-termination/candidates error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'candidates' => [], 'total' => null]));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Schedule ONE long-inactive account. Atomic + idempotent + race-safe inside
  // Account::scheduleInactivityDeletion (only flips a still-eligible 'none' row).
  // `scheduled` is true ONLY when this call did the flip — the Worker DMs/audits
  // on that, so a retry (which sees 'pending' now) is a clean no-op.
  $group->post('/expired-termination/schedule', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $uuid = is_array($data) ? trim((string)($data['uuid'] ?? '')) : '';
    if ($uuid === '') {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'uuid required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $account = Account::fromUuid($uuid, $link);
      if ($account === null) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'no-such-account']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      $result = $account->scheduleInactivityDeletion();
      $response->getBody()->write(json_encode([
        'ok' => true,
        'scheduled' => $result['scheduled'],
        'status' => $result['status'],
        'npub' => $account->getNpub(),
        'deleteAfter' => $result['deleteAfter'],
      ]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/expired-termination/schedule error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'schedule failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // The in-flight automated terminations (status 'pending' + category
  // 'inactivity') for the admin "Upcoming terminations" view. Offset-paged on a
  // stable set; returns the count too so the UI can show the total.
  $group->get('/inactivity-terminations', function (Request $request, Response $response) {
    global $link;
    $qp = $request->getQueryParams();
    $limit = max(1, min(500, (int)($qp['limit'] ?? 100)));
    $offset = max(0, (int)($qp['offset'] ?? 0));
    $search = trim((string)($qp['q'] ?? ''));
    try {
      $where = "deletion_status = 'pending' AND deletion_category = 'inactivity'";
      $params = [];
      $types = '';
      if ($search !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        $where .= " AND (usernpub LIKE ? OR nym LIKE ? OR uuid_id LIKE ?)";
        $params = [$like, $like, $like];
        $types = 'sss';
      }
      $sql = "SELECT uuid_id, usernpub, nym, ppic, plan_until_date, delete_after, deletion_requested_at
              FROM users WHERE {$where}
              ORDER BY delete_after ASC
              LIMIT {$limit} OFFSET {$offset}";
      $stmt = $link->prepare($sql);
      if (count($params) > 0) { $stmt->bind_param($types, ...$params); }
      $stmt->execute();
      $result = $stmt->get_result();
      $rows = [];
      while ($row = $result->fetch_assoc()) {
        $rows[] = [
          'uuid' => $row['uuid_id'],
          'npub' => $row['usernpub'],
          'nym' => $row['nym'],
          'pfpUrl' => $row['ppic'],
          'planUntilDate' => $row['plan_until_date'],
          'deleteAfter' => $row['delete_after'] !== null ? strtotime($row['delete_after']) : null,
          'requestedAt' => $row['deletion_requested_at'] !== null ? strtotime($row['deletion_requested_at']) : null,
        ];
      }
      $stmt->close();
      $cstmt = $link->prepare("SELECT COUNT(*) AS c FROM users WHERE {$where}");
      if (count($params) > 0) { $cstmt->bind_param($types, ...$params); }
      $cstmt->execute();
      $total = (int)($cstmt->get_result()->fetch_assoc()['c'] ?? 0);
      $cstmt->close();
      $response->getBody()->write(json_encode(['ok' => true, 'rows' => $rows, 'total' => $total]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/inactivity-terminations error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'rows' => [], 'total' => 0]));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });

  // Server-initiated cancel of an INACTIVITY-sweep 'pending' deletion — used when
  // an admin HOLDS an in-flight inactivity termination. Cancels ONLY a
  // category='inactivity' 'pending' schedule, so it can never undo a USER's own
  // self-service deletion (category NULL) nor an admin 'admin'/'forced'
  // termination. Idempotent no-op otherwise.
  $group->post('/cancel-deletion', function (Request $request, Response $response) {
    global $link;
    $data = json_decode($request->getBody()->getContents(), true);
    $uuid = is_array($data) ? trim((string)($data['uuid'] ?? '')) : '';
    if ($uuid === '') {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'uuid required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $account = Account::fromUuid($uuid, $link);
      if ($account === null) {
        $response->getBody()->write(json_encode(['ok' => false, 'error' => 'no-such-account']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
      }
      // Guard to the automated sweep's own terminations only.
      if ($account->getDeletionStatus() === 'pending' && $account->getDeletionCategory() === 'inactivity') {
        $account->cancelDeletion(false);
      }
      $response->getBody()->write(json_encode(['ok' => true, 'deletionStatus' => $account->getDeletionStatus()]));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/account/cancel-deletion error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'cancel-deletion failed']));
      return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
  });
})->add(new HmacAuthMiddleware());
