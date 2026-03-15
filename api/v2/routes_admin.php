<?php
/**
 * Admin API Routes
 * Migrated from account/admin/*.php to PSR-7 Slim routes
 *
 * Route groups:
 *   /admin/moderation  — upload approval/rejection/ban/csam (admin OR canModerate)
 *   /admin/csam        — NCMEC evidence, reports, unblacklist (admin only)
 *   /admin/users       — account level, nym, pfp updates (admin only)
 *   /admin/media       — mass delete (admin only)
 *   /admin/promotions  — CRUD for promotions (admin only)
 *   /admin/stats       — upload & account statistics (admin only)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/CloudflarePurge.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/BlossomFrontEndAPI.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NCMECReportHandler.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/Promotions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Plans.class.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteCollectorProxy;

// --- Response Helpers ---

function adminJson(Response $response, array $data, int $statusCode = 200): Response
{
  $response->getBody()->write(json_encode($data));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($statusCode);
}

function adminError(Response $response, string $message, int $statusCode = 400): Response
{
  return adminJson($response, ['error' => $message], $statusCode);
}

function adminSuccess(Response $response, array $extra = []): Response
{
  return adminJson($response, array_merge(['success' => true], $extra));
}

// --- Auth Middleware Factories ---

/**
 * Requires admin (level 99) OR the canModerate privilege.
 */
function adminOrModeratorMiddleware(): callable
{
  return function (Request $request, RequestHandler $handler): Response {
    $perm = new Permission();
    if (!$perm->isAdmin() && !$perm->hasPrivilege('canModerate')) {
      $response = new \Slim\Psr7\Response();
      return adminError($response, 'Unauthorized', 403);
    }
    return $handler->handle($request);
  };
}

/**
 * Requires admin (level 99) only.
 */
function adminOnlyMiddleware(): callable
{
  return function (Request $request, RequestHandler $handler): Response {
    $perm = new Permission();
    if (!$perm->isAdmin()) {
      $response = new \Slim\Psr7\Response();
      return adminError($response, 'Unauthorized', 403);
    }
    return $handler->handle($request);
  };
}

// =============================================================================
// Moderation routes — admin OR canModerate
// =============================================================================

