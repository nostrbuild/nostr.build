<?php
/**
 * Account Dashboard — shared HELPER functions (response/json helpers, file +
 * media + credits + AI builders). Consumed by the worker-facing /accounts BFF
 * subgroup in routes_accounts.php (HMAC + npub auth).
 *
 * The legacy session/cookie-authenticated `/account/dashboard` Slim group that
 * used to live here was removed — it served the old (deleted) web frontend and
 * nothing calls it anymore. Add new dashboard endpoints to the /accounts
 * subgroup in routes_accounts.php, not here.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/SiteConfig.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ImageCatalogManager.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/imageproc.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Credits.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';

use Psr\Http\Message\ResponseInterface as Response;

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
  } catch (\Throwable $e) {
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
    ? $folders->findFolderByNameOrCreate($_SESSION['useruuid'], $folderName)
    : null;

  $imgArray = $images->getFiles($_SESSION['useruuid'], $folderId, $start, $limit, $filter);

  return array_map('buildFileListEntry', $imgArray);
}

// Files the logged-in user owns that the DOWNGRADE TARGET tier can't host, across
// ALL folders (users_images is keyed by stable user_uuid, not folder). A file is ineligible
// when its MIME isn't in the target tier's allow-list (getAllowedMimesArray — the
// SAME gate the uploader enforces, so this never drifts) OR — Purist (3) only — it
// exceeds the 450 MiB per-file cap. Each row carries a `reason`:
//   'type' = the tier doesn't accept this file type (e.g. ZIP on Pro, PDF on Purist)
//   'size' = the type is fine but the file is over Purist's per-file cap
// Mirrors UsersImages::getFiles' associated_notes subquery so buildFileListEntry
// yields the same shape the file list uses (proper type icons in the UI). Capped at
// 200; a downgrade-eligible library is tiny (the storage gate gates first).
function dashboardListDowngradeIneligibleFiles(int $targetLevel, $link): array
{
  $userUuid = $_SESSION['useruuid'];

  // The exact MIME allow-list the uploader enforces for the target tier.
  $allowed = array_keys(getAllowedMimesArray($targetLevel));
  // Purist (3) is the only tier with a per-file size cap (450 MiB). Others: none.
  $cap = ($targetLevel === 3) ? (450 * 1024 * 1024) : null;

  $placeholders = implode(',', array_fill(0, count($allowed), '?'));
  // COALESCE so a NULL/empty stored MIME is treated as "not allowed" (block, never
  // silently pass an unverifiable file).
  $sizeClause = $cap !== null ? ' OR ui.file_size > ?' : '';
  $sql = "
        SELECT
            ui.*,
            (SELECT GROUP_CONCAT(CONCAT(uni.note_id, ':', UNIX_TIMESTAMP(unn.created_at)))
             FROM users_nostr_images uni
             LEFT JOIN users_nostr_notes unn ON uni.note_id = unn.note_id
             WHERE uni.image_id = ui.id) AS associated_notes
        FROM users_images ui
        WHERE ui.user_uuid = ?
          AND (COALESCE(ui.mime_type, '') NOT IN ($placeholders)$sizeClause)
        ORDER BY ui.file_size DESC
        LIMIT 200
  ";
  // Throw (→ HTTP 5xx) on any DB failure rather than returning []: the Worker
  // treats a non-2xx as "can't verify → block the downgrade", whereas an empty 200
  // would be read as "all files supported → allow", masking the failure and letting
  // an unsupported file slip onto the smaller tier.
  $stmt = $link->prepare($sql);
  if (!$stmt) {
    throw new Exception('downgrade-ineligible prepare failed: ' . $link->error);
  }
  // Bind: npub (s), each allowed MIME (s…), then the cap (i) when present.
  $types = 's' . str_repeat('s', count($allowed)) . ($cap !== null ? 'i' : '');
  $bindArgs = array_merge([$userUuid], $allowed);
  if ($cap !== null) {
    $bindArgs[] = $cap;
  }
  $stmt->bind_param($types, ...$bindArgs);
  if (!$stmt->execute()) {
    $err = $stmt->error;
    $stmt->close();
    throw new Exception('downgrade-ineligible execute failed: ' . $err);
  }
  $result = $stmt->get_result();
  $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();

  // A row is here because its type is unsupported OR it's oversized. Type wins the
  // label (a wrong-type file can never fit the tier, regardless of size).
  $allowedSet = array_flip($allowed);
  return array_map(function (array $row) use ($allowedSet): array {
    $entry = buildFileListEntry($row);
    $entry['reason'] = isset($allowedSet[$row['mime_type'] ?? '']) ? 'size' : 'type';
    return $entry;
  }, $rows);
}

function dashboardGetAccountData($link, $account): array
{
  $info = $account->getAccountInfo();

  // Cast mysqli `fetch_assoc()` strings to their honest types at the
  // boundary. The connection isn't configured with
  // MYSQLI_OPT_INT_AND_FLOAT_NATIVE, so every numeric/boolean column
  // arrives here as a string ("42", "0", "1"). Returning that shape
  // breaks any consumer that schema-validates (e.g. the Worker login
  // handler returning 502 "bad-upstream"). Fix once, at the source.
  return [
    "userId" => (int) $info['id'],
    // Stable per-user identity (users.uuid_id). The accounts Worker keys its
    // Durable Objects / session cookie / webhooks on this, not on the
    // autoincrement id (re-import-unstable) or the npub (mutable attribute).
    "uuidId" => $info['uuid_id'] ?? null,
    // Coerce nullable `nym` to an empty string so the client never sees a
    // null where the type promises a string. Same pattern used a few lines
    // down for default_folder.
    "name" => $info['nym'] ?? "",
    "npub" => $info['usernpub'] ?? null,
    // email/emailVerified/hasPassword are read via dedicated getters, NOT $info:
    // getAccountInfo() strips email (PII, shared payload) and the password hash.
    "email" => $account->getEmail(),
    "emailVerified" => $account->isEmailVerified(),
    "hasPassword" => $account->hasPassword(),
    // Email-notification preferences (account/security notices + marketing).
    // Defaults tolerate a DB that predates the email_notify_* columns.
    "emailNotifyAccount" => $account->getEmailNotifyAccount(),
    "emailNotifyMarketing" => $account->getEmailNotifyMarketing(),
    "pfpUrl" => $info['ppic'],
    "wallet" => $info['wallet'],
    "defaultFolder" => $info['default_folder'] ?? "",
    "allowNostrLogin" => (bool) $info['allow_npub_login'],
    "npubVerified" => (bool) $info['npub_verified'],
    "accountLevel" => (int) $info['acctlevel'],
    "accountFlags" => $info['accflags'],
    "remainingDays" => $info['remaining_subscription_days'],
    "storageUsed" => $info['used_storage_space'],
    "storageLimit" => $info['storage_space_limit'],
    "totalStorageLimit" => $info['storage_space_limit'] === PHP_INT_MAX ? "Unlimited" : formatSizeUnits($info['storage_space_limit']),
    "referralCode" => $account->getAccountReferralCode(),
    "nlSubEligible" => $info['nl_sub_eligible'] ?? false,
    "nlSubActivated" => $info['nl_sub_activated'] ?? false,
    "nlSubInfo" => $info['nl_sub_info'] ?? null,
    // Self-service account-deletion lifecycle. Defaults tolerate a DB that
    // predates the deletion_* columns (reads as "not pending"). deleteAfter is
    // unix seconds (the app converts to a countdown).
    "deletionStatus" => $info['deletion_status'] ?? 'none',
    "deletionDeleteAfter" => !empty($info['delete_after']) ? strtotime($info['delete_after']) : null,
    // Whole days since the paid plan lapsed (0 while active / never-expired).
    // remaining_subscription_days clamps to 0 on expiry, so it can't tell
    // "expired yesterday" from "expired 2 years ago" — this exposes that gap so
    // the dashboard can surface the delete-account CTA only for long-dead
    // accounts (>365 days). Admins/moderators report 0 (never eligible).
    "daysPastExpiration" => $account->getDaysPastSubscriptionExpiration(),
  ];
}

function dashboardGetMediaStats(string $mediaId, string $period, string $interval, string $groupBy, $link): string
{
  $userUuid = $_SESSION['useruuid'];
  $mediaIdInt = intval($mediaId);

  $stmt = $link->prepare("SELECT * FROM users_images WHERE id = ? AND user_uuid = ?");
  $stmt->bind_param('is', $mediaIdInt, $userUuid);
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
  $upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub'], $awsConfig, $_SESSION['useruuid']);
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

/**
 * Per-model img2img source constraints, verified against the upstream docs
 * (CF Workers AI model pages + Stability's live v2beta OpenAPI spec):
 *
 *   - FLUX.2 family: "All input images must be smaller than 512x512" (hard
 *     upstream rule, max 4 references). The 300x300 box-fit thumb is the only
 *     rendition that guarantees compliance for every aspect ratio.
 *   - CF SD family: the published schema caps width/height at 2048 and says
 *     nothing about oversized inputs being resized — so input dims are OUR
 *     responsibility. SDXL-class models are 1MP-native (~2MP is the sane
 *     ceiling); DreamShaper is SD1.5-based (512-native, ~1MP ceiling). The
 *     ladders start at the rendition nearest the native resolution: bigger
 *     inputs only add latency (the img2img output tracks input size).
 *   - Stability SD3.5 / Ultra: jpeg/png/webp, every side >= 64px, request
 *     body <= 10MiB; output is ~1MP regardless, so 1080p is plenty.
 *
 * 'ladder' lists acceptable renditions best-first (responsive renditions are
 * width-fit with UNCONSTRAINED height, so a tall portrait can violate a
 * dimension cap at one rung and comply at the next). null = model takes no
 * source image.
 */
