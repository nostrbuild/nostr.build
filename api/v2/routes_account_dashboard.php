<?php
/**
 * Account Dashboard API Routes
 * Migrated from account/api.php to PSR-7 Slim routes
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ImageCatalogManager.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/imageproc.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrClient.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Credits.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// --- Response Helpers ---

function dashboardJson(Response $response, $data, int $statusCode = 200): Response
{
  $response->getBody()->write(json_encode($data));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

function dashboardError(Response $response, string $message, int $statusCode = 400): Response
{
  return dashboardJson($response, ['error' => $message], $statusCode);
}

// --- Business Logic Helpers ---

function buildFileListEntry(array $row): array
{
  $type = getFileTypeFromName($row['image']);
  if ($type === 'unknown') {
    $type = explode('/', $row['mime_type'])[0];
  }

  $image = $row['image'];
  $parsed_url = parse_url($image);
  $filename = pathinfo($parsed_url['path'], PATHINFO_BASENAME);
  $professional_type = 'professional_account_' . $type;

  try {
    $base_url = SiteConfig::getFullyQualifiedUrl($professional_type);
  } catch (\Exception $e) {
    error_log($e->getMessage());
    $base_url = SiteConfig::ACCESS_SCHEME . "://" . SiteConfig::DOMAIN_NAME . "/p/";
  }

  $image_url = $base_url . $filename;
  $thumb_url = SiteConfig::getThumbnailUrl($professional_type) . $filename;

  $resolutionToWidth = [
    "240p"  => "426",
    "360p"  => "640",
    "480p"  => "854",
    "720p"  => "1280",
    "1080p" => "1920",
  ];

  $srcset = [];
  $responsive = [];
  foreach ($resolutionToWidth as $resolution => $width) {
    $srcset[] = htmlspecialchars(SiteConfig::getResponsiveUrl($professional_type, $resolution) . $filename . " {$width}w");
    $responsive[$resolution] = htmlspecialchars(SiteConfig::getResponsiveUrl($professional_type, $resolution) . $filename);
  }

  return [
    "id" => $row['id'],
    "flag" => ($row['flag'] === '1') ? 1 : 0,
    "name" => $filename,
    "url" => $image_url,
    "thumb" => $type === 'image' ? $thumb_url : null,
    "responsive" => $responsive,
    "mime" => $row['mime_type'],
    "size" => $row['file_size'],
    "sizes" => $type === 'image' ? '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw' : null,
    "srcset" => $type === 'image' ? implode(", ", $srcset) : null,
    "width" => $row['media_width'] ?? null,
    "height" => $row['media_height'] ?? null,
    "media_type" => $type,
    "blurhash" => $type === 'image' ? $row['blurhash'] : null,
    "sha256_hash" => $row['sha256_hash'],
    "created_at" => $row['created_at'],
    "title" => $row['title'],
    "ai_prompt" => $type === 'image' ? $row['ai_prompt'] : null,
    "description" => $row['description'],
    "loaded" => false,
    "show" => true,
    "associated_notes" => $row['associated_notes'] ?? null,
  ];
}

function dashboardListFiles(string $folderName, $link, $start = null, $limit = null, $filter = null): array
{
  $folders = new UsersImagesFolders($link);
  $images = new UsersImages($link);

  $folderId = ($folderName !== "Home: Main Folder")
    ? $folders->findFolderByNameOrCreate($_SESSION['usernpub'], $folderName)
    : null;

  $imgArray = $images->getFiles($_SESSION['usernpub'], $folderId, $start, $limit, $filter);

  return array_map('buildFileListEntry', $imgArray);
}

function dashboardGetAccountData($link, $account): array
{
  $credits = dashboardGetCredits($link);
  $info = $account->getAccountInfo();

  return [
    "userId" => $info['id'],
    "name" => $info['nym'],
    "npub" => $info['usernpub'],
    "pfpUrl" => $info['ppic'],
    "wallet" => $info['wallet'],
    "defaultFolder" => $info['default_folder'] ?? "",
    "allowNostrLogin" => $info['allow_npub_login'],
    "npubVerified" => $info['npub_verified'],
    "accountLevel" => $info['acctlevel'],
    "accountFlags" => $info['accflags'],
    "remainingDays" => $info['remaining_subscription_days'],
    "storageUsed" => $info['used_storage_space'],
    "storageLimit" => $info['storage_space_limit'],
    "totalStorageLimit" => $info['storage_space_limit'] === PHP_INT_MAX ? "Unlimited" : formatSizeUnits($info['storage_space_limit']),
    "availableCredits" => $credits['available'],
    "debitedCredits" => $credits['debited'] ?? 0,
    "creditedCredits" => $credits['credited'] ?? 0,
    "referralCode" => $account->getAccountReferralCode(),
    "nlSubEligible" => $info['nl_sub_eligible'] ?? false,
    "nlSubActivated" => $info['nl_sub_activated'] ?? false,
    "nlSubInfo" => $info['nl_sub_info'] ?? null,
  ];
}

function dashboardGetCredits($link): array
{
  $__t = hrtime(true);
  $apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
  $credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
  $balance = $credits->getCreditsBalance();
  apiTimingLog('dashboardGetCredits: Credits API call', $__t);
  $_SESSION['sd_credits'] = $balance['available'];
  return $balance;
}

function dashboardGetMediaStats(string $mediaId, string $period, string $interval, string $groupBy, $link): string
{
  $userNpub = $_SESSION['usernpub'];
  $mediaIdInt = intval($mediaId);

  $stmt = $link->prepare("SELECT * FROM users_images WHERE id = ? AND usernpub = ?");
  $stmt->bind_param('is', $mediaIdInt, $userNpub);
  $stmt->execute();
  $result = $stmt->get_result();

  if ($result->num_rows === 0) {
    return json_encode(["error" => "Media not found"]);
  }

  $row = $result->fetch_assoc();
  $type = getFileTypeFromName($row['image']);
  if ($type === 'unknown') {
    $type = explode('/', $row['mime_type'])[0];
  }

  $mediaURL = SiteConfig::getFullyQualifiedUrl("professional_account_{$type}") . $row['image'];
  $statsURL = "{$mediaURL}/stats?period={$period}&interval={$interval}&group_by={$groupBy}";

  $bearer = signApiRequest($_SERVER['NB_HMAC_SECRETS'], $statsURL, 'GET');

  $ch = curl_init($statsURL);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$bearer}"]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);

  if ($response === false) {
    $error = curl_error($ch);
    $ch = null;
    return json_encode(["error" => "cURL request failed: {$error}"]);
  }

  $ch = null;
  return $response;
}

function dashboardBuildReturnFile(array $fileData): array
{
  $resolutionToWidth = [
    "240p"  => "426",
    "360p"  => "640",
    "480p"  => "854",
    "720p"  => "1280",
    "1080p" => "1920",
  ];

  return [
    "id" => $fileData[0]['id'],
    "flag" => 0,
    "name" => $fileData[0]['name'],
    "mime" => $fileData[0]['mime'],
    "url" => $fileData[0]['url'],
    "thumb" => $fileData[0]['thumbnail'],
    "responsive" => $fileData[0]['responsive'],
    "size" => $fileData[0]['size'],
    "sizes" => '(max-width: 426px) 100vw, (max-width: 640px) 100vw, (max-width: 854px) 100vw, (max-width: 1280px) 50vw, 33vw',
    "srcset" => implode(", ", array_map(function ($resolution) use ($fileData, $resolutionToWidth) {
      return htmlspecialchars($fileData[0]['responsive'][$resolution] . " {$resolutionToWidth[$resolution]}w");
    }, array_keys($fileData[0]['responsive']))),
    "width" => $fileData[0]['dimensions']['width'] ?? null,
    "height" => $fileData[0]['dimensions']['height'] ?? null,
    "media_type" => $fileData[0]['media_type'],
    "blurhash" => $fileData[0]['blurhash'],
    "sha256_hash" => $fileData[0]['original_sha256'],
    "created_at" => date('Y-m-d H:i:s'),
    "title" => $fileData[0]['title'] ?? '',
    "ai_prompt" => $fileData[0]['ai_prompt'] ?? '',
    "description" => $fileData[0]['description'] ?? '',
    "loaded" => false,
    "show" => true,
    "associated_notes" => null,
  ];
}

function dashboardImportFromURL(string $url, string $folder, string $title, string $prompt, $link, $awsConfig): array
{
  $s3 = new S3Service($awsConfig);
  $upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub'], $awsConfig);
  if (!empty($folder)) {
    $upload->setDefaultFolderName($folder);
  }

  $upload->uploadFileFromUrl(url: $url, title: $title, ai_prompt: $prompt);

  $fileData = $upload->getUploadedFiles();
  if (empty($fileData)) {
    throw new \Exception("Failed to import media from URL");
  }

  return dashboardBuildReturnFile($fileData);
}

function dashboardGenerateAIImage(string $model, string $prompt, string $title, $link, $awsConfig): array
{
  $s3 = new S3Service($awsConfig);
  $apiUrl = $_SERVER['AI_GEN_API_ENDPOINT'];
  $requestBody = json_encode([
    "prompt" => $prompt,
    "model" => $model,
    "npub" => $_SESSION['usernpub'],
  ]);

  $bearer = signApiRequest($_SERVER['AI_GEN_API_HMAC_KEY'], $apiUrl, 'POST', $requestBody);

  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer {$bearer}"]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

  if ($response === false) {
    $error = curl_error($ch);
    $ch = null;
    throw new \Exception("cURL request failed: {$error}");
  }
  $ch = null;

  if ($httpCode !== 200 && $contentType === 'application/json') {
    $responseJson = json_decode($response, true);
    throw new \Exception("AI Image generation failed: HTTP {$httpCode} - {$responseJson['message']}");
  }

  $tempFile = generateUniqueFilename("ai_image_", sys_get_temp_dir());
  if (in_array($contentType, ['image/png', 'image/jpeg', 'image/webp'])) {
    file_put_contents($tempFile, $response);
  } else {
    throw new \Exception("AI Image generation failed: Unexpected content type: {$contentType}");
  }

  $upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub'], $awsConfig);
  $upload->setDefaultFolderName("AI: Generated Images");
  $upload->setRawFiles([[
    'input_name' => 'ai_image',
    'name' => basename($tempFile),
    'type' => 'image/png',
    'tmp_name' => realpath($tempFile),
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tempFile),
    'title' => $title ?? '',
    'ai_prompt' => $prompt ?? '',
  ]]);

  [$status, $code, $message] = $upload->uploadFiles(true);
  if (!$status) {
    throw new \Exception("Failed to upload AI generated image: {$message} {$code}");
  }

  $fileData = $upload->getUploadedFiles();
  if (empty($fileData)) {
    throw new \Exception("Failed to import media from URL");
  }

  return dashboardBuildReturnFile($fileData);
}

function dashboardGenerateSDCoreImage(string $prompt, string $negativePrompt, string $ar, string $preset, int $seed, Account $account, $link, $awsConfig): array
{
  // Validate parameters
  if (empty($prompt) || strlen($prompt) > 10000) {
    throw new \Exception("Prompt is required and must be less than 10000 characters");
  }
  if (!empty($negativePrompt) && strlen($negativePrompt) > 10000) {
    throw new \Exception("Negative prompt must be less than 10000 characters");
  }
  if (!empty($ar) && !in_array($ar, ["21:9", "16:9", "3:2", "5:4", "1:1", "4:5", "2:3", "9:16", "9:21"])) {
    throw new \Exception("Invalid aspect ratio");
  }
  if (!empty($preset) && !in_array($preset, ["enhance", "anime", "photographic", "digital-art", "comic-book", "fantasy-art", "line-art", "analog-film", "neon-punk", "isometric", "low-poly", "origami", "modeling-compound", "cinematic", "3d-model", "pixel-art", "tile-texture"])) {
    throw new \Exception("Invalid style preset");
  }
  if ($seed < 0 || $seed > 4294967294) {
    throw new \Exception("Invalid seed value");
  }

  $s3 = new S3Service($awsConfig);
  $apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
  $apiUrl = $apiBase . '/sd/core';

  $usernpub = $account->getNpub();
  $level = $account->getAccountLevelInt();
  $subscriptionPeriod = $account->getSubscriptionPeriod();

  $requestBodyArray = [
    "user_npub" => $usernpub,
    "app_id" => "nostr.build",
    "app_version" => "1.0.0-beta",
    "user_level" => $level,
    "user_sub_period" => $subscriptionPeriod,
    "prompt" => $prompt,
  ];
  if (!empty($negativePrompt)) $requestBodyArray['negative_prompt'] = $negativePrompt;
  if (!empty($ar)) $requestBodyArray['aspect_ratio'] = $ar;
  if (!empty($preset)) $requestBodyArray['style_preset'] = $preset;
  if ($seed > 0) $requestBodyArray['seed'] = $seed;

  $requestBody = json_encode($requestBodyArray);
  $bearer = signApiRequest($_SERVER['AI_GEN_API_HMAC_KEY'], $apiUrl, 'POST', $requestBody);

  $ch = curl_init($apiUrl);
  $customHeaders = [];
  curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$customHeaders) {
    $parts = explode(':', $headerLine, 2);
    if (count($parts) === 2) {
      $headerName = strtolower(trim($parts[0]));
      $headerValue = trim($parts[1]);
      if (in_array($headerName, ['x-sd-finish-reason', 'x-sd-seed', 'x-sd-available-balance', 'x-sd-debited', 'x-sd-transaction-id'])) {
        $customHeaders[$headerName] = $headerValue;
      }
    }
    return strlen($headerLine);
  });

  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer {$bearer}"]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  if ($response === false) {
    $error = curl_error($ch);
    $ch = null;
    throw new \Exception("cURL request failed: {$error}");
  }

  $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  if ($httpCode !== 200 && $contentType === 'application/json') {
    throw new \Exception("SD Core Image generation failed: HTTP {$httpCode} - {$response}");
  }
  $ch = null;

  $transactionId = $customHeaders['x-sd-transaction-id'] ?? '';

  $tempFile = generateUniqueFilename("ai_image_", sys_get_temp_dir());
  if (in_array($contentType, ['image/png', 'image/jpeg', 'image/webp'])) {
    file_put_contents($tempFile, $response);
  } else {
    throw new \Exception("SD Core Image generation failed: Unexpected content type: {$contentType}");
  }

  $upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub'], $awsConfig);
  $upload->setDefaultFolderName("AI: Generated Images");
  $upload->setRawFiles([[
    'input_name' => 'ai_image',
    'name' => basename($tempFile),
    'type' => 'image/png',
    'tmp_name' => realpath($tempFile),
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tempFile),
    'title' => '',
    'ai_prompt' => $prompt ?? '',
  ]]);

  [$status, $code, $message] = $upload->uploadFiles();
  if (!$status) {
    throw new \Exception("Failed to upload SD Core generated image: {$message} {$code}");
  }

  $fileData = $upload->getUploadedFiles();
  if (empty($fileData)) {
    throw new \Exception("Failed to import media from URL");
  }

  $mediaId = $fileData[0]['name'];
  $credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
  $credits->updateTransactionWithMediaId($transactionId, $mediaId);

  return dashboardBuildReturnFile($fileData);
}

// --- Lazy Account loader ---
// Only creates Account + fetches subscription days when a route actually needs it.
// Most routes (files, folders, stats) only need the session npub — no DB lookup.
function dashboardGetAccount(): Account
{
  static $account = null;
  if ($account === null) {
    $__t = hrtime(true);
    global $link;
    $account = new Account($_SESSION['usernpub'], $link);
    apiTimingLog('dashboardGetAccount: new Account() constructor', $__t);
  }
  return $account;
}

function dashboardGetDaysRemaining(): int
{
  static $days = null;
  if ($days === null) {
    try {
      $days = dashboardGetAccount()->getRemainingSubscriptionDays();
    } catch (\Exception $e) {
      error_log($e->getMessage());
      $days = 0;
    }
  }
  return $days;
}

// --- Routes ---
$app->group('/account/dashboard', function (RouteCollectorProxy $group) {

  // GET /files - List files by folder name
  $group->get('/files', function (Request $request, Response $response) {
    $__t = hrtime(true);
    global $link;
    $params = $request->getQueryParams();

    $folder = $params['folder'] ?? null;
    if (empty($folder)) {
      return dashboardError($response, 'Missing folder parameter');
    }

    $start = isset($params['start']) ? max(0, intval($params['start'])) : null;
    $limit = isset($params['limit']) ? min(500, max(1, intval($params['limit']))) : null;
    $filter = $params['filter'] ?? null;

    $allowedFilters = ['all', 'images', 'videos', 'audio', 'gifs', 'documents', 'archives', 'others'];
    if ($filter !== null && !in_array($filter, $allowedFilters)) {
      return dashboardError($response, 'Invalid filter value');
    }

    $files = dashboardListFiles($folder, $link, $start, $limit, $filter);
    apiTimingLog('route /files handler', $__t);
    return dashboardJson($response, $files);
  });

  // GET /folders - List folders with stats
  $group->get('/folders', function (Request $request, Response $response) {
    $__t = hrtime(true);
    global $link;
    $folders = new UsersImagesFolders($link);
    $folderList = $folders->getFoldersWithStats($_SESSION['usernpub']);
    apiTimingLog('route /folders DB query', $__t);

    $result = array_map(function ($folder) {
      $folderName = $folder['folder'];
      $firstChar = mb_substr($folderName, 0, 1, 'UTF-8');
      $folderIcon = mb_strlen($firstChar, 'UTF-8') === 1 ? strtoupper($firstChar) : '#';
      return [
        "name" => $folderName,
        "icon" => $folderIcon,
        "route" => "#f=" . urlencode($folderName),
        "id" => $folder['id'],
        "allowDelete" => true,
        "stats" => [
          "allSize" => (int)($folder['allSize'] ?? 0),
          "all" => (int)($folder['all'] ?? 0),
          "imagesSize" => (int)($folder['imageSize'] ?? 0),
          "images" => (int)($folder['images'] ?? 0),
          "gifsSize" => (int)($folder['gifSize'] ?? 0),
          "gifs" => (int)($folder['gifs'] ?? 0),
          "videosSize" => (int)($folder['videoSize'] ?? 0),
          "videos" => (int)($folder['videos'] ?? 0),
          "audioSize" => (int)($folder['audioSize'] ?? 0),
          "audio" => (int)($folder['audio'] ?? 0),
          "documentsSize" => (int)($folder['documentSize'] ?? 0),
          "documents" => (int)($folder['documents'] ?? 0),
          "archivesSize" => (int)($folder['archiveSize'] ?? 0),
          "archives" => (int)($folder['archives'] ?? 0),
          "othersSize" => (int)($folder['otherSize'] ?? 0),
          "others" => (int)($folder['others'] ?? 0),
          "publicCount" => (int)($folder['publicCount'] ?? 0),
        ],
      ];
    }, $folderList);

    apiTimingLog('route /folders total', $__t);
    return dashboardJson($response, $result);
  });

  // GET /profile - Get account profile info
  $group->get('/profile', function (Request $request, Response $response) {
    $__t = hrtime(true);
    global $link;
    $account = dashboardGetAccount();
    apiTimingLog('route /profile Account loaded', $__t);
    $data = dashboardGetAccountData($link, $account);
    apiTimingLog('route /profile total', $__t);
    return dashboardJson($response, $data);
  });

  // GET /folders/stats - Get total folder statistics
  $group->get('/folders/stats', function (Request $request, Response $response) {
    $__t = hrtime(true);
    global $link;
    try {
      $usersFoldersTable = new UsersImagesFolders($link);
      $usersFoldersStats = $usersFoldersTable->getTotalStats($_SESSION['usernpub']);
      apiTimingLog('route /folders/stats total', $__t);
      return dashboardJson($response, ['totalStats' => $usersFoldersStats]);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to get folders stats', 500);
    }
  });

  // GET /credits/history - Get credits transaction history
  $group->get('/credits/history', function (Request $request, Response $response) {
    global $link;
    $params = $request->getQueryParams();
    try {
      $apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
      $credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
      $txType = $params['type'] ?? 'all';
      $txLimit = isset($params['limit']) ? min(500, max(1, intval($params['limit']))) : null;
      $txOffset = isset($params['offset']) ? max(0, intval($params['offset'])) : null;
      $txHistory = $credits->getTransactionsHistory($txType, $txLimit, $txOffset);
      return dashboardJson($response, $txHistory);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to get credits transaction history', 500);
    }
  });

  // GET /credits/invoice - Get credits invoice
  $group->get('/credits/invoice', function (Request $request, Response $response) {
    global $link;
    $params = $request->getQueryParams();
    $creditsAmount = isset($params['credits']) ? intval($params['credits']) : 0;
    if ($creditsAmount <= 0) {
      return dashboardError($response, 'Invalid credits amount');
    }
    try {
      $apiBase = substr($_SERVER['AI_GEN_API_ENDPOINT'], 0, strrpos($_SERVER['AI_GEN_API_ENDPOINT'], '/'));
      $credits = new Credits($_SESSION['usernpub'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
      $invoice = $credits->getInvoice($creditsAmount);
      return dashboardJson($response, $invoice);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to get credits invoice', 500);
    }
  });

  // GET /credits/balance - Get credits balance
  $group->get('/credits/balance', function (Request $request, Response $response) {
    global $link;
    try {
      $balance = dashboardGetCredits($link);
      return dashboardJson($response, $balance);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to get credits balance', 500);
    }
  });

  // GET /media/{media_id}/stats - Get media statistics
  $group->get('/media/{media_id}/stats', function (Request $request, Response $response, array $args) {
    global $link;
    $perm = new Permission();
    $account = dashboardGetAccount();
    $daysRemaining = dashboardGetDaysRemaining();

    if ($daysRemaining <= 0) {
      return dashboardError($response, 'Your account has expired', 403);
    }
    if (!$perm->validatePermissionsLevelAny(1, 2, 10, 99)) {
      return dashboardError($response, 'You do not have permission to get media stats', 403);
    }

    $mediaId = $args['media_id'];
    $params = $request->getQueryParams();

    $allowedPeriods = ['1h', '3h', '6h', '12h', 'day', 'week', 'month', '3months'];
    $allowedIntervals = ['1m', '5m', '15m', '30m', '1h', '3h', '6h', '12h', '1d'];
    $allowedGroupBy = ['time', 'referrer', 'country', 'device'];

    $period = $params['period'] ?? '1h';
    $interval = $params['interval'] ?? '1m';
    $groupBy = $params['group_by'] ?? 'time';

    if (!in_array($period, $allowedPeriods) || !in_array($interval, $allowedIntervals) || !in_array($groupBy, $allowedGroupBy)) {
      return dashboardError($response, 'Invalid stats parameters');
    }

    if (empty($mediaId) || !is_numeric($mediaId) || intval($mediaId) <= 0) {
      return dashboardError($response, 'Missing media_id parameter');
    }

    try {
      $mediaStats = dashboardGetMediaStats($mediaId, $period, $interval, $groupBy, $link);
      $json = json_decode($mediaStats, true, 512, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
      if ($json['success'] !== true || $json['status'] !== 200) {
        throw new \Exception("Failed to get media stats");
      }
      $resultString = json_encode($json['results'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
      return dashboardJson($response, $resultString);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to get media stats', 500);
    }
  });

  // POST /ai/generate - Generate AI image
  $group->post('/ai/generate', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $perm = new Permission();
    $account = dashboardGetAccount();
    $daysRemaining = dashboardGetDaysRemaining();

    if ($daysRemaining <= 0 || $account->getPerFileUploadLimit() <= 0) {
      return dashboardError($response, 'Your account has expired', 403);
    }
    if (!$perm->validatePermissionsLevelAny(2, 1, 10, 99)) {
      return dashboardError($response, 'You do not have permission to generate AI images', 403);
    }

    $body = $request->getParsedBody();

    // Check model-specific permissions
    $creatorsModels = ["@cf/bytedance/stable-diffusion-xl-lightning", "@cf/stabilityai/stable-diffusion-xl-base-1.0"];
    if (isset($body['model']) && in_array($body['model'], $creatorsModels)) {
      if (!$perm->validatePermissionsLevelAny(2, 1, 10, 99)) {
        return dashboardError($response, "You do not have permission to generate AI images using the {$body['model']} model", 403);
      }
    }
    $advancedModels = ["@cf/black-forest-labs/flux-1-schnell"];
    if (isset($body['model']) && in_array($body['model'], $advancedModels)) {
      if (!$perm->validatePermissionsLevelAny(1, 10, 99)) {
        return dashboardError($response, "You do not have permission to generate AI images using the {$body['model']} model", 403);
      }
    }

    if (empty($body['model']) || empty($body['prompt']) || !isset($body['title'])) {
      return dashboardError($response, 'Missing required parameters');
    }

    $model = $body['model'];
    $prompt = $body['prompt'];
    $title = $body['title'];
    $negativePrompt = $body['negative_prompt'] ?? '';
    $ar = $body['aspect_ratio'] ?? '';
    $preset = $body['style_preset'] ?? '';

    if ($model === "@sd/core" && intval($_SESSION['sd_credits'] ?? 0) <= 3) {
      return dashboardError($response, 'You do not have enough credits to generate AI images');
    }

    try {
      if ($model === "@sd/core") {
        $aiImage = dashboardGenerateSDCoreImage($prompt, $negativePrompt, $ar, $preset, 0, $account, $link, $awsConfig);
        $_SESSION['sd_credits'] -= 3;
      } else {
        $aiImage = dashboardGenerateAIImage($model, $prompt, $title, $link, $awsConfig);
      }
      return dashboardJson($response, $aiImage);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to generate AI image', 500);
    }
  });

  // POST /media/import - Import media from URL
  $group->post('/media/import', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $account = dashboardGetAccount();
    $daysRemaining = dashboardGetDaysRemaining();

    if ($daysRemaining <= 0 || $account->getPerFileUploadLimit() <= 0) {
      return dashboardError($response, 'Your account has expired', 403);
    }

    $body = $request->getParsedBody();
    if (empty($body['url']) || !isset($body['folder'])) {
      return dashboardError($response, 'Missing required parameters');
    }

    $url = $body['url'];
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return dashboardError($response, 'Invalid URL');
    }

    // Only allow http/https schemes to prevent SSRF via file://, ftp://, etc.
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower($scheme), ['http', 'https'])) {
      return dashboardError($response, 'Only http and https URLs are allowed');
    }

    try {
      $media = dashboardImportFromURL($url, $body['folder'], '', '', $link, $awsConfig);
      return dashboardJson($response, $media);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to import media from URL', 500);
    }
  });

  // POST /media/delete - Delete files and/or folders
  $group->post('/media/delete', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $body = $request->getParsedBody();

    $foldersToDelete = !empty($body['foldersToDelete']) ? json_decode($body['foldersToDelete']) : [];
    $imagesToDelete = !empty($body['imagesToDelete']) ? json_decode($body['imagesToDelete']) : [];

    $s3 = new S3Service($awsConfig);
    $icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
    $deletedFolders = array_map('intval', $icm->deleteFolders($foldersToDelete));
    $deletedImages = array_map('intval', $icm->deleteImages($imagesToDelete));

    return dashboardJson($response, [
      "action" => "delete",
      "deletedFolders" => $deletedFolders,
      "deletedImages" => $deletedImages,
    ]);
  });

  // POST /media/share - Share/unshare on creator page
  $group->post('/media/share', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $perm = new Permission();
    $daysRemaining = dashboardGetDaysRemaining();

    if ($daysRemaining <= 0) {
      return dashboardError($response, 'Your account has expired', 403);
    }
    if (!$perm->validatePermissionsLevelAny(1, 10, 99)) {
      return dashboardError($response, 'You do not have permission to share images', 403);
    }

    $body = $request->getParsedBody();
    $imagesToShare = !empty($body['imagesToShare']) ? (array)json_decode($body['imagesToShare']) : [];
    $shareFlag = !empty($body['shareFlag']) ? $body['shareFlag'] === 'true' : true;

    if (empty($imagesToShare)) {
      return dashboardError($response, 'No images to share');
    }

    $s3 = new S3Service($awsConfig);
    $icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
    $sharedImages = array_map('intval', $icm->shareImage($imagesToShare, (bool)$shareFlag));

    return dashboardJson($response, [
      "action" => "share_creator_page",
      "sharedImages" => $sharedImages,
    ]);
  });

  // POST /media/move - Move images to folder
  $group->post('/media/move', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $body = $request->getParsedBody();

    $imagesToMove = !empty($body['imagesToMove']) ? json_decode($body['imagesToMove']) : [];
    $destinationFolderId = !empty($body['destinationFolderId']) ? (int)$body['destinationFolderId'] : null;

    if (empty($imagesToMove)) {
      return dashboardError($response, 'Missing required parameters');
    }

    $s3 = new S3Service($awsConfig);
    $icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
    $movedImages = array_map('intval', $icm->moveImages($imagesToMove, $destinationFolderId));

    return dashboardJson($response, [
      "action" => "move_to_folder",
      "movedImages" => $movedImages,
    ]);
  });

  // POST /media/metadata - Update media title/description
  $group->post('/media/metadata', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $body = $request->getParsedBody();

    $mediaId = !empty($body['mediaId']) ? intval($body['mediaId']) : null;
    $title = $body['title'] ?? '';
    $description = $body['description'] ?? '';

    if ($mediaId === null) {
      return dashboardError($response, 'Missing required parameters');
    }

    $s3 = new S3Service($awsConfig);
    $icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
    $updatedMedia = $icm->updateMediaMetadata($mediaId, $title, $description);

    return dashboardJson($response, [
      "action" => "update_media_metadata",
      "updatedMedia" => $updatedMedia,
    ]);
  });

  // POST /media/poster - Upload video poster
  $group->post('/media/poster', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;

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

    try {
      $images = new UsersImages($link);
      $videoInfo = $images->getFile(npub: $_SESSION['usernpub'], fileId: $fileId);
      if (!$videoInfo) {
        return dashboardError($response, 'Video not found', 404);
      }

      $videoURL = SiteConfig::getFullyQualifiedUrl("professional_account_video") . $videoInfo['image'];

      // Move uploaded file to temp location for processing
      $tmpPath = sys_get_temp_dir() . '/' . uniqid('poster_') . '.jpg';
      $uploadedFile->moveTo($tmpPath);

      $imageProcessor = new ImageProcessor($tmpPath);
      $imageProcessor->save();
      $posterDimensions = $imageProcessor->getImageDimensions();
      $imageProcessor->optimiseImage();

      $images->update($fileId, ['media_width' => $posterDimensions['width'], 'media_height' => $posterDimensions['height']]);
      $sha256 = hash_file('sha256', $tmpPath);

      $objectKey = "{$videoInfo['image']}/poster.jpg";
      $objectBucketSuffix = SiteConfig::getBucketSuffix("professional_account_video");
      $objectBucket = $awsConfig['r2']['bucket'] . $objectBucketSuffix;

      $res = storeToR2Bucket(
        $tmpPath,
        $objectKey,
        $objectBucket,
        $awsConfig['r2']['endpoint'],
        $awsConfig['r2']['credentials']['key'],
        $awsConfig['r2']['credentials']['secret'],
        [
          'sha256' => $sha256,
          'npub' => $_SESSION['usernpub'],
          'videoUrl' => $videoURL,
        ],
      );

      if (!$res) {
        throw new \Exception("Failed to upload video poster");
      }

      $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
      $purger->purgeFiles($objectKey, true);

      // Clean up temp file after successful upload
      if (file_exists($tmpPath)) {
        @unlink($tmpPath);
      }

      $posterURL = $videoURL . "/poster.jpg";
      return dashboardJson($response, ["posterURL" => $posterURL, "dimensions" => $posterDimensions]);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      // Clean up temp file on failure
      if (isset($tmpPath) && file_exists($tmpPath)) {
        @unlink($tmpPath);
      }
      return dashboardError($response, 'Failed to upload video poster', 500);
    }
  });

  // POST /folders/rename - Rename a folder
  $group->post('/folders/rename', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $body = $request->getParsedBody();

    $folderToRename = !empty($body['foldersToRename']) ? json_decode($body['foldersToRename']) : null;
    $folderNames = !empty($body['folderNames']) ? json_decode($body['folderNames']) : null;

    if (empty($folderToRename) || empty($folderNames)) {
      return dashboardError($response, 'Missing required parameters');
    }

    $s3 = new S3Service($awsConfig);
    $icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
    $renamedFolders = array_map('intval', $icm->renameFolder($folderToRename, $folderNames));

    return dashboardJson($response, [
      "action" => "rename_folders",
      "renamedFolders" => $renamedFolders,
    ]);
  });

  // POST /folders/delete - Delete folders
  $group->post('/folders/delete', function (Request $request, Response $response) {
    global $link;
    global $awsConfig;
    $body = $request->getParsedBody();

    $foldersToDelete = !empty($body['foldersToDelete']) ? json_decode($body['foldersToDelete']) : [];
    if (empty($foldersToDelete)) {
      return dashboardError($response, 'No folders to delete');
    }

    $foldersToDelete = array_map('intval', $foldersToDelete);
    $s3 = new S3Service($awsConfig);
    $icm = new ImageCatalogManager($link, $s3, $_SESSION['usernpub']);
    $deletedFolders = array_map('intval', $icm->deleteFolders($foldersToDelete));

    return dashboardJson($response, [
      "action" => "delete_folders",
      "deletedFolders" => $deletedFolders,
    ]);
  });

  // POST /nostr/publish - Publish or delete Nostr events
  $group->post('/nostr/publish', function (Request $request, Response $response) {
    global $link;
    $perm = new Permission();
    $daysRemaining = dashboardGetDaysRemaining();

    if ($daysRemaining <= 0) {
      return dashboardError($response, 'Your account has expired', 403);
    }
    if (!$perm->validatePermissionsLevelAny(1, 2, 3, 10, 99)) {
      return dashboardError($response, 'You do not have permission to publish Nostr events', 403);
    }

    $body = $request->getParsedBody();
    $signedEvent = $body['event'] ?? null;
    $mediaIds = !empty($body['mediaIds']) ? json_decode($body['mediaIds']) : [];
    $eventId = $body['eventId'] ?? null;
    $eventCreatedAt = $body['eventCreatedAt'] ?? null;
    $eventContent = $body['eventContent'] ?? null;

    $event = json_decode($signedEvent, true);
    $eventKind = $event['kind'] ?? null;
    $eventIdsToDelete = $eventKind === 5
      ? array_map(fn($tag) => $tag[1], array_filter($event['tags'], fn($tag) => $tag[0] === 'e'))
      : [];

    if (!$signedEvent || (empty($mediaIds) && $eventKind !== 5) || ($eventKind === 5 && empty($eventIdsToDelete)) || !$eventId || !$eventCreatedAt || !$eventContent) {
      return dashboardError($response, 'No event to publish or delete');
    }

    $nc = new NostrClient($_SERVER['NB_API_NOSTR_CLIENT_SECRET'], $_SERVER['NB_API_NOSTR_CLIENT_URL']);
    $pubRes = $nc->sendPresignedNote($signedEvent);
    if (!$pubRes) {
      return dashboardError($response, 'Failed to publish Nostr event', 500);
    }

    try {
      switch ($eventKind) {
        case 5:
          $stmtDeleteEvent = $link->prepare("DELETE FROM users_nostr_notes WHERE usernpub = ? AND note_id = ?");
          $stmtDeleteImage = $link->prepare("DELETE FROM users_nostr_images WHERE usernpub = ? AND note_id = ?");
          foreach ($eventIdsToDelete as $eventToDelete) {
            $stmtDeleteEvent->bind_param("ss", $_SESSION['usernpub'], $eventToDelete);
            $stmtDeleteEvent->execute();
            $stmtDeleteImage->bind_param("ss", $_SESSION['usernpub'], $eventToDelete);
            $stmtDeleteImage->execute();
          }
          $stmtDeleteEvent->close();
          $stmtDeleteImage->close();
          break;

        case 1:
        case 20:
        case 21:
        case 1222:
          $stmt = $link->prepare("INSERT INTO users_nostr_notes (usernpub, note_id, created_at, content, full_json) VALUES (?, ?, FROM_UNIXTIME(?), ?, ?)");
          $stmt->bind_param("ssiss", $_SESSION['usernpub'], $eventId, $eventCreatedAt, $eventContent, $signedEvent);
          $stmt->execute();
          $stmt->close();

          $stmt = $link->prepare("INSERT INTO users_nostr_images (usernpub, note_id, image_id) VALUES (?, ?, ?)");
          foreach ($mediaIds as $imageId) {
            $stmt->bind_param("ssi", $_SESSION['usernpub'], $eventId, $imageId);
            $stmt->execute();
          }
          $stmt->close();
          break;

        default:
          return dashboardError($response, 'Invalid event kind');
      }
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to store Nostr event in the database', 500);
    }

    $mediaEvents = array_combine($mediaIds, array_fill(0, count($mediaIds), "{$eventId}:{$eventCreatedAt}"));

    return dashboardJson($response, [
      "action" => "publish_nostr_event",
      "success" => true,
      "noteId" => $eventId,
      "createdAt" => $eventCreatedAt,
      "mediaIds" => $mediaIds,
      "mediaEvents" => $mediaEvents,
      "deletedEvents" => $eventIdsToDelete,
    ]);
  });

  // POST /profile - Update profile
  $group->post('/profile', function (Request $request, Response $response) {
    global $link;
    $account = dashboardGetAccount();
    $body = $request->getParsedBody();

    $profileData = [
      "name" => !empty($body['name']) ? $body['name'] : null,
      "pfpUrl" => !empty($body['pfpUrl']) ? $body['pfpUrl'] : null,
      "wallet" => !empty($body['wallet']) ? $body['wallet'] : null,
      "allowNostrLogin" => !empty($body['allowNostrLogin']) ? $body['allowNostrLogin'] === 'true' : false,
      "defaultFolder" => !empty($body['defaultFolder']) ? $body['defaultFolder'] : '',
    ];

    try {
      $account->updateAccount(
        nym: $profileData['name'],
        ppic: $profileData['pfpUrl'],
        wallet: $profileData['wallet'],
        default_folder: $profileData['defaultFolder'],
      );
      $account->allowNpubLogin($profileData['allowNostrLogin']);
      $data = dashboardGetAccountData($link, $account);
      return dashboardJson($response, $data);
    } catch (\Exception $e) {
      error_log($e->getMessage());
      return dashboardError($response, 'Failed to update profile', 500);
    }
  });

  // POST /profile/password - Update password
  $group->post('/profile/password', function (Request $request, Response $response) {
    $account = dashboardGetAccount();
    $body = $request->getParsedBody();

    $currentPassword = !empty($body['password']) ? $body['password'] : null;
    $newPassword = !empty($body['newPassword']) ? $body['newPassword'] : null;

    $res = $account->changePasswordSafe($currentPassword, $newPassword);
    if ($res) {
      return dashboardJson($response, ["action" => "update_password", "success" => $res]);
    } else {
      return dashboardError($response, 'Failed to update password');
    }
  });

  // POST /nostrland/activate - Activate NostrLand Plus
  $group->post('/nostrland/activate', function (Request $request, Response $response) {
    global $link;
    $account = dashboardGetAccount();

    if (!$account->isAccountNostrLandPlusEligible()) {
      return dashboardError($response, 'Account is not eligible for NostrLand Plus', 403);
    }

    if ($account->hasNlSubActivation()) {
      return dashboardError($response, 'NostrLand Plus is already activated');
    }

    try {
      require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrLand.class.php';
      $nostrLand = new NostrLand($_SESSION['usernpub'], $link);
      $result = $nostrLand->activateSubscription();

      if ($result === null) {
        return dashboardError($response, 'Unable to activate NostrLand Plus. Please try again later.');
      }

      $refreshedData = dashboardGetAccountData($link, $account);

      return dashboardJson($response, [
        "success" => true,
        "message" => "NostrLand Plus activated successfully!",
        "accountData" => $refreshedData,
      ]);
    } catch (\Exception $e) {
      error_log("NostrLand activation failed: " . $e->getMessage());
      error_log("NostrLand activation error: " . $e->getMessage());
      return dashboardError($response, 'Failed to activate NostrLand Plus', 500);
    }
  });

})->add(function ($request, $handler) {
  $__mwStart = hrtime(true);
  $routePath = $request->getUri()->getPath();
  apiTimingLog("dashboard middleware START {$routePath}");

  $perm = new Permission();

  if (!$perm->validateLoggedin() || !isset($_SESSION['usernpub'])) {
    $response = new Slim\Psr7\Response();
    return dashboardError($response, 'You are not logged in', 401);
  }

  if ($perm->validatePermissionsLevelEqual(0)) {
    $response = new Slim\Psr7\Response();
    return dashboardError($response, 'Please verify your account', 403);
  }

  apiTimingLog("dashboard middleware: auth passed {$routePath}", $__mwStart);

  $response = $handler->handle($request);
  apiTimingLog("dashboard middleware: handler done {$routePath}", $__mwStart);

  return $response;
});