$app->group('/admin/moderation', function (RouteCollectorProxy $group) {

  /**
   * POST /admin/moderation/status
   * Change status of a single upload (approved, rejected, adult, ban, csam).
   * Replaces: change_status.php
   */
  $group->post('/status', function (Request $request, Response $response) {
    global $link, $awsConfig, $csamReportingConfig;

    $body = $request->getParsedBody();
    $id = isset($body['id']) ? intval($body['id']) : 0;
    $status = $body['status'] ?? '';

    if ($id <= 0 || $status === '') {
      return adminError($response, 'Invalid parameters');
    }

    // Validate status is one of the allowed values
    $allowedStatuses = ['approved', 'rejected', 'adult', 'ban', 'csam'];
    if (!in_array($status, $allowedStatuses, true)) {
      return adminError($response, 'Invalid status value');
    }

    $s3 = new S3Service($awsConfig);
    $blossomFrontEndAPI = new BlossomFrontEndAPI($_SERVER['BLOSSOM_API_URL'], $_SERVER['BLOSSOM_API_KEY']);

    // Get the filename and type from uploads_data
    $stmt = $link->prepare("SELECT filename, type FROM uploads_data WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($filename, $type);
    $stmt->fetch();
    $stmt->close();

    if ($filename === null) {
      return adminError($response, 'Upload not found');
    }

    if ($status === 'rejected') {
      deleteAndRejectUpload($link, $s3, $blossomFrontEndAPI, $id, $filename, $type);
    } elseif ($status === 'ban') {
      // Ban requires admin level
      $perm = new Permission();
      if (!$perm->isAdmin()) {
        return adminError($response, 'Unauthorized: Ban actions require admin privileges', 403);
      }
      banUserAndDeleteUpload($link, $s3, $blossomFrontEndAPI, $csamReportingConfig, $id, $filename, $type);
    } elseif ($status === 'csam') {
      // CSAM requires admin level
      $perm = new Permission();
      if (!$perm->isAdmin()) {
        return adminError($response, 'Unauthorized: CSAM actions require admin privileges', 403);
      }
      $result = processCsamReport($link, $s3, $blossomFrontEndAPI, $csamReportingConfig, $id, $filename, $type);
      if ($result !== true) {
        return adminError($response, $result, 500);
      }
    } else {
      // approved or adult — just update the status
      $stmt = $link->prepare("UPDATE uploads_data SET approval_status = ? WHERE id = ?");
      $stmt->bind_param("si", $status, $id);
      $stmt->execute();
      $stmt->close();
    }

    return adminSuccess($response);
  });

  /**
   * POST /admin/moderation/approve-all
   * Bulk approve pending uploads.
   * Replaces: approve_all.php
   */
  $group->post('/approve-all', function (Request $request, Response $response) {
    global $link;

    $data = $request->getParsedBody();

    if (!isset($data['ids']) || !is_array($data['ids'])) {
      return adminError($response, 'Invalid payload, expecting an "ids" array');
    }

    $ids = array_map('intval', $data['ids']);
    $ids = array_filter($ids, fn($id) => $id > 0);

    if (empty($ids)) {
      return adminError($response, 'No valid IDs provided');
    }

    // Upper-bound to prevent abuse
    if (count($ids) > 1000) {
      return adminError($response, 'Too many IDs, max 1000 per request');
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE uploads_data SET approval_status='approved' WHERE id IN ($placeholders) AND approval_status='pending'";
    $stmt = $link->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

    if ($stmt->execute()) {
      $stmt->close();
      return adminSuccess($response);
    }

    $error = $stmt->error;
    $stmt->close();
    error_log("Admin approve-all error: " . $error);
    return adminError($response, 'Database error', 500);
  });

})->add(adminOrModeratorMiddleware());

// =============================================================================
// CSAM routes — admin only
// =============================================================================

$app->group('/admin/csam', function (RouteCollectorProxy $group) {

  /**
   * GET /admin/csam/evidence
   * Fetch evidence image/video tag for a CSAM incident.
   * Replaces: get_evidence.php
   */
  $group->get('/evidence', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $incidentId = isset($params['incidentId']) ? intval($params['incidentId']) : 0;

    if ($incidentId <= 0) {
      return adminError($response, 'Incident ID is required');
    }

    try {
      $ncmecHandler = new NCMECReportHandler($incidentId);
      $imgTag = $ncmecHandler->getEvidenceImgTag(500, 500);
      if ($imgTag === '') {
        throw new Exception('Unable to retrieve evidence image.');
      }
      $response->getBody()->write($imgTag);
      return $response->withHeader('Content-Type', 'text/html')->withStatus(200);
    } catch (Exception $e) {
      error_log("Admin get_evidence error: " . $e->getMessage());
      return adminError($response, 'Error fetching evidence', 500);
    }
  });

  /**
   * GET /admin/csam/report/preview
   * Preview a NCMEC report before submission.
   * Replaces: preview_report.php
   */
  $group->get('/report/preview', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $incidentId = isset($params['incidentId']) ? intval($params['incidentId']) : 0;
    $testReport = isset($params['testReport']) && $params['testReport'] === 'true';

    if ($incidentId <= 0) {
      return adminError($response, 'Incident ID is required');
    }

    try {
      $ncmecHandler = new NCMECReportHandler($incidentId, $testReport);
      $sanitizedReport = $ncmecHandler->previewReport();
      return adminJson($response, $sanitizedReport);
    } catch (Exception $e) {
      error_log("Admin preview_report error: " . $e->getMessage());
      return adminError($response, 'Error generating report preview', 500);
    }
  });

  /**
   * POST /admin/csam/report/submit
   * Submit a NCMEC violation report.
   * Replaces: submit_report.php
   */
  $group->post('/report/submit', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $incidentId = isset($data['incidentId']) ? intval($data['incidentId']) : 0;
    $testReport = isset($data['testReport']) && $data['testReport'] === 'true';

    if ($incidentId <= 0) {
      return adminError($response, 'Incident ID is required');
    }

    try {
      $ncmecHandler = new NCMECReportHandler($incidentId, $testReport);
      $result = $ncmecHandler->processAndReportViolation();
      return adminJson($response, $result);
    } catch (Exception $e) {
      error_log("Admin submit_report error: " . $e->getMessage());
      return adminError($response, 'Error submitting report', 500);
    }
  });

  /**
   * POST /admin/csam/unblacklist
   * Remove a user from the blacklist (false match).
   * Replaces: unblacklist_user.php
   */
  $group->post('/unblacklist', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $incidentId = isset($data['incidentId']) ? intval($data['incidentId']) : 0;

    if ($incidentId <= 0) {
      return adminError($response, 'Incident ID is required');
    }

    try {
      $ncmecHandler = new NCMECReportHandler($incidentId, false);
      $result = $ncmecHandler->unBlacklistUser();

      if ($result) {
        return adminSuccess($response);
      }
      return adminError($response, 'Failed to unblacklist user. User may not be blacklisted or an error occurred.', 500);
    } catch (Exception $e) {
      error_log("Admin unblacklist error: " . $e->getMessage());
      return adminError($response, 'Error unblacklisting user', 500);
    }
  });

  /**
   * GET /admin/csam/unsubmitted
   * Get list of unsubmitted CSAM incident IDs from the past N days.
   * Query params: days (default 7, max 90)
   */
  $group->get('/unsubmitted', function (Request $request, Response $response) {
    global $link;

    $params = $request->getQueryParams();
    $days = isset($params['days']) ? min(90, max(1, intval($params['days']))) : 7;

    $sql = "SELECT id FROM identified_csam_cases
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND (ncmec_report_id IS NULL
                   OR ncmec_report_id LIKE 'TEST_%'
                   OR ncmec_report_id = 'Null: Technical Error')
            ORDER BY timestamp ASC";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $ids = [];
    while ($row = $result->fetch_assoc()) {
      $ids[] = (int) $row['id'];
    }
    $stmt->close();

    return adminJson($response, ['ids' => $ids, 'count' => count($ids), 'days' => $days]);
  });

  /**
   * POST /admin/csam/report/submit-single
   * Submit a single NCMEC report by incident ID (for use in bulk loops).
   * Same as /report/submit but returns structured result for batch processing.
   */
  $group->post('/report/submit-single', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    $incidentId = isset($data['incidentId']) ? intval($data['incidentId']) : 0;

    if ($incidentId <= 0) {
      return adminError($response, 'Incident ID is required');
    }

    try {
      $ncmecHandler = new NCMECReportHandler($incidentId, false);
      $result = $ncmecHandler->processAndReportViolation();

      $success = isset($result['httpCode']) && $result['httpCode'] === 200;
      return adminJson($response, [
        'success' => $success,
        'incidentId' => $incidentId,
        'result' => $result,
      ]);
    } catch (Exception $e) {
      error_log("Admin submit-single error for incident {$incidentId}: " . $e->getMessage());
      return adminJson($response, [
        'success' => false,
        'incidentId' => $incidentId,
        'error' => 'Error submitting report',
      ], 500);
    }
  });

})->add(adminOnlyMiddleware());