function dashboardAiSourceSpec(string $model): ?array
{
  if (str_starts_with($model, '@cf/black-forest-labs/flux-2')) {
    return [
      'max' => 4,
      'ladder' => ['thumb'],
      'format' => null, // thumb ignores ?format=
      'minSide' => 64,
      'maxSide' => 511,
      'maxPixels' => 511 * 511,
      'maxBytes' => 2 * 1024 * 1024,
    ];
  }
  $specs = [
    '@cf/bytedance/stable-diffusion-xl-lightning' => [
      'max' => 1,
      'ladder' => ['720p', '480p', '360p'],
      'format' => 'webp',
      'minSide' => 64,
      'maxSide' => 2048,
      'maxPixels' => 2 * 1024 * 1024,
      'maxBytes' => 8 * 1024 * 1024,
    ],
    '@cf/lykon/dreamshaper-8-lcm' => [
      'max' => 1,
      'ladder' => ['480p', '360p', '240p'],
      'format' => 'webp',
      'minSide' => 64,
      'maxSide' => 2048,
      'maxPixels' => 1024 * 1024,
      'maxBytes' => 8 * 1024 * 1024,
    ],
    '@sd/sd3.5-large' => [
      'max' => 1,
      'ladder' => ['1080p', '720p', '480p'],
      'format' => 'webp',
      'minSide' => 64,
      'maxSide' => 4096,
      'maxPixels' => 9437184,
      'maxBytes' => 8 * 1024 * 1024,
    ],
  ];
  $specs['@cf/stabilityai/stable-diffusion-xl-base-1.0'] = $specs['@cf/bytedance/stable-diffusion-xl-lightning'];
  $specs['@sd/sd3.5-medium'] = $specs['@sd/sd3.5-large'];
  $specs['@sd/sd3.5-large-turbo'] = $specs['@sd/sd3.5-large'];
  $specs['@sd/ultra'] = $specs['@sd/sd3.5-large'];
  return $specs[$model] ?? null;
}

