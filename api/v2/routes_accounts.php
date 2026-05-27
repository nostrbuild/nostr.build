<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/HmacAuthMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ClientInfoMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ImageCatalogManager.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once __DIR__ . '/helper_functions.php';
require_once __DIR__ . '/routes_account_dashboard.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * New route group for the accounts.nostr.build Worker BFF.
 *
 * Mounted behind HmacAuthMiddleware (with-body, default NB_HMAC_SECRETS) so
 * the accounts Worker is the only legitimate caller. Identity for the acting
 * user is read from x-accounts-userid / x-accounts-npub via
 * metadataFromHeaders() — which was extended in Task 9 to recognize the
 * x-accounts-* prefix.
 *
 * Phase 1 ships: stubbed /accounts/login + real /accounts/user-by-npub.
 * Phases 2+ will fill in /accounts/dashboard/* and /accounts/uploads/*.
 */
$app->group('/accounts', function (RouteCollectorProxy $group) {
  // POST /api/v2/accounts/login — npub + password validation.
  // Mirrors login/index.php:54-66 production behavior. Returns the full
  // dashboard profile shape (same as /accounts/dashboard/profile) so the
  // Worker can seed the SessionDO snapshot + emit all three ecosystem
  // cookies (npub + user_level + plan_expired) on the login response
  // without a second PHP roundtrip.
  $group->post('/login', function (Request $request, Response $response) {
    global $link;
    $raw = (string) $request->getBody();
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['npub']) || empty($data['password'])) {
      $response->getBody()->write(json_encode(['error' => 'invalid-input']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400);
    }

    $npub = trim((string) $data['npub']);
    $password = (string) $data['password'];

    $account = $this->get('accountClass')($npub);
    if (!$account->accountExists() || !$account->verifyPassword($password)) {
      $response->getBody()->write(json_encode(['error' => 'invalid-credentials']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(401);
    }

    // dashboardGetAccountData -> dashboardGetCredits reads $_SESSION['usernpub']
    // and writes $_SESSION['sd_credits']. Set the session var transiently so
    // the helper works unmodified, then restore.
    $prevNpub = $_SESSION['usernpub'] ?? null;
    $_SESSION['usernpub'] = $npub;
    try {
      $data = dashboardGetAccountData($link, $account);
    } finally {
      if ($prevNpub === null) {
        unset($_SESSION['usernpub']);
      } else {
        $_SESSION['usernpub'] = $prevNpub;
      }
    }
    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // GET /api/v2/accounts/user-by-npub?npub=... — npub → internal user id
  // lookup used by the Worker after NIP-07 signature verification.
  //
  // Uses the same 'accountClass' DI factory the rest of api/v2 uses
  // (see index.php:105-110 and routes_blossom.php:45,189) so we don't
  // duplicate the (npub, $link) wiring.
  //
  // Mirrors the legacy NostrLoginMiddleware gate at routes_account.php:225-227
  // and the BFF DM-login gate at /accounts/nostr-dm-login below: NIP-07 login
  // is only honored when the per-account allow_npub_login flag is on. Without
  // this check the Worker happily issues a session from a verified NIP-07
  // signature even though the user explicitly disabled extension login.
  $group->get('/user-by-npub', function (Request $request, Response $response) {
    $npub = $request->getQueryParams()['npub'] ?? '';
    if ($npub === '') {
      $response->getBody()->write(json_encode(['error' => 'missing npub']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400);
    }

    $account = $this->get('accountClass')($npub);
    if (!$account->accountExists()) {
      $response->getBody()->write(json_encode(['error' => 'not found']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404);
    }

    if (!$account->isNpubLoginAllowed()) {
      $response->getBody()->write(json_encode(['error' => 'npub-login-disabled']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(403);
    }

    // See /login route above — same transient $_SESSION['usernpub'] pattern.
    global $link;
    $prevNpub = $_SESSION['usernpub'] ?? null;
    $_SESSION['usernpub'] = $npub;
    try {
      $data = dashboardGetAccountData($link, $account);
    } finally {
      if ($prevNpub === null) {
        unset($_SESSION['usernpub']);
      } else {
        $_SESSION['usernpub'] = $prevNpub;
      }
    }
    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // POST /api/v2/accounts/nostr-dm-login — finalize a "Login with DM" flow.
  // The accounts Worker calls this only after it has matched the one-time code
  // it DM'd to the user, so the Worker is HMAC-trusted to vouch for npub
  // ownership here. Runs the same DB-side gate as the legacy routes_account.php
  // /login DM branch (account exists -> npub verified -> npub login enabled),
  // then returns the account record for the Worker to mint its session from.
  //
  // It deliberately does NOT call $account->verifyNostrLogin(): that mutates
  // PHP's $_SESSION, which this Worker BFF never uses — sessions are issued in
  // the Worker, exactly as /accounts/login (password) already does.
  $group->post('/nostr-dm-login', function (Request $request, Response $response) {
    $raw = (string) $request->getBody();
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['npub'])) {
      $response->getBody()->write(json_encode(['error' => 'invalid-input']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(400);
    }

    $npub = trim((string) $data['npub']);
    $account = $this->get('accountClass')($npub);

    if (!$account->accountExists()) {
      $response->getBody()->write(json_encode(['error' => 'not-found']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(404);
    }

    // The verified DM code proves npub ownership — mark it verified, mirroring
    // routes_account.php:264-266. isNpubLoginAllowed() depends on this flag.
    if (!$account->isNpubVerified()) {
      $account->verifyNpub();
    }
    if (!$account->isNpubLoginAllowed()) {
      $response->getBody()->write(json_encode(['error' => 'npub-login-disabled']));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(403);
    }

    // See /login route above — same transient $_SESSION['usernpub'] pattern.
    global $link;
    $prevNpub = $_SESSION['usernpub'] ?? null;
    $_SESSION['usernpub'] = $npub;
    try {
      $data = dashboardGetAccountData($link, $account);
    } finally {
      if ($prevNpub === null) {
        unset($_SESSION['usernpub']);
      } else {
        $_SESSION['usernpub'] = $prevNpub;
      }
    }
    $response->getBody()->write(json_encode($data));
    return $response
      ->withHeader('Content-Type', 'application/json')
      ->withStatus(200);
  });

  // Nested /dashboard subgroup — reuses helpers from routes_account_dashboard.php
  // for data shaping. Identity resolved from X-Accounts-Npub (set by the Worker).
  $group->group('/dashboard', function (RouteCollectorProxy $sub) {

    // GET /api/v2/accounts/dashboard/profile
    $sub->get('/profile', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }
      $account = new Account($npub, $link);
      if (!$account->accountExists()) {
        $response->getBody()->write(json_encode(['error' => 'not-found']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(404);
      }
      // dashboardGetAccountData -> dashboardGetCredits reads $_SESSION['usernpub']
      // and writes $_SESSION['sd_credits']. Set the session var transiently so
      // the helper works unmodified, then restore.
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $data = dashboardGetAccountData($link, $account);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
      $response->getBody()->write(json_encode($data));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // GET /api/v2/accounts/dashboard/folders
    $sub->get('/folders', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }
      $folders = new UsersImagesFolders($link);
      $folderList = $folders->getFoldersWithStats($npub);
      $result = array_map(function ($folder) {
        $folderName = $folder['folder'];
        $firstChar = mb_substr($folderName, 0, 1, 'UTF-8');
        $folderIcon = mb_strlen($firstChar, 'UTF-8') === 1 ? strtoupper($firstChar) : '#';
        return [
          'name' => $folderName,
          'icon' => $folderIcon,
          'route' => '#f=' . urlencode($folderName),
          'id' => $folder['id'],
          'allowDelete' => true,
          'stats' => [
            'allSize'       => (int) ($folder['allSize']      ?? 0),
            'all'           => (int) ($folder['all']          ?? 0),
            'imagesSize'    => (int) ($folder['imageSize']    ?? 0),
            'images'        => (int) ($folder['images']       ?? 0),
            'gifsSize'      => (int) ($folder['gifSize']      ?? 0),
            'gifs'          => (int) ($folder['gifs']         ?? 0),
            'videosSize'    => (int) ($folder['videoSize']    ?? 0),
            'videos'        => (int) ($folder['videos']       ?? 0),
            'audioSize'     => (int) ($folder['audioSize']    ?? 0),
            'audio'         => (int) ($folder['audio']        ?? 0),
            'documentsSize' => (int) ($folder['documentSize'] ?? 0),
            'documents'     => (int) ($folder['documents']    ?? 0),
            'archivesSize'  => (int) ($folder['archiveSize']  ?? 0),
            'archives'      => (int) ($folder['archives']     ?? 0),
            'othersSize'    => (int) ($folder['otherSize']    ?? 0),
            'others'        => (int) ($folder['others']       ?? 0),
            'publicCount'   => (int) ($folder['publicCount']  ?? 0),
          ],
        ];
      }, $folderList);
      $response->getBody()->write(json_encode($result));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // GET /api/v2/accounts/dashboard/files
    $sub->get('/files', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $params = $request->getQueryParams();
      $folder = $params['folder'] ?? null;
      if (empty($folder)) {
        $response->getBody()->write(json_encode(['error' => 'missing-folder']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }
      $start = isset($params['start']) ? max(0, intval($params['start'])) : null;
      $limit = isset($params['limit']) ? min(500, max(1, intval($params['limit']))) : null;
      $filter = $params['filter'] ?? null;
      $allowedFilters = ['all', 'images', 'videos', 'audio', 'gifs', 'documents', 'archives', 'others'];
      if ($filter !== null && !in_array($filter, $allowedFilters, true)) {
        $response->getBody()->write(json_encode(['error' => 'invalid-filter']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      // dashboardListFiles -> getFiles reads $_SESSION['usernpub'] inside
      // UsersImages. Set the session var transiently, then restore.
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $files = dashboardListFiles((string) $folder, $link, $start, $limit, $filter);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode($files));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/media/delete
    $sub->post('/media/delete', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $foldersToDelete = !empty($body['foldersToDelete']) ? json_decode($body['foldersToDelete']) : [];
      $imagesToDelete = !empty($body['imagesToDelete']) ? json_decode($body['imagesToDelete']) : [];

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3 = new S3Service($awsConfig);
        $icm = new ImageCatalogManager($link, $s3, $npub);
        $deletedFolders = array_map('intval', $icm->deleteFolders((array) $foldersToDelete));
        $deletedImages = array_map('intval', $icm->deleteImages((array) $imagesToDelete));
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'delete',
        'deletedFolders' => $deletedFolders,
        'deletedImages' => $deletedImages,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/media/share
    $sub->post('/media/share', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $account = new Account($npub, $link);
      if (!$account->accountExists()) {
        $response->getBody()->write(json_encode(['error' => 'not-found']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(404);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        // Permission allowlist: Creator (1), Advanced (10), Admin (99) — mirrors
        // the legacy Permission::validatePermissionsLevelAny(1, 10, 99).
        // Expiry is intentionally NOT gated here: an expired subscriber should
        // still be able to publish/unpublish their existing media (legacy
        // blocked this; the new app deliberately relaxes it).
        $level = (int) $account->getAccountLevel()->value;
        if (!in_array($level, [1, 10, 99], true)) {
          $response->getBody()->write(json_encode(['error' => 'forbidden']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
        }

        $body = $request->getParsedBody();
        $imagesToShare = !empty($body['imagesToShare']) ? (array) json_decode($body['imagesToShare']) : [];
        $shareFlag = !empty($body['shareFlag']) ? $body['shareFlag'] === 'true' : true;

        if (empty($imagesToShare)) {
          $response->getBody()->write(json_encode(['error' => 'no-images']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
        }

        $s3 = new S3Service($awsConfig);
        $icm = new ImageCatalogManager($link, $s3, $npub);
        $sharedImages = array_map('intval', $icm->shareImage($imagesToShare, (bool) $shareFlag));
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'share_creator_page',
        'sharedImages' => $sharedImages,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/media/move
    $sub->post('/media/move', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $imagesToMove = !empty($body['imagesToMove']) ? (array) json_decode($body['imagesToMove']) : [];
      $destinationFolderId = isset($body['destinationFolderId']) ? (int) $body['destinationFolderId'] : 0;

      if (empty($imagesToMove)) {
        $response->getBody()->write(json_encode(['error' => 'no-images']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3 = new S3Service($awsConfig);
        $icm = new ImageCatalogManager($link, $s3, $npub);
        $movedImages = array_map('intval', $icm->moveImages($imagesToMove, $destinationFolderId));
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'move_to_folder',
        'movedImages' => $movedImages,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/media/import
    $sub->post('/media/import', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $url = isset($body['url']) ? (string) $body['url'] : '';
      $folder = isset($body['folder']) ? (string) $body['folder'] : '';

      if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        $response->getBody()->write(json_encode(['error' => 'invalid-url']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }
      $scheme = parse_url($url, PHP_URL_SCHEME);
      if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
        $response->getBody()->write(json_encode(['error' => 'invalid-scheme']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $account = new Account($npub, $link);
        if (!$account->accountExists()) {
          $response->getBody()->write(json_encode(['error' => 'not-found']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
        }
        $daysRemaining = dashboardGetDaysRemaining();
        if ($daysRemaining <= 0 || $account->getPerFileUploadLimit() <= 0) {
          $response->getBody()->write(json_encode(['error' => 'account-expired']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
        }
        $media = dashboardImportFromURL($url, $folder, '', '', $link, $awsConfig);
      } catch (\Throwable $e) {
        error_log($e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'import-failed']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode($media));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/nostrland/activate
    $sub->post('/nostrland/activate', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $account = new Account($npub, $link);
        if (!$account->accountExists()) {
          $response->getBody()->write(json_encode(['error' => 'not-found']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
        }
        if (!$account->isAccountNostrLandPlusEligible()) {
          $response->getBody()->write(json_encode(['error' => 'not-eligible']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
        }
        if ($account->hasNlSubActivation()) {
          $response->getBody()->write(json_encode(['error' => 'already-activated']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
        }
        require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrLand.class.php';
        $nostrLand = new NostrLand($npub, $link);
        $result = $nostrLand->activateSubscription();
        if ($result === null) {
          $response->getBody()->write(json_encode(['error' => 'activation-failed']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
        }
        $refreshed = dashboardGetAccountData($link, $account);
      } catch (\Throwable $e) {
        error_log('NostrLand activation failed: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'activation-failed']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'activate_nostrland_plus',
        'accountData' => $refreshed,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/folders/create
    $sub->post('/folders/create', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $folderName = isset($body['folderName']) ? trim((string) $body['folderName']) : '';
      if ($folderName === '') {
        $response->getBody()->write(json_encode(['error' => 'empty-name']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $folders = new UsersImagesFolders($link);
        $folderId = $folders->findFolderByNameOrCreate($npub, $folderName);
      } catch (\Throwable $e) {
        error_log($e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'create-failed']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'create_folder',
        'folderId' => (int) $folderId,
        'folderName' => $folderName,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/folders/rename
    $sub->post('/folders/rename', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $folderToRename = !empty($body['foldersToRename']) ? json_decode($body['foldersToRename']) : null;
      $folderNames = !empty($body['folderNames']) ? json_decode($body['folderNames']) : null;

      if (empty($folderToRename) || empty($folderNames)) {
        $response->getBody()->write(json_encode(['error' => 'missing-params']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $folderToRename = array_map('intval', (array) $folderToRename);
      $folderNames = array_map('strval', (array) $folderNames);

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      $renamedFolders = [];
      try {
        $s3 = new S3Service($awsConfig);
        $icm = new ImageCatalogManager($link, $s3, $npub);
        foreach ($folderToRename as $i => $id) {
          if (!isset($folderNames[$i])) continue;
          foreach ($icm->renameFolder($id, $folderNames[$i]) as $renamed) {
            $renamedFolders[] = (int) $renamed;
          }
        }
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'rename_folders',
        'renamedFolders' => $renamedFolders,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/folders/delete
    $sub->post('/folders/delete', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $foldersToDelete = !empty($body['foldersToDelete']) ? json_decode($body['foldersToDelete']) : [];

      if (empty($foldersToDelete)) {
        $response->getBody()->write(json_encode(['error' => 'no-folders']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $foldersToDelete = array_map('intval', $foldersToDelete);
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3 = new S3Service($awsConfig);
        $icm = new ImageCatalogManager($link, $s3, $npub);
        $deletedFolders = array_map('intval', $icm->deleteFolders($foldersToDelete));
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'delete_folders',
        'deletedFolders' => $deletedFolders,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/media/metadata
    $sub->post('/media/metadata', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $mediaId = !empty($body['mediaId']) ? (int) $body['mediaId'] : null;
      $title = isset($body['title']) ? (string) $body['title'] : '';
      $description = isset($body['description']) ? (string) $body['description'] : '';

      if ($mediaId === null) {
        $response->getBody()->write(json_encode(['error' => 'missing-mediaId']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3 = new S3Service($awsConfig);
        $icm = new ImageCatalogManager($link, $s3, $npub);
        $updatedMedia = $icm->updateMediaMetadata($mediaId, $title, $description);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode([
        'action' => 'update_media_metadata',
        'updatedMedia' => $updatedMedia,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/profile
    $sub->post('/profile', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $name = isset($body['name']) ? (string) $body['name'] : null;
      $pfpUrl = isset($body['pfpUrl']) ? (string) $body['pfpUrl'] : null;
      $wallet = isset($body['wallet']) ? (string) $body['wallet'] : null;
      $defaultFolder = isset($body['defaultFolder']) ? (string) $body['defaultFolder'] : '';
      // Accept JSON true/false OR string 'true'/'false' for parity with the
      // legacy multipart endpoint.
      $rawNl = $body['allowNostrLogin'] ?? false;
      $allowNostrLogin = ($rawNl === true || $rawNl === 'true' || $rawNl === 1 || $rawNl === '1');

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $account = new Account($npub, $link);
        if (!$account->accountExists()) {
          $response->getBody()->write(json_encode(['error' => 'not-found']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
        }
        $account->updateAccount(
          nym: $name,
          ppic: $pfpUrl,
          wallet: $wallet,
          default_folder: $defaultFolder,
        );
        $account->allowNpubLogin($allowNostrLogin);
        $data = dashboardGetAccountData($link, $account);
      } catch (\Throwable $e) {
        error_log($e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'update-failed']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode($data));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/npub/mark-verified
    //
    // Called by the Worker after it has cryptographically proven (NIP-07
    // signature OR matched DM code) that the user owns the npub bound to
    // their session. Worker carries the trust via HmacAuthMiddleware; the
    // X-Accounts-Npub header is set by the Worker (cookie-authenticated user)
    // and cannot be spoofed by the browser.
    //
    // Effect: flips users.npub_verified = 1 via Account::verifyNpub(), which
    // also syncs to Blossom. No-op when already verified (returns 200).
    $sub->post('/npub/mark-verified', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $account = new Account($npub, $link);
        if (!$account->accountExists()) {
          $response->getBody()->write(json_encode(['error' => 'not-found']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
        }
        if (!$account->isNpubVerified()) {
          $account->verifyNpub();
        }
      } catch (\Throwable $e) {
        error_log('npub mark-verified failed: ' . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'update-failed']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      $response->getBody()->write(json_encode(['ok' => true]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/profile/password
    $sub->post('/profile/password', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-identity']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $body = $request->getParsedBody();
      $currentPassword = isset($body['password']) ? (string) $body['password'] : '';
      $newPassword = isset($body['newPassword']) ? (string) $body['newPassword'] : '';

      if ($currentPassword === '' || $newPassword === '') {
        $response->getBody()->write(json_encode(['error' => 'missing-password']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $account = new Account($npub, $link);
        if (!$account->accountExists()) {
          $response->getBody()->write(json_encode(['error' => 'not-found']));
          return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(404);
        }
        $ok = $account->changePasswordSafe($currentPassword, $newPassword);
      } catch (\Throwable $e) {
        error_log($e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'update-failed']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }

      if (!$ok) {
        $response->getBody()->write(json_encode(['error' => 'password-change-failed']));
        return $response
          ->withHeader('Content-Type', 'application/json')
          ->withStatus(400);
      }

      $response->getBody()->write(json_encode([
        'action' => 'update_password',
        'success' => true,
      ]));
      return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
    });

    // POST /api/v2/accounts/dashboard/media/poster — replace a video's poster
    // frame. Ported from routes_account_dashboard.php; the $_SESSION swap lets
    // any legacy helper that reads $_SESSION['usernpub'] run unchanged.
    $sub->post('/media/poster', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return dashboardError($response, 'missing-identity', 400);
      }

      $body = $request->getParsedBody();
      $fileId = $body['fileId'] ?? 0;
      if (!$fileId) {
        return dashboardError($response, 'File ID is missing');
      }

      $uploadedFiles = $request->getUploadedFiles();
      $uploadedFile = $uploadedFiles['file'] ?? null;
      if (!$uploadedFile || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
        return dashboardError($response, 'No file uploaded');
      }
      if ($uploadedFile->getClientMediaType() !== 'image/jpeg') {
        return dashboardError($response, 'Invalid file type');
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      $tmpPath = null;
      try {
        $images = new UsersImages($link);
        $videoInfo = $images->getFile(npub: $npub, fileId: $fileId);
        if (!$videoInfo) {
          return dashboardError($response, 'Video not found', 404);
        }

        $videoURL = SiteConfig::getFullyQualifiedUrl('professional_account_video') . $videoInfo['image'];

        $tmpPath = sys_get_temp_dir() . '/' . uniqid('poster_') . '.jpg';
        $uploadedFile->moveTo($tmpPath);

        $imageProcessor = new ImageProcessor($tmpPath);
        $imageProcessor->save();
        $posterDimensions = $imageProcessor->getImageDimensions();
        $imageProcessor->optimiseImage();

        $images->update($fileId, [
          'media_width' => $posterDimensions['width'],
          'media_height' => $posterDimensions['height'],
        ]);
        $sha256 = hash_file('sha256', $tmpPath);

        $objectKey = "{$videoInfo['image']}/poster.jpg";
        $objectBucketSuffix = SiteConfig::getBucketSuffix('professional_account_video');
        $objectBucket = $awsConfig['r2']['bucket'] . $objectBucketSuffix;

        $res = storeToR2Bucket(
          $tmpPath,
          $objectKey,
          $objectBucket,
          $awsConfig['r2']['endpoint'],
          $awsConfig['r2']['credentials']['key'],
          $awsConfig['r2']['credentials']['secret'],
          ['sha256' => $sha256, 'npub' => $npub, 'videoUrl' => $videoURL],
        );
        if (!$res) {
          throw new \Exception('Failed to upload video poster');
        }

        $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
        $purger->purgeFiles($objectKey, true);

        return dashboardJson($response, [
          'posterURL' => $videoURL . '/poster.jpg',
          'dimensions' => $posterDimensions,
        ]);
      } catch (\Throwable $e) {
        error_log($e->getMessage());
        return dashboardError($response, 'Failed to upload video poster', 500);
      } finally {
        if ($tmpPath !== null && file_exists($tmpPath)) {
          @unlink($tmpPath);
        }
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });

    // POST /api/v2/accounts/dashboard/nostr/publish — publish, or delete via a
    // kind-5 event, Nostr notes. Ported from routes_account_dashboard.php; the
    // permission check uses the account level directly (the BFF has no full
    // PHP session for the legacy Permission class), mirroring /media/share.
    $sub->post('/nostr/publish', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return dashboardError($response, 'missing-identity', 400);
      }

      $account = new Account($npub, $link);
      if (!$account->accountExists()) {
        return dashboardError($response, 'not-found', 404);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        // Creator (1), paid tiers (2, 3), Advanced (10), Admin (99) — mirrors
        // the legacy Permission::validatePermissionsLevelAny(1, 2, 3, 10, 99).
        // Expiry is intentionally NOT gated here so an expired subscriber can
        // still publish/unpublish their existing media (legacy blocked this;
        // the new app deliberately relaxes it). Upload + AI still are gated.
        $level = (int) $account->getAccountLevel()->value;
        if (!in_array($level, [1, 2, 3, 10, 99], true)) {
          return dashboardError($response, 'You do not have permission to publish Nostr events', 403);
        }

        $body = $request->getParsedBody();
        $signedEvent = $body['event'] ?? null;
        $mediaIds = !empty($body['mediaIds']) ? json_decode($body['mediaIds']) : [];
        $eventId = $body['eventId'] ?? null;
        $eventCreatedAt = $body['eventCreatedAt'] ?? null;
        $eventContent = $body['eventContent'] ?? null;

        $event = json_decode((string) $signedEvent, true);
        $eventKind = $event['kind'] ?? null;
        $eventIdsToDelete = $eventKind === 5
          ? array_map(fn($tag) => $tag[1], array_filter($event['tags'] ?? [], fn($tag) => $tag[0] === 'e'))
          : [];

        if (!$signedEvent || (empty($mediaIds) && $eventKind !== 5) || ($eventKind === 5 && empty($eventIdsToDelete)) || !$eventId || !$eventCreatedAt || !$eventContent) {
          return dashboardError($response, 'No event to publish or delete');
        }

        $nc = new NostrClient($_SERVER['NB_API_NOSTR_CLIENT_SECRET'], $_SERVER['NB_API_NOSTR_CLIENT_URL']);
        if (!$nc->sendPresignedNote($signedEvent)) {
          return dashboardError($response, 'Failed to publish Nostr event', 500);
        }

        switch ($eventKind) {
          case 5:
            $stmtDeleteEvent = $link->prepare('DELETE FROM users_nostr_notes WHERE usernpub = ? AND note_id = ?');
            $stmtDeleteImage = $link->prepare('DELETE FROM users_nostr_images WHERE usernpub = ? AND note_id = ?');
            foreach ($eventIdsToDelete as $eventToDelete) {
              $stmtDeleteEvent->bind_param('ss', $npub, $eventToDelete);
              $stmtDeleteEvent->execute();
              $stmtDeleteImage->bind_param('ss', $npub, $eventToDelete);
              $stmtDeleteImage->execute();
            }
            $stmtDeleteEvent->close();
            $stmtDeleteImage->close();
            break;

          case 1:
          case 20:
          case 21:
          case 1222:
            $stmt = $link->prepare('INSERT INTO users_nostr_notes (usernpub, note_id, created_at, content, full_json) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)');
            $stmt->bind_param('ssiss', $npub, $eventId, $eventCreatedAt, $eventContent, $signedEvent);
            $stmt->execute();
            $stmt->close();

            $stmt = $link->prepare('INSERT INTO users_nostr_images (usernpub, note_id, image_id) VALUES (?, ?, ?)');
            foreach ($mediaIds as $imageId) {
              $stmt->bind_param('ssi', $npub, $eventId, $imageId);
              $stmt->execute();
            }
            $stmt->close();
            break;

          default:
            return dashboardError($response, 'Invalid event kind');
        }

        $mediaEvents = array_combine(
          $mediaIds,
          array_fill(0, count($mediaIds), "{$eventId}:{$eventCreatedAt}"),
        );

        return dashboardJson($response, [
          'action' => 'publish_nostr_event',
          'success' => true,
          'noteId' => $eventId,
          'createdAt' => $eventCreatedAt,
          'mediaIds' => $mediaIds,
          'mediaEvents' => $mediaEvents,
          'deletedEvents' => $eventIdsToDelete,
        ]);
      } catch (\Throwable $e) {
        error_log($e->getMessage());
        return dashboardError($response, 'Failed to publish Nostr event', 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });

    // POST /api/v2/accounts/dashboard/ai/generate — text-to-image generation
    // across Stable AI Core (@sd/core, credit-gated) and Cloudflare Workers AI
    // models (@cf/...). Ported from routes_account_dashboard.php:654; the BFF
    // has no full PHP session, so the tier gates use Account directly (mirrors
    // /nostr/publish) and dashboardGetCredits() is called explicitly to
    // populate $_SESSION['sd_credits'] for the @sd/core helper to read.
    $sub->post('/ai/generate', function (Request $request, Response $response) {
      global $link;
      global $awsConfig;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return dashboardError($response, 'missing-identity', 400);
      }

      $account = new Account($npub, $link);
      if (!$account->accountExists()) {
        return dashboardError($response, 'not-found', 404);
      }

      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        if (dashboardGetDaysRemaining() <= 0 || $account->getPerFileUploadLimit() <= 0) {
          return dashboardError($response, 'Your account has expired', 403);
        }
        $level = (int) $account->getAccountLevel()->value;
        // Base AI Studio gate: Creator (1), Professional (2), Advanced (10),
        // Admin (99) — mirrors the legacy Permission::validatePermissionsLevelAny(2, 1, 10, 99).
        if (!in_array($level, [1, 2, 10, 99], true)) {
          return dashboardError($response, 'You do not have permission to generate AI images', 403);
        }

        $body = $request->getParsedBody();
        if (empty($body['model']) || empty($body['prompt']) || !isset($body['title'])) {
          return dashboardError($response, 'Missing required parameters');
        }
        $model = $body['model'];
        $prompt = $body['prompt'];
        $title = $body['title'];
        $negativePrompt = $body['negative_prompt'] ?? '';
        $ar = $body['aspect_ratio'] ?? '';
        $preset = $body['style_preset'] ?? '';

        // Per-model tier gating mirrors the legacy route. Lightning + SD-XL
        // base share the base AI gate; FLUX is narrower (no Professional).
        $creatorsModels = [
          '@cf/bytedance/stable-diffusion-xl-lightning',
          '@cf/stabilityai/stable-diffusion-xl-base-1.0',
        ];
        $advancedModels = ['@cf/black-forest-labs/flux-1-schnell'];
        if (in_array($model, $creatorsModels, true) && !in_array($level, [1, 2, 10, 99], true)) {
          return dashboardError($response, "You do not have permission to generate AI images using the {$model} model", 403);
        }
        if (in_array($model, $advancedModels, true) && !in_array($level, [1, 10, 99], true)) {
          return dashboardError($response, "You do not have permission to generate AI images using the {$model} model", 403);
        }

        // @sd/core consumes credits — populate $_SESSION['sd_credits'] so the
        // legacy generator can read it.
        if ($model === '@sd/core') {
          dashboardGetCredits($link);
          if (intval($_SESSION['sd_credits'] ?? 0) <= 3) {
            return dashboardError($response, 'You do not have enough credits to generate AI images');
          }
        }

        if ($model === '@sd/core') {
          $aiImage = dashboardGenerateSDCoreImage(
            $prompt,
            $negativePrompt,
            $ar,
            $preset,
            0,
            $account,
            $link,
            $awsConfig,
          );
          $_SESSION['sd_credits'] -= 3;
        } else {
          $aiImage = dashboardGenerateAIImage($model, $prompt, $title, $link, $awsConfig);
        }
        return dashboardJson($response, $aiImage);
      } catch (\Throwable $e) {
        error_log($e->getMessage());
        return dashboardError($response, 'Failed to generate AI image', 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });
  });

  // Multipart S3 — large files. Mirrors api/v2/routes_s3.php /multipart/*
  // (production: session-cookie auth + permission level 1/10/99). Here we
  // identify the user via X-Accounts-Npub and call S3Multipart with the npub
  // explicitly. Each route still gates on the same account-level whitelist.
  $group->group('/uploads/multipart', function (RouteCollectorProxy $mp) {
    // POST /multipart — create multipart upload.
    $mp->post('', function (Request $request, Response $response) {
      global $link;
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return jsonResponse($response, 'error', 'missing-identity', new stdClass(), 400);
      }
      $data = $request->getParsedBody();
      if (empty($data['filename']) || empty($data['type'])) {
        return jsonResponse($response, 'error', 'Missing required fields: filename, type', new stdClass(), 400);
      }
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $account = new Account($npub, $link);
        if (!$account->accountExists()) {
          return jsonResponse($response, 'error', 'not-found', new stdClass(), 404);
        }
        $level = (int) $account->getAccountLevel()->value;
        if (!in_array($level, [1, 10, 99], true)) {
          return jsonResponse($response, 'error', 'forbidden', new stdClass(), 403);
        }
        $s3Multipart = $this->get('s3Multipart');
        $result = $s3Multipart->createMultipartUpload(
          $data['filename'],
          $data['type'],
          $data['metadata'] ?? [],
          $npub,
        );
        if (!$result) {
          return jsonResponse($response, 'error', 'Failed to create multipart upload', new stdClass(), 500);
        }
        return jsonResponse($response, 'success', 'Multipart upload created', $result);
      } catch (\Throwable $e) {
        error_log('uploads/multipart create: ' . $e->getMessage());
        return jsonResponse($response, 'error', 'Failed to create multipart upload', new stdClass(), 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });

    // GET /multipart/{uploadId}/{partNumber}?key=... — sign one part URL.
    $mp->get('/{uploadId}/{partNumber:[0-9]+}', function (Request $request, Response $response, array $args) {
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return jsonResponse($response, 'error', 'missing-identity', new stdClass(), 400);
      }
      $uploadId = $args['uploadId'];
      $partNumber = (int) $args['partNumber'];
      $key = $request->getQueryParams()['key'] ?? '';
      if (empty($uploadId) || empty($key) || $partNumber < 1) {
        return jsonResponse($response, 'error', 'Missing required parameters', new stdClass(), 400);
      }
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3Multipart = $this->get('s3Multipart');
        $result = $s3Multipart->signPart($uploadId, $key, $partNumber, $npub);
        if (!$result) {
          return jsonResponse($response, 'error', 'Failed to sign part', new stdClass(), 500);
        }
        return jsonResponse($response, 'success', 'Part signed', $result);
      } catch (\Throwable $e) {
        error_log('uploads/multipart sign: ' . $e->getMessage());
        return jsonResponse($response, 'error', 'Failed to sign part', new stdClass(), 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });

    // GET /multipart/{uploadId}/status?key=... — completion-status probe.
    $mp->get('/{uploadId}/status', function (Request $request, Response $response, array $args) {
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return jsonResponse($response, 'error', 'missing-identity', new stdClass(), 400);
      }
      $uploadId = $args['uploadId'];
      $key = $request->getQueryParams()['key'] ?? '';
      if (empty($uploadId) || empty($key)) {
        return jsonResponse($response, 'error', 'Missing required parameters', new stdClass(), 400);
      }
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3Multipart = $this->get('s3Multipart');
        $completionStatus = $s3Multipart->checkForCompletedUpload($key, $npub);
        if ($completionStatus) {
          if ($completionStatus['status'] === 'fully_completed') {
            return jsonResponse($response, 'success', 'Upload fully completed', [
              'completed' => true,
              'fileData' => $completionStatus,
            ]);
          }
          if ($completionStatus['status'] === 's3_completed_needs_processing') {
            return jsonResponse($response, 'success', 'Upload exists in S3, call completion', [
              'call_completion' => true,
              'key' => $completionStatus['key'],
              'uploadInfo' => $completionStatus['uploadInfo'],
            ]);
          }
        }
        return jsonResponse($response, 'success', 'Upload not completed', ['completed' => false]);
      } catch (\Throwable $e) {
        error_log('uploads/multipart status: ' . $e->getMessage());
        return jsonResponse($response, 'error', 'Failed to check upload status', new stdClass(), 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });

    // GET /multipart/{uploadId}?key=... — list uploaded parts.
    $mp->get('/{uploadId}', function (Request $request, Response $response, array $args) {
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return jsonResponse($response, 'error', 'missing-identity', new stdClass(), 400);
      }
      $uploadId = $args['uploadId'];
      $key = $request->getQueryParams()['key'] ?? '';
      if (empty($uploadId) || empty($key)) {
        return jsonResponse($response, 'error', 'Missing required parameters', new stdClass(), 400);
      }
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3Multipart = $this->get('s3Multipart');
        $result = $s3Multipart->listParts($uploadId, $key, $npub);
        if ($result === false) {
          return jsonResponse($response, 'error', 'Failed to list parts', new stdClass(), 500);
        }
        return jsonResponse($response, 'success', 'Parts listed', $result);
      } catch (\Throwable $e) {
        error_log('uploads/multipart list: ' . $e->getMessage());
        return jsonResponse($response, 'error', 'Failed to list parts', new stdClass(), 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });

    // POST /multipart/{uploadId}/complete?key=... — finalize.
    $mp->post('/{uploadId}/complete', function (Request $request, Response $response, array $args) {
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return jsonResponse($response, 'error', 'missing-identity', new stdClass(), 400);
      }
      $uploadId = $args['uploadId'];
      $key = $request->getQueryParams()['key'] ?? '';
      $data = $request->getParsedBody();
      if (empty($uploadId) || empty($key) || empty($data['parts'])) {
        return jsonResponse($response, 'error', 'Missing required parameters', new stdClass(), 400);
      }
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3Multipart = $this->get('s3Multipart');
        $result = $s3Multipart->completeMultipartUpload($uploadId, $key, $data['parts'], $npub);
        if (!$result) {
          return jsonResponse($response, 'error', 'Failed to complete multipart upload', new stdClass(), 500);
        }
        return jsonResponse($response, 'success', 'Multipart upload completed', $result);
      } catch (\Throwable $e) {
        error_log('uploads/multipart complete: ' . $e->getMessage());
        return jsonResponse($response, 'error', 'Failed to complete multipart upload', new stdClass(), 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });

    // DELETE /multipart/{uploadId}?key=... — abort.
    $mp->delete('/{uploadId}', function (Request $request, Response $response, array $args) {
      $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
      if ($npub === '') {
        return jsonResponse($response, 'error', 'missing-identity', new stdClass(), 400);
      }
      $uploadId = $args['uploadId'];
      $key = $request->getQueryParams()['key'] ?? '';
      if (empty($uploadId) || empty($key)) {
        return jsonResponse($response, 'error', 'Missing required parameters', new stdClass(), 400);
      }
      $prevNpub = $_SESSION['usernpub'] ?? null;
      $_SESSION['usernpub'] = $npub;
      try {
        $s3Multipart = $this->get('s3Multipart');
        $result = $s3Multipart->abortMultipartUpload($uploadId, $key, $npub);
        if (!$result) {
          return jsonResponse($response, 'error', 'Failed to abort multipart upload', new stdClass(), 500);
        }
        return jsonResponse($response, 'success', 'Multipart upload aborted', new stdClass());
      } catch (\Throwable $e) {
        error_log('uploads/multipart abort: ' . $e->getMessage());
        return jsonResponse($response, 'error', 'Failed to abort multipart upload', new stdClass(), 500);
      } finally {
        if ($prevNpub === null) {
          unset($_SESSION['usernpub']);
        } else {
          $_SESSION['usernpub'] = $prevNpub;
        }
      }
    });
  })->add(new ClientInfoMiddleware());

  // Uploads — small files via XHR (multipart/form-data, body-aware HMAC).
  // Large files use /accounts/uploads/multipart/* (bodyless HMAC) wired by
  // HmacAuthMiddlewareBodyless in a separate group below.
  $group->post('/uploads/uppy', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultimediaUpload.class.php';

    $npub = trim((string) ($request->getHeaderLine('X-Accounts-Npub') ?? ''));
    if ($npub === '') {
      return uppyResponse($response, 'error', 'missing-identity', new stdClass(), 400);
    }

    $files = $request->getUploadedFiles();
    $metadata = $request->getParsedBody();
    if (empty($files)) {
      return uppyResponse($response, 'error', 'No files provided', new stdClass(), 400);
    }

    $prevNpub = $_SESSION['usernpub'] ?? null;
    $_SESSION['usernpub'] = $npub;
    try {
      $s3 = new S3Service($awsConfig);
      $upload = new MultimediaUpload($link, $s3, true, $npub, $awsConfig);
      $upload->setPsrFiles($files, $metadata);
      $upload->setUppyMetadata($metadata);
      [$status, $code, $message] = $upload->uploadFiles();
      if (!$status) {
        return uppyResponse($response, 'error', $message, new stdClass(), $code);
      }
      $data = $upload->getUploadedFiles();
      return uppyResponse($response, 'success', $message, $data, $code);
    } catch (\Throwable $e) {
      error_log('uploads/uppy failed: ' . $e->getMessage());
      return uppyResponse($response, 'error', 'Upload failed', new stdClass(), 500);
    } finally {
      if ($prevNpub === null) {
        unset($_SESSION['usernpub']);
      } else {
        $_SESSION['usernpub'] = $prevNpub;
      }
    }
  })->add(new ClientInfoMiddleware());
})->add(new HmacAuthMiddleware());