// =============================================================================
// User management routes — admin only
// =============================================================================

$app->group('/admin/users', function (RouteCollectorProxy $group) {

  /**
   * POST /admin/users/account-level
   * Update a user's account level.
   * Replaces: newacct.php POST and update_db.php acctlevel POST
   * SECURITY FIX: uses prepared statements instead of raw SQL concatenation
   */
  $group->post('/account-level', function (Request $request, Response $response) {
    global $link;

    $body = $request->getParsedBody();
    $npub = trim($body['usernpub'] ?? '');
    $level = isset($body['acctlevel']) ? intval($body['acctlevel']) : -1;

    if ($npub === '' || $level < 0) {
      return adminError($response, 'usernpub and acctlevel are required');
    }

    // Validate npub format
    if (strpos($npub, 'npub1') !== 0 || strlen($npub) > 255) {
      return adminError($response, 'Invalid npub format');
    }

    // Validate account level is within known range
    $validLevels = [0, 1, 2, 3, 4, 5, 10, 89, 99];
    if (!in_array($level, $validLevels, true)) {
      return adminError($response, 'Invalid account level');
    }

    $stmt = $link->prepare("UPDATE users SET acctlevel = ? WHERE usernpub = ?");
    $stmt->bind_param("is", $level, $npub);

    if ($stmt->execute()) {
      $affected = $stmt->affected_rows;
      $stmt->close();
      if ($affected === 0) {
        return adminError($response, 'User not found or level unchanged');
      }
      return adminSuccess($response, ['npub' => $npub, 'acctlevel' => $level]);
    }

    $error = $stmt->error;
    $stmt->close();
    error_log("Admin account-level error: " . $error);
    return adminError($response, 'Database error', 500);
  });

  /**
   * POST /admin/users/nym
   * Update a user's @nym.
   * Replaces: update_db.php nym POST
   * SECURITY FIX: uses prepared statements
   */
  $group->post('/nym', function (Request $request, Response $response) {
    global $link;

    $body = $request->getParsedBody();
    $npub = trim($body['usernpub'] ?? '');
    $nym = trim($body['nym'] ?? '');

    if ($npub === '' || $nym === '') {
      return adminError($response, 'usernpub and nym are required');
    }

    if (strpos($npub, 'npub1') !== 0 || strlen($npub) > 255) {
      return adminError($response, 'Invalid npub format');
    }

    // Ensure nym starts with @
    if (substr($nym, 0, 1) !== '@') {
      $nym = '@' . $nym;
    }

    // Limit nym length
    if (strlen($nym) > 50) {
      return adminError($response, 'Nym too long (max 50 chars)');
    }

    $stmt = $link->prepare("UPDATE users SET nym = ? WHERE usernpub = ?");
    $stmt->bind_param("ss", $nym, $npub);

    if ($stmt->execute()) {
      $affected = $stmt->affected_rows;
      $stmt->close();
      if ($affected === 0) {
        return adminError($response, 'User not found or nym unchanged');
      }
      return adminSuccess($response, ['npub' => $npub, 'nym' => $nym]);
    }

    $error = $stmt->error;
    $stmt->close();
    error_log("Admin nym update error: " . $error);
    return adminError($response, 'Database error', 500);
  });

  /**
   * POST /admin/users/pfp
   * Update a user's profile picture URL.
   * Replaces: update_db.php ppic POST
   * SECURITY FIX: uses prepared statements
   */
  $group->post('/pfp', function (Request $request, Response $response) {
    global $link;

    $body = $request->getParsedBody();
    $npub = trim($body['usernpub'] ?? '');
    $ppic = trim($body['ppic'] ?? '');

    if ($npub === '' || $ppic === '') {
      return adminError($response, 'usernpub and ppic are required');
    }

    if (strpos($npub, 'npub1') !== 0 || strlen($npub) > 255) {
      return adminError($response, 'Invalid npub format');
    }

    // Basic URL validation
    if (!filter_var($ppic, FILTER_VALIDATE_URL) || strlen($ppic) > 2048) {
      return adminError($response, 'Invalid profile picture URL');
    }

    $stmt = $link->prepare("UPDATE users SET ppic = ? WHERE usernpub = ?");
    $stmt->bind_param("ss", $ppic, $npub);

    if ($stmt->execute()) {
      $affected = $stmt->affected_rows;
      $stmt->close();
      if ($affected === 0) {
        return adminError($response, 'User not found or pfp unchanged');
      }
      return adminSuccess($response, ['npub' => $npub, 'ppic' => $ppic]);
    }

    $error = $stmt->error;
    $stmt->close();
    error_log("Admin pfp update error: " . $error);
    return adminError($response, 'Database error', 500);
  });

})->add(adminOnlyMiddleware());