/**
 * Predict what the serving worker will return for a rendition of a
 * $srcW x $srcH original: thumb = 300x300 box fit, responsive = width fit
 * with unconstrained height, and neither ever upscales. Lets the fetch loop
 * skip rungs that cannot comply (e.g. a tall portrait at 720p) without
 * wasting the fetch. Prediction only skips; the post-fetch validation on the
 * actual bytes is authoritative.
 */
function dashboardAiPredictRendition(int $srcW, int $srcH, string $variant): array
{
  $widths = ['240p' => 426, '360p' => 640, '480p' => 854, '720p' => 1280, '1080p' => 1920];
  if ($variant === 'thumb') {
    $scale = min(1.0, 300 / $srcW, 300 / $srcH);
  } else {
    $scale = min(1.0, ($widths[$variant] ?? 1920) / $srcW);
  }
  return [(int) round($srcW * $scale), (int) round($srcH * $scale)];
}

/**
 * Fetch one rendition and validate it against the model spec. Returns
 * ['bytes','width','height'] on success, or ['reject' => reason] when the
 * received bytes are unusable. Failure modes this guards (all real behaviors
 * of the serving worker):
 *   - imgproxy hiccup => the ORIGINAL full-size bytes with HTTP 200 (and that
 *     result cached at the edge for up to 14 days) — caught by the dims/bytes
 *     checks; the caller retries with x-nb-bypass-cache to purge the edge.
 *   - missing file => a branded 404 JPEG with HTTP 200 — caught via the
 *     x-status: 404 marker header (its only tell).
 *   - non-image redirect (301/302 point at the full original) — never
 *     followed.
 */
