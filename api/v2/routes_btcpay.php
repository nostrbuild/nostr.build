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
  // Activate a settled invoice. The Worker's PaymentWorkflow calls this after
  // its webhook confirms settlement; it runs the SAME fulfillment core as the
  // PHP webhook (setPlan / credits-topup / referral + emitProfileChanged), and
  // is idempotent, so dual-run double-fire is safe.
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