// =============================================================================
// Mass delete routes — admin only
// =============================================================================

$app->group('/admin/media', function (RouteCollectorProxy $group) {

  /**
   * POST /admin/media/mass-delete
   * Batch delete files from S3, CF cache, and database.
   * Replaces: mass_delete.php POST
   */
  $group->post('/mass-delete', function (Request $request, Response $response) {
    global $link, $awsConfig;

    $body = $request->getParsedBody();
    $fileListRaw = $body['file_list'] ?? '';

    if (empty($fileListRaw)) {
      return adminError($response, 'file_list is required');
    }

    // Parse newline-separated file list
    $fileList = array_filter(array_map('trim', explode("\n", $fileListRaw)));

    if (empty($fileList)) {
      return adminError($response, 'No valid filenames provided');
    }

    // Validate filename format — must be hex hash with extension, no path separators
    foreach ($fileList as $f) {
      if (!preg_match('/^[a-f0-9]{64}\.[a-z0-9]+$/i', $f)) {
        return adminError($response, 'Invalid filename format: ' . substr($f, 0, 80));
      }
    }

    // Upper-bound to prevent abuse
    if (count($fileList) > 5000) {
      return adminError($response, 'Too many files, max 5000 per request');
    }

    $s3 = new S3Service($awsConfig);
    $batchSize = 64;
    $batches = array_chunk($fileList, $batchSize);

    foreach ($batches as $batch) {
      $batch = array_values(array_map('trim', $batch));

      // Look up file types
      $fileTypeMap = [];
      $placeholders = implode(',', array_fill(0, count($batch), '?'));
      $stmt = $link->prepare("SELECT type, filename FROM uploads_data WHERE filename IN ($placeholders)");
      $stmt->bind_param(str_repeat('s', count($batch)), ...$batch);
      $stmt->execute();
      $stmt->bind_result($type, $filename);
      while ($stmt->fetch()) {
        $fileTypeMap[$filename] = $type;
      }
      $stmt->close();

      // Delete from S3 and collect purge list
      $purgeBatch = [];
      foreach ($fileTypeMap as $filename => $type) {
        $objectName = ($type === 'video')
          ? 'av/' . $filename
          : (($type === 'profile') ? 'i/p/' . $filename : 'i/' . $filename);
        $currentSha256 = $s3->getS3ObjectHash(objectKey: $objectName, paidAccount: false);
        $purgeBatch[] = !empty($currentSha256) ? "{$filename}|{$currentSha256}" : $filename;
        $s3->deleteFromS3(objectKey: $objectName, paidAccount: false);
      }

      // Purge Cloudflare cache
      try {
        $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
        $result = $purger->purgeFiles($purgeBatch);
        if ($result !== false) {
          error_log("Mass delete purge result: " . json_encode($result));
        }
      } catch (Exception $e) {
        error_log("Mass delete PURGE error: " . $e->getMessage());
      }

      // Delete from uploads_data
      $stmt = $link->prepare("DELETE FROM uploads_data WHERE filename IN ($placeholders)");
      $stmt->bind_param(str_repeat('s', count($batch)), ...$batch);
      $stmt->execute();
      $stmt->close();

      // Delete from upload_attempts (filename without extension)
      $batchAttempts = array_map(fn($f) => pathinfo($f, PATHINFO_FILENAME), $batch);
      $stmt = $link->prepare("DELETE FROM upload_attempts WHERE filename IN ($placeholders)");
      $stmt->bind_param(str_repeat('s', count($batchAttempts)), ...$batchAttempts);
      $stmt->execute();
      $stmt->close();
    }

    return adminSuccess($response, ['deleted' => count($fileList)]);
  });

})->add(adminOnlyMiddleware());