function dashboardAiTryFetchRendition(string $url, bool $bypassCache, array $spec): array
{
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($ch, CURLOPT_TIMEOUT, 20);
  $headers = [];
  curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $headerLine) use (&$headers) {
    $parts = explode(':', $headerLine, 2);
    if (count($parts) === 2) {
      $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
    }
    return strlen($headerLine);
  });
  if ($bypassCache) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-nb-bypass-cache: 1']);
  }
  $bytes = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $ch = null;

  if ($bytes === false || $httpCode !== 200) {
    return ['reject' => 'unavailable'];
  }
  if (($headers['x-status'] ?? '') === '404') {
    return ['reject' => 'not-found'];
  }
  if (!str_starts_with((string) ($headers['content-type'] ?? ''), 'image/')) {
    return ['reject' => 'not-an-image'];
  }
  if (strlen($bytes) > $spec['maxBytes']) {
    return ['reject' => 'oversized'];
  }
  $dims = getimagesizefromstring($bytes);
  if ($dims === false) {
    return ['reject' => 'unreadable'];
  }
  [$w, $h] = $dims;
  if ($w < $spec['minSide'] || $h < $spec['minSide']) {
    // No rendition upscales, so a too-small source can never succeed at any
    // rung — the caller turns this into the specific user-facing message.
    return ['reject' => 'too-small'];
  }
  if ($w > $spec['maxSide'] || $h > $spec['maxSide'] || $w * $h > $spec['maxPixels']) {
    return ['reject' => 'oversized'];
  }
  return ['bytes' => $bytes, 'width' => $w, 'height' => $h];
}

/**
 * Resolve one of the caller's OWN images (users_images.id + session uuid) into
 * a spec-compliant base64 payload (+ pixel dimensions) for the AI worker's
 * img2img inputs. Walks the spec's rendition ladder best-first, skipping rungs
 * the stored original dimensions already rule out, and retries a failed rung
 * once with x-nb-bypass-cache (a resizer hiccup poisons the edge cache with
 * original bytes; the bypass purges that entry).
 *
 * Throws \Exception with code 400 and a user-safe message on any problem; the
 * /ai/generate route surfaces that verbatim instead of a generic 500.
 */
