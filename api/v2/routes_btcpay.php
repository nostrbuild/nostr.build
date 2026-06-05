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

$app->group('/btcpay', function (RouteCollectorProxy $group) {
  // Route to upload file(s) via form
  $group->post('/webhook', function (Request $request, Response $response) {
    // Get Raw Body
    $body = $request->getBody()->getContents();
    // Get Signature
    $signature = $request->getHeaderLine('btcpay-sig');

    try {
      // Instantiate BTCPayWebhook
      $webhook = $this->get('btcpayWebhook');
      $check = $webhook->processWebhook($body, $signature);
      if(!$check) {
        return btcpayWebhookResponse($response, 'error', 'Webhook processing failed');
      }

      // Handle exceptions thrown by the MultimediaUpload class
      return btcpayWebhookResponse($response, 'success', 'Webhook processed successfully');
    } catch (\Exception $e) {
      return btcpayWebhookResponse($response, 'error', $e->getMessage());
    }
  });

  $group->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
  });
});

// Worker-facing internal plans endpoints (the account.nostr.build Worker calls
// these during the dual-run migration). HMAC-authed via NB_HMAC_SECRETS — the
// SAME shared secret the PHP->Worker events bridge uses — so only the Worker can
// reach them. Full paths: POST /api/v2/internal/plans/activate,
// GET /api/v2/internal/plans/promotions.
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
      $status = $account->setPlan($plan, $period, $new);
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

  // Activate a settled invoice. LEGACY/UNUSED by the in-app flow now: the Worker
  // calls /set-plan above with its own verified facts instead of having PHP
  // re-derive everything from the invoice. Left in place (the PHP webhook still
  // references fulfillInvoiceById) but no longer on the in-app settlement path.
  $group->post('/activate', function (Request $request, Response $response) {
    $data = json_decode($request->getBody()->getContents(), true);
    $invoiceId = is_array($data) ? (string)($data['invoiceId'] ?? '') : '';
    if ($invoiceId === '') {
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'invoiceId required']));
      return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    try {
      $webhook = $this->get('btcpayWebhook');
      $ok = $webhook->fulfillInvoiceById($invoiceId);
      $response->getBody()->write(json_encode(['ok' => (bool)$ok]));
      return $response->withStatus($ok ? 200 : 422)->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
      error_log('internal/plans/activate error: ' . $e->getMessage());
      $response->getBody()->write(json_encode(['ok' => false, 'error' => 'activation failed']));
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
      $response->getBody()->write(json_encode([
        'ok' => true,
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