// =============================================================================
// Promotions routes — admin only
// =============================================================================

$app->group('/admin/promotions', function (RouteCollectorProxy $group) {

  /**
   * GET /admin/promotions
   * List current/future and past promotions.
   * Replaces: promo.php GET (data portion)
   */
  $group->get('', function (Request $request, Response $response) {
    global $link;

    Plans::getInstance();
    $promotions = new Promotions($link);

    $current = $promotions->getCurrentAndFuturePromotions();
    $past = $promotions->getPastPromotions();

    // Build plan name map for frontend
    $plans = [];
    foreach (Plans::$PLANS as $planId => $plan) {
      $plans[] = ['id' => $plan->id, 'name' => $plan->name];
    }

    return adminJson($response, [
      'current' => $current,
      'past' => $past,
      'plans' => $plans,
    ]);
  });

  /**
   * POST /admin/promotions/add
   * Add a new promotion.
   * Replaces: promo.php add_promotion POST
   */
  $group->post('/add', function (Request $request, Response $response) {
    global $link;

    $body = $request->getParsedBody();
    $required = ['promotion_name', 'promotion_description', 'promotion_start_time', 'promotion_end_time', 'promotion_percentage', 'promotion_applicable_plans', 'promotion_type'];
    foreach ($required as $field) {
      if (empty($body[$field])) {
        return adminError($response, "Missing required field: $field");
      }
    }

    // Validate promotion type
    $promoType = $body['promotion_type'];
    if (!in_array($promoType, ['perPlan', 'global'], true)) {
      return adminError($response, 'Invalid promotion type');
    }

    // Validate percentage (0-100)
    $percentage = intval($body['promotion_percentage']);
    if ($percentage < 0 || $percentage > 100) {
      return adminError($response, 'Percentage must be between 0 and 100');
    }

    // Validate datetime strings
    if (strtotime($body['promotion_start_time']) === false || strtotime($body['promotion_end_time']) === false) {
      return adminError($response, 'Invalid date/time format');
    }

    // Validate plan IDs are integers
    $rawPlans = is_array($body['promotion_applicable_plans'])
      ? $body['promotion_applicable_plans']
      : explode(",", $body['promotion_applicable_plans']);
    $validPlans = array_filter(array_map('intval', $rawPlans), fn($id) => $id > 0);
    if (empty($validPlans)) {
      return adminError($response, 'At least one valid plan ID is required');
    }

    $promotionData = [
      'promotion_name' => $body['promotion_name'],
      'promotion_description' => $body['promotion_description'],
      'promotion_start_time' => $body['promotion_start_time'],
      'promotion_end_time' => $body['promotion_end_time'],
      'promotion_percentage' => $percentage,
      'promotion_applicable_plans' => implode(",", $validPlans),
      'promotion_type' => $promoType,
    ];

    $promotions = new Promotions($link);
    $promotions->addPromotion($promotionData);

    return adminSuccess($response);
  });

  /**
   * POST /admin/promotions/update
   * Update an existing promotion.
   * Replaces: promo.php update_promotion POST
   */
  $group->post('/update', function (Request $request, Response $response) {
    global $link;

    $body = $request->getParsedBody();
    $id = isset($body['id']) ? intval($body['id']) : 0;

    if ($id <= 0) {
      return adminError($response, 'Promotion ID is required');
    }

    // Validate promotion type
    $promoType = $body['promotion_type'] ?? 'perPlan';
    if (!in_array($promoType, ['perPlan', 'global'], true)) {
      return adminError($response, 'Invalid promotion type');
    }

    // Validate percentage
    $percentage = intval($body['promotion_percentage'] ?? 0);
    if ($percentage < 0 || $percentage > 100) {
      return adminError($response, 'Percentage must be between 0 and 100');
    }

    // Validate datetime strings if provided
    $startTime = $body['promotion_start_time'] ?? '';
    $endTime = $body['promotion_end_time'] ?? '';
    if ($startTime !== '' && strtotime($startTime) === false) {
      return adminError($response, 'Invalid start time format');
    }
    if ($endTime !== '' && strtotime($endTime) === false) {
      return adminError($response, 'Invalid end time format');
    }

    // Validate plan IDs
    $rawPlans = is_array($body['promotion_applicable_plans'] ?? null)
      ? $body['promotion_applicable_plans']
      : explode(",", $body['promotion_applicable_plans'] ?? '');
    $validPlans = array_filter(array_map('intval', $rawPlans), fn($pid) => $pid > 0);

    $promotionData = [
      'promotion_name' => $body['promotion_name'] ?? '',
      'promotion_description' => $body['promotion_description'] ?? '',
      'promotion_start_time' => $startTime,
      'promotion_end_time' => $endTime,
      'promotion_percentage' => $percentage,
      'promotion_applicable_plans' => implode(",", $validPlans),
      'promotion_type' => $promoType,
    ];

    $promotions = new Promotions($link);
    $promotions->updatePromotion($id, $promotionData);

    return adminSuccess($response);
  });

  /**
   * POST /admin/promotions/delete
   * Delete a promotion.
   * Replaces: promo.php delete_promotion POST
   */
  $group->post('/delete', function (Request $request, Response $response) {
    global $link;

    $body = $request->getParsedBody();
    $id = isset($body['id']) ? intval($body['id']) : 0;

    if ($id <= 0) {
      return adminError($response, 'Promotion ID is required');
    }

    $promotions = new Promotions($link);
    $promotions->deletePromotion($id);

    return adminSuccess($response);
  });

})->add(adminOnlyMiddleware());