function dashboardFetchAiSourceImage(int $imageId, array $spec, $link): array
{
  $userUuid = $_SESSION['useruuid'];
  $stmt = $link->prepare("SELECT image, mime_type, media_width, media_height FROM users_images WHERE id = ? AND user_uuid = ?");
  $stmt->bind_param('is', $imageId, $userUuid);
  $stmt->execute();
  $result = $stmt->get_result();
  $row = $result ? $result->fetch_assoc() : null;
  $stmt->close();
  if (!$row) {
    throw new \Exception('Source image not found', 400);
  }
  if (!in_array($row['mime_type'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
    throw new \Exception('Source must be a JPEG, PNG, or WebP image', 400);
  }
  $srcW = (int) ($row['media_width'] ?? 0);
  $srcH = (int) ($row['media_height'] ?? 0);
  if ($srcW > 0 && $srcH > 0 && ($srcW < $spec['minSide'] || $srcH < $spec['minSide'])) {
    throw new \Exception('Source image must be at least 64x64 pixels', 400);
  }

  $parsedUrl = parse_url($row['image']);
  $filename = pathinfo($parsedUrl['path'] ?? $row['image'], PATHINFO_BASENAME);

  foreach ($spec['ladder'] as $variant) {
    if ($srcW > 0 && $srcH > 0) {
      [$predW, $predH] = dashboardAiPredictRendition($srcW, $srcH, $variant);
      if (
        $predW < $spec['minSide'] || $predH < $spec['minSide'] ||
        $predW > $spec['maxSide'] || $predH > $spec['maxSide'] ||
        $predW * $predH > $spec['maxPixels']
      ) {
        continue;
      }
    }
    if ($variant === 'thumb') {
      $url = SiteConfig::getThumbnailUrl('professional_account_image') . $filename;
    } else {
      // Explicit format: a server-side fetch has no Accept header, and webp
      // is documented-accepted by Stability and decodes fine on the CF side.
      $url = SiteConfig::getResponsiveUrl('professional_account_image', $variant) . $filename . '?format=' . $spec['format'];
    }
    $fetch = dashboardAiTryFetchRendition($url, false, $spec);
    if (isset($fetch['reject']) && $fetch['reject'] !== 'too-small') {
      $fetch = dashboardAiTryFetchRendition($url, true, $spec);
    }
    if (isset($fetch['reject'])) {
      if ($fetch['reject'] === 'too-small') {
        throw new \Exception('Source image must be at least 64x64 pixels', 400);
      }
      continue;
    }
    return [
      'b64' => base64_encode($fetch['bytes']),
      'width' => $fetch['width'],
      'height' => $fetch['height'],
    ];
  }
  throw new \Exception('Source image could not be prepared, please try again', 400);
}

/**
 * Output dimensions for FLUX.2 image-to-image, matched to the (first) source's
 * aspect ratio: ~1MP area, multiples of 32, clamped to the schema's 256-1920.
 * Without this the model falls back to its fixed 1024x768 default and a
 * portrait remix comes back landscape.
 */
function dashboardAiOutputDims(int $srcW, int $srcH): array
{
  $aspect = $srcW / max(1, $srcH);
  $w = sqrt(1024 * 1024 * $aspect);
  $h = $w / max(0.0001, $aspect);
  $snap = fn(float $v): int => (int) max(256, min(1920, round($v / 32) * 32));
  return [$snap($w), $snap($h)];
}

function dashboardGenerateAIImage(string $model, string $prompt, string $title, string $negativePrompt, $link, $awsConfig, ?array $sourceImagesB64 = null, ?float $strength = null, ?array $firstSourceDims = null): array
{
  $s3 = new S3Service($awsConfig);
  $apiUrl = $_SERVER['AI_GEN_API_ENDPOINT'];
  $requestBodyArray = [
    "prompt" => $prompt,
    "model" => $model,
    // The AI worker's CF-model path is uuid-only (npub fully retired there).
    "user_uuid" => $_SESSION['useruuid'],
  ];
  // Only the SD-family CF models + Leonardo Phoenix accept a negative prompt;
  // the worker's per-model schema strips it for the rest, so forwarding it when
  // present is safe.
  if (!empty($negativePrompt)) $requestBodyArray['negative_prompt'] = $negativePrompt;
  // img2img sources (already ownership-checked + spec-validated by the route).
  // FLUX.2 takes multi-reference `reference_images` (no strength field); the
  // SD family takes a single `image_b64` + optional 0-1 `strength`.
  if (!empty($sourceImagesB64)) {
    if (str_starts_with($model, '@cf/black-forest-labs/flux-2')) {
      $requestBodyArray['reference_images'] = array_values($sourceImagesB64);
      // FLUX.2 otherwise defaults to a fixed 1024x768 — size the output to the
      // first source's aspect so a portrait remix comes back portrait.
      if ($firstSourceDims !== null) {
        [$outW, $outH] = dashboardAiOutputDims($firstSourceDims[0], $firstSourceDims[1]);
        $requestBodyArray['width'] = $outW;
        $requestBodyArray['height'] = $outH;
      }
    } else {
      $requestBodyArray['image_b64'] = $sourceImagesB64[0];
      if ($strength !== null) $requestBodyArray['strength'] = $strength;
    }
  }
  $requestBody = json_encode($requestBodyArray);

  $bearer = signApiRequest($_SERVER['AI_GEN_API_HMAC_KEY'], $apiUrl, 'POST', $requestBody);

  $ch = curl_init($apiUrl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', "Authorization: Bearer {$bearer}"]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  // Generation is slow but not unbounded — an oversized/failing upstream call
  // must surface as an error, not hang the request until the process dies.
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 180);

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

  $upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub'], $awsConfig, $_SESSION['useruuid']);
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

function dashboardGenerateStabilityImage(string $endpoint, ?string $sdModel, string $prompt, string $negativePrompt, string $ar, string $preset, int $seed, string $title, Account $account, $link, $awsConfig, ?string $sourceImageB64 = null, ?float $strength = null): array
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
  $apiUrl = $apiBase . $endpoint;

  $level = $account->getAccountLevelInt();
  $subscriptionPeriod = $account->getSubscriptionPeriod();
  // Stable per-user identity (users.uuid_id) — the worker keys the AI-credit
  // ledger by this. npub is no longer sent (the ledger is uuid-only).
  $userUuid = $account->getAccountUuid();

  $requestBodyArray = [
    "user_uuid" => $userUuid,
    "app_id" => "nostr.build",
    "app_version" => "1.0.0-beta",
    "user_level" => $level,
    "user_sub_period" => $subscriptionPeriod,
    "prompt" => $prompt,
  ];
  // Only the /sd/sd3 endpoint takes a bare model id (sd3.5-*); core + ultra are
  // fixed by their endpoint.
  if ($sdModel !== null) $requestBodyArray['model'] = $sdModel;
  if (!empty($negativePrompt)) $requestBodyArray['negative_prompt'] = $negativePrompt;
  // img2img (sd3 + ultra): Stability requires image + strength together. The
  // sd3 endpoint additionally needs mode=image-to-image; ultra has no mode
  // field (supplying `image` IS the switch). aspect_ratio is text-to-image
  // only — the source image supersedes any aspect the client had selected.
  if ($sourceImageB64 !== null && in_array($endpoint, ['/sd/sd3', '/sd/ultra'], true)) {
    if ($endpoint === '/sd/sd3') $requestBodyArray['mode'] = 'image-to-image';
    $requestBodyArray['image_b64'] = $sourceImageB64;
    $requestBodyArray['strength'] = $strength ?? 0.7;
  } elseif (!empty($ar)) {
    $requestBodyArray['aspect_ratio'] = $ar;
  }
  // The /sd/sd3 endpoint has no style_preset field; only core + ultra accept it.
  if (!empty($preset) && $endpoint !== '/sd/sd3') $requestBodyArray['style_preset'] = $preset;
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
  // Generation is slow but not unbounded — an oversized/failing upstream call
  // must surface as an error, not hang the request until the process dies.
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, 180);

  $response = curl_exec($ch);
  if ($response === false) {
    $error = curl_error($ch);
    $ch = null;
    throw new \Exception("cURL request failed: {$error}");
  }

  $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
  $ch = null;
  if ($httpCode === 402) {
    // Worker ledger refused the debit — surface as insufficient credits (the
    // route maps code 402 to a user-facing error instead of a generic 500).
    throw new \Exception('insufficient_credits', 402);
  }
  if ($httpCode === 403) {
    // Stability's documented 403 on the generate endpoints is content
    // moderation (the worker forwards its status; users are not charged).
    // Code 400 => the route surfaces this message verbatim.
    throw new \Exception('Your request was declined by the content moderation system', 400);
  }
  if ($httpCode !== 200 && $contentType === 'application/json') {
    throw new \Exception("Stability image generation failed: HTTP {$httpCode} - {$response}");
  }

  $transactionId = $customHeaders['x-sd-transaction-id'] ?? '';

  $tempFile = generateUniqueFilename("ai_image_", sys_get_temp_dir());
  if (in_array($contentType, ['image/png', 'image/jpeg', 'image/webp'])) {
    file_put_contents($tempFile, $response);
  } else {
    throw new \Exception("Stability image generation failed: Unexpected content type: {$contentType}");
  }

  $upload = new MultimediaUpload($link, $s3, true, $_SESSION['usernpub'], $awsConfig, $_SESSION['useruuid']);
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

  [$status, $code, $message] = $upload->uploadFiles();
  if (!$status) {
    throw new \Exception("Failed to upload Stability generated image: {$message} {$code}");
  }

  $fileData = $upload->getUploadedFiles();
  if (empty($fileData)) {
    throw new \Exception("Failed to import media from URL");
  }

  // Sync the session credit cache from the worker's authoritative
  // x-sd-available-balance header. Replaces the old hardcoded `-= 3` in the
  // route, which was wrong for the 4-/7-/8-credit SD3.5 + Ultra models.
  if (isset($customHeaders['x-sd-available-balance']) && is_numeric($customHeaders['x-sd-available-balance'])) {
    $_SESSION['sd_credits'] = intval($customHeaders['x-sd-available-balance']);
  }

  $mediaId = $fileData[0]['name'];
  $credits = new Credits($_SESSION['useruuid'], $apiBase, $_SERVER['AI_GEN_API_HMAC_KEY'], $link);
  $credits->updateTransactionWithMediaId($transactionId, $mediaId);

  return dashboardBuildReturnFile($fileData);
}

// --- Lazy Account loader ---
// Only creates Account + fetches subscription days when a route actually needs it.
// Keyed by the stable uuid ($_SESSION['useruuid']) — works for every account,
// npub or email.
function dashboardGetAccount(): Account
{
  static $account = null;
  if ($account === null) {
    $__t = hrtime(true);
    global $link;
    $account = Account::fromUuid($_SESSION['useruuid'], $link) ?? new Account('', $link, $_SESSION['useruuid']);
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
    } catch (\Throwable $e) {
      error_log($e->getMessage());
      $days = 0;
    }
  }
  return $days;
}