// =============================================================================
// Stats routes — admin only
// =============================================================================

$app->group('/admin/stats', function (RouteCollectorProxy $group) {

  /**
   * GET /admin/stats/uploads
   * Free uploads statistics.
   * Replaces: stats.php
   */
  $group->get('/uploads', function (Request $request, Response $response) {
    global $link;

    // Breakdown by type
    $sql = "SELECT SUM(file_size) AS total_size, COUNT(*) AS total_count, type FROM uploads_data GROUP BY type";
    $result = $link->query($sql);

    $breakdown = [];
    $totalSize = 0;
    $totalCount = 0;

    while ($row = $result->fetch_assoc()) {
      $breakdown[] = [
        'type' => $row['type'],
        'count' => (int) $row['total_count'],
        'sizeBytes' => (int) $row['total_size'],
      ];
      $totalSize += $row['total_size'];
      $totalCount += $row['total_count'];
    }

    // Daily breakdown
    $sql = "SELECT DATE(upload_date) AS upload_day, COUNT(*) AS upload_count, SUM(file_size) AS total_size
            FROM uploads_data
            GROUP BY DATE(upload_date)
            ORDER BY upload_day DESC";
    $result = $link->query($sql);

    $daily = [];
    while ($row = $result->fetch_assoc()) {
      $daily[] = [
        'date' => $row['upload_day'],
        'count' => (int) $row['upload_count'],
        'sizeBytes' => (int) $row['total_size'],
      ];
    }

    return adminJson($response, [
      'breakdown' => $breakdown,
      'totalCount' => $totalCount,
      'totalSizeBytes' => $totalSize,
      'daily' => $daily,
    ]);
  });

  /**
   * GET /admin/stats/accounts
   * Account (paid) uploads statistics.
   * Replaces: account_stats.php
   */
  $group->get('/accounts', function (Request $request, Response $response) {
    global $link;

    // Breakdown by mime type
    $sql = "SELECT SUM(file_size) AS total_size, COUNT(*) AS total_count, mime_type FROM users_images GROUP BY mime_type";
    $result = $link->query($sql);

    $breakdown = [];
    $totalSize = 0;
    $totalCount = 0;

    while ($row = $result->fetch_assoc()) {
      $breakdown[] = [
        'mimeType' => $row['mime_type'],
        'count' => (int) $row['total_count'],
        'sizeBytes' => (int) $row['total_size'],
      ];
      $totalSize += $row['total_size'];
      $totalCount += $row['total_count'];
    }

    // Daily breakdown
    $sql = "SELECT DATE(created_at) AS upload_day, COUNT(*) AS upload_count, SUM(file_size) AS total_size
            FROM users_images
            GROUP BY DATE(created_at)
            ORDER BY created_at DESC";
    $result = $link->query($sql);

    $daily = [];
    while ($row = $result->fetch_assoc()) {
      $daily[] = [
        'date' => $row['upload_day'],
        'count' => (int) $row['upload_count'],
        'sizeBytes' => (int) $row['total_size'],
      ];
    }

    return adminJson($response, [
      'breakdown' => $breakdown,
      'totalCount' => $totalCount,
      'totalSizeBytes' => $totalSize,
      'daily' => $daily,
    ]);
  });

})->add(adminOnlyMiddleware());

// =============================================================================
// Helper functions for moderation actions
// =============================================================================

/**
 * Delete a file from S3, ban from blossom, insert into rejected_files, delete from uploads_data.
 */
function deleteAndRejectUpload($link, S3Service $s3, BlossomFrontEndAPI $blossomAPI, int $id, string $filename, string $type): void
{
  $objectName = match ($type) {
    'picture' => 'i/' . $filename,
    'profile' => 'i/p/' . $filename,
    default   => 'av/' . $filename,
  };

  try {
    $currentSha256 = $s3->getS3ObjectHash(objectKey: $objectName, paidAccount: false);
    $s3->deleteFromS3(objectKey: $objectName, paidAccount: false);
    $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
    $purgeFilename = !empty($currentSha256) ? "{$filename}|{$currentSha256}" : $filename;
    $result = $purger->purgeFiles([$purgeFilename]);
    if ($result !== false) {
      error_log(json_encode($result));
    }
  } catch (Exception $e) {
    error_log("PURGE error occurred: " . $e->getMessage());
  }

  // Ban from blossom if hash exists
  banFromBlossomIfHashExists($link, $blossomAPI, $id);

  // Insert into rejected_files
  $stmt = $link->prepare("INSERT INTO rejected_files (filename, type) VALUES (?, ?)");
  $stmt->bind_param("ss", $filename, $type);
  $stmt->execute();
  $stmt->close();

  // Delete from uploads_data
  $stmt = $link->prepare("DELETE FROM uploads_data WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->close();
}

/**
 * Ban user (blacklist IPs/UAs/npubs from logs), delete file, insert into rejected_files.
 */
function banUserAndDeleteUpload($link, S3Service $s3, BlossomFrontEndAPI $blossomAPI, array $csamReportingConfig, int $id, string $filename, string $type): void
{
  $file_sha256_hash = pathinfo($filename, PATHINFO_FILENAME);

  // Fetch upload logs from R2
  $logsJSON = fetchJsonFromR2Bucket(
    prefix: $file_sha256_hash,
    endPoint: $csamReportingConfig['r2EndPoint'],
    accessKey: $csamReportingConfig['r2AccessKey'],
    secretKey: $csamReportingConfig['r2SecretKey'],
    bucket: $csamReportingConfig['r2LogsBucket']
  );

  if (!empty($logsJSON)) {
    $stmt = $link->prepare("INSERT INTO blacklist (npub, ip, user_agent, reason) VALUES (?, ?, ?, ?)");
    foreach ($logsJSON as $log) {
      $logData = json_decode($log['uploadedFileInfo'], true);
      $ip = $logData['realIp'];
      $ua = $logData['userAgent'];
      $npub = $log['uploadNpub'] ?? "anonymous";
      $blockReason = 'BANNED';
      $stmt->bind_param("ssss", $npub, $ip, $ua, $blockReason);
      $stmt->execute();
      $blossomAPI->banUser($npub, 'Repeated TOS Violation or legal reasons');
    }
    $stmt->close();
  }

  // Delete the file (reuse reject helper logic)
  deleteAndRejectUpload($link, $s3, $blossomAPI, $id, $filename, $type);
}

/**
 * Full CSAM workflow: archive evidence, blacklist, delete.
 * Returns true on success, or an error message string on failure.
 */
function processCsamReport($link, S3Service $s3, BlossomFrontEndAPI $blossomAPI, array $csamReportingConfig, int $id, string $filename, string $type): string|bool
{
  $file_sha256_hash = pathinfo($filename, PATHINFO_FILENAME);
  $objectName = match ($type) {
    'picture' => 'i/' . $filename,
    'profile' => 'i/p/' . $filename,
    default   => 'av/' . $filename,
  };

  // Download offending media to temp file
  $tempFile = tempnam(sys_get_temp_dir(), 'csam_');
  $tempFile = $s3->downloadObjectR2(key: $objectName, saveAs: $tempFile, paidAccount: false);

  // Fetch upload logs from R2
  $logsJSON = fetchJsonFromR2Bucket(
    prefix: $file_sha256_hash,
    endPoint: $csamReportingConfig['r2EndPoint'],
    accessKey: $csamReportingConfig['r2AccessKey'],
    secretKey: $csamReportingConfig['r2SecretKey'],
    bucket: $csamReportingConfig['r2LogsBucket']
  );

  if (empty($logsJSON)) {
    error_log("Failed to fetch logs for CSAM report: {$file_sha256_hash}");
  }

  $resLogStore = true;
  if (!empty($logsJSON)) {
    $evidenceLogKey = "{$file_sha256_hash}/uploads_log.json";
    $resLogStore = storeJSONObjectToR2Bucket(
      object: $logsJSON,
      destinationKey: $evidenceLogKey,
      destinationBucket: $csamReportingConfig['r2EvidenceBucket'],
      endPoint: $csamReportingConfig['r2EndPoint'],
      accessKey: $csamReportingConfig['r2AccessKey'],
      secretKey: $csamReportingConfig['r2SecretKey'],
    );
  }

  // Store the evidence file
  $evidenceFileKey = "{$file_sha256_hash}/{$filename}";
  $resFileStore = storeToR2Bucket(
    sourceFilePath: $tempFile,
    destinationKey: $evidenceFileKey,
    destinationBucket: $csamReportingConfig['r2EvidenceBucket'],
    endPoint: $csamReportingConfig['r2EndPoint'],
    accessKey: $csamReportingConfig['r2AccessKey'],
    secretKey: $csamReportingConfig['r2SecretKey'],
  );

  if (($resLogStore === false && !empty($logsJSON)) || $resFileStore === false) {
    unlink($tempFile);
    return 'Failed to store evidence logs or file';
  }

  unlink($tempFile);

  // Store case info in DB
  $stmt = $link->prepare("INSERT INTO identified_csam_cases (identified_by_npub, evidence_location_url, file_sha256_hash, logs) VALUES (?, ?, ?, ?)");
  $evidenceReportingNpub = $_SESSION['usernpub'];
  $evidenceLocationURL = "{$csamReportingConfig['r2EndPoint']}/{$csamReportingConfig['r2EvidenceBucket']}/{$file_sha256_hash}/";
  $evidenceJSONLogs = json_encode($logsJSON);
  $stmt->bind_param("ssss", $evidenceReportingNpub, $evidenceLocationURL, $file_sha256_hash, $evidenceJSONLogs);
  $stmt->execute();
  $stmt->close();

  // Blacklist all associated users
  if (!empty($logsJSON)) {
    $stmt = $link->prepare("INSERT INTO blacklist (npub, ip, user_agent, reason) VALUES (?, ?, ?, ?)");
    foreach ($logsJSON as $log) {
      $logData = json_decode($log['uploadedFileInfo'], true);
      $ip = $logData['realIp'];
      $ua = $logData['userAgent'];
      $npub = $log['uploadNpub'] ?? "anonymous";
      $blockReason = 'CSAM';
      $stmt->bind_param("ssss", $npub, $ip, $ua, $blockReason);
      $stmt->execute();
      $blossomAPI->banUser($npub, 'Confirmed CSAM report');
    }
    $stmt->close();
  }

  // Delete file from S3, purge CF, ban blossom hash, reject, delete from DB
  deleteAndRejectUpload($link, $s3, $blossomAPI, $id, $filename, $type);

  return true;
}

/**
 * Check if upload has a blossom hash and ban it if so.
 */
function banFromBlossomIfHashExists($link, BlossomFrontEndAPI $blossomAPI, int $id): void
{
  $blossomHash = null;
  $stmt = $link->prepare("SELECT blossom_hash FROM uploads_data WHERE id = ?");
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $stmt->bind_result($blossomHash);
  $stmt->fetch();
  $stmt->close();

  if ($blossomHash !== null) {
    $blossomAPI->banMedia($blossomHash);
  }
}
