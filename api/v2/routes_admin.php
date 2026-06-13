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
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/IpAccessControl.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/CymruWhois.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/LegacyBlacklist.class.php';

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

/**
 * Delete a set of uploads, orphan-safely: S3 object first, then one
 * consolidated Cloudflare purge, per-hash blossom bans, a single multi-row
 * rejected_files insert, and finally the uploads_data delete. Shared by
 * /admin/moderation/reject-batch and /admin/moderation/ban-purge so the two
 * paths can never drift. $ids must already be validated as positive ints.
 * Returns a per-id result list: [{id, ok, error?}].
 */
function rejectUploadsByIds(mysqli $link, array $awsConfig, array $ids): array
{
  if ($ids === []) {
    return [];
  }

  // Single SELECT to fetch every row's filename + type + blossom_hash. Any
  // id not found here will be reported as a failure in the result map.
  $placeholders = implode(',', array_fill(0, count($ids), '?'));
  $stmt = $link->prepare(
    "SELECT id, filename, type, blossom_hash
       FROM uploads_data
      WHERE id IN ($placeholders)"
  );
  $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
  $stmt->execute();
  $rs = $stmt->get_result();
  /** @var array<int,array{filename:string,type:string,blossom_hash:?string}> $rows */
  $rows = [];
  while ($r = $rs->fetch_assoc()) {
    $rows[(int) $r['id']] = [
      'filename' => (string) $r['filename'],
      'type' => (string) ($r['type'] ?? ''),
      'blossom_hash' => $r['blossom_hash'] !== null ? (string) $r['blossom_hash'] : null,
    ];
  }
  $rs->free();
  $stmt->close();

  /** @var list<array{id:int,ok:bool,error?:string}> $results */
  $results = [];
  $foundIds = [];      // ids we actually processed (subset of $ids)
  $purgeBatch = [];    // CF cache keys (filename or filename|sha256)
  $blossomHashes = []; // distinct blossom hashes to ban
  $rejectedRows = [];  // [filename, type] tuples for rejected_files insert

  $s3 = new S3Service($awsConfig);

  foreach ($ids as $id) {
    if (!isset($rows[$id])) {
      $results[] = ['id' => $id, 'ok' => false, 'error' => 'Upload not found'];
      continue;
    }
    $row = $rows[$id];
    $filename = $row['filename'];
    $type     = $row['type'];

    $objectKey = match ($type) {
      'picture' => 'i/' . $filename,
      'profile' => 'i/p/' . $filename,
      default   => 'av/' . $filename,
    };

    // CRITICAL ORDERING: S3 delete first, DB delete last. If we ever lose
    // the uploads_data row but leave the S3 object behind, the file becomes
    // an unreferenced orphan we can never find again. So an S3 failure here
    // SKIPS the rest of the per-item bookkeeping (no rejected_files insert,
    // no DELETE) — the DB row is preserved as our pointer for retry.
    // (deleteFromS3 already swallows AWS exceptions internally and reports
    //  via boolean return; NoSuchKey counts as success.)
    $s3DeleteOk = false;
    try {
      $s3DeleteOk = $s3->deleteFromS3(objectKey: $objectKey, paidAccount: false) === true;
    } catch (\Throwable $e) {
      error_log("rejectUploadsByIds S3 exception for id {$id} ({$filename}): " . $e->getMessage());
    }

    if (!$s3DeleteOk) {
      error_log("rejectUploadsByIds: S3 delete failed for id {$id} ({$filename}); preserving DB row for retry");
      $results[] = [
        'id' => $id,
        'ok' => false,
        'error' => 'S3 delete failed — DB row preserved for retry',
      ];
      continue;
    }

    $purgeBatch[] = $filename;
    if ($row['blossom_hash'] !== null && $row['blossom_hash'] !== '') {
      $blossomHashes[$row['blossom_hash']] = true;
    }

    $foundIds[] = $id;
    $rejectedRows[] = [$filename, $type];
    $results[] = ['id' => $id, 'ok' => true];
  }

  // Consolidated Cloudflare purge — one call per batch instead of per item.
  if ($purgeBatch !== []) {
    try {
      $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
      $purger->purgeFiles($purgeBatch);
    } catch (\Throwable $e) {
      error_log('rejectUploadsByIds CF purge error: ' . $e->getMessage());
    }
  }

  // Per-hash blossom ban. No bulk surface upstream; sequential calls.
  if ($blossomHashes !== []) {
    $blossomAPI = new BlossomFrontEndAPI($_SERVER['BLOSSOM_API_URL'], $_SERVER['BLOSSOM_API_KEY']);
    foreach (array_keys($blossomHashes) as $hash) {
      try {
        $blossomAPI->banMedia($hash);
      } catch (\Throwable $e) {
        error_log("rejectUploadsByIds blossom ban failed for hash {$hash}: " . $e->getMessage());
      }
    }
  }

  if ($foundIds !== []) {
    // Single multi-row INSERT into rejected_files.
    $valuesSql = implode(',', array_fill(0, count($rejectedRows), '(?, ?)'));
    $insTypes  = str_repeat('ss', count($rejectedRows));
    $insArgs   = [];
    foreach ($rejectedRows as [$fn, $tp]) { $insArgs[] = $fn; $insArgs[] = $tp; }
    try {
      $stmt = $link->prepare("INSERT INTO rejected_files (filename, type) VALUES $valuesSql");
      $stmt->bind_param($insTypes, ...$insArgs);
      $stmt->execute();
      $stmt->close();
    } catch (\Throwable $e) {
      // If the bulk insert fails, the DELETE below is still safe (the file
      // is gone from S3 already). Log loudly so a sweeper can backfill.
      error_log('rejectUploadsByIds rejected_files insert failed: ' . $e->getMessage()
        . ' — affected filenames: ' . implode(',', array_column($rejectedRows, 0)));
    }

    // Single DELETE for the whole batch.
    $delPlaceholders = implode(',', array_fill(0, count($foundIds), '?'));
    try {
      $stmt = $link->prepare("DELETE FROM uploads_data WHERE id IN ($delPlaceholders)");
      $stmt->bind_param(str_repeat('i', count($foundIds)), ...$foundIds);
      $stmt->execute();
      $stmt->close();
    } catch (\Throwable $e) {
      // This one matters — if we can't delete the row, the upload reappears
      // in admin queues. Mark all in-batch ids as failed so the caller
      // retries.
      error_log('rejectUploadsByIds uploads_data delete failed: ' . $e->getMessage());
      $foundSet = array_flip($foundIds);
      foreach ($results as &$r) {
        if (isset($foundSet[$r['id']])) {
          $r['ok'] = false;
          $r['error'] = 'Database delete failed';
        }
      }
      unset($r);
    }
  }

  return $results;
}

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
      if (!deleteAndRejectUpload($link, $s3, $blossomFrontEndAPI, $id, $filename, $type)) {
        return adminError($response, 'Reject failed (S3 delete error). The upload was preserved for retry.', 500);
      }
    } elseif ($status === 'ban') {
      // Ban requires admin level
      $perm = new Permission();
      if (!$perm->isAdmin()) {
        return adminError($response, 'Unauthorized: Ban actions require admin privileges', 403);
      }
      // banUserAndDeleteUpload writes the npub ban first then calls
      // deleteAndRejectUpload — even if delete fails, the user is now
      // banned. Surface the partial state to the admin so they can retry.
      if (!banUserAndDeleteUpload($link, $s3, $blossomFrontEndAPI, $csamReportingConfig, $id, $filename, $type)) {
        return adminError($response, 'User was banned but file delete failed. Retry the reject action to clean up.', 500);
      }
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

  /**
   * POST /admin/moderation/reject-batch
   * Body: { ids: [int, ...] } — up to MAX_REJECT_BATCH per call.
   *
   * Bulk equivalent of `/status` with status=rejected. Same semantics as
   * deleteAndRejectUpload (S3 delete + CF purge + blossom hash ban + insert
   * into rejected_files + delete from uploads_data) but consolidates the
   * Cloudflare purge into one call per batch and uses single multi-row
   * INSERT/DELETE for the DB writes — the per-item per-HTTP-call overhead
   * (CF purge in particular) is what made bulk offender cleanup slow.
   *
   * S3 deletes and blossom hash bans remain per-item — those libraries don't
   * expose batch surfaces — but they're fast HTTP calls.
   *
   * Returns per-id success/failure so the caller can show granular progress
   * rather than bailing on first error.
   */
  $group->post('/reject-batch', function (Request $request, Response $response) {
    global $link, $awsConfig;

    // Cap aligned with what the front-end sends; CF purge accepts plenty more
    // but we keep the chunk small so per-batch latency stays predictable and
    // a single failure can't lose too much work.
    $MAX_REJECT_BATCH = 30;

    $data = $request->getParsedBody();
    if (!is_array($data) || !isset($data['ids']) || !is_array($data['ids'])) {
      return adminError($response, 'Invalid payload, expecting an "ids" array');
    }

    $ids = array_values(array_unique(array_filter(
      array_map('intval', $data['ids']),
      fn(int $id): bool => $id > 0,
    )));

    if ($ids === []) {
      return adminError($response, 'No valid IDs provided');
    }
    if (count($ids) > $MAX_REJECT_BATCH) {
      return adminError($response, "Too many IDs, max $MAX_REJECT_BATCH per request");
    }

    $results = rejectUploadsByIds($link, $awsConfig, $ids);

    $succeeded = 0;
    $failed = 0;
    foreach ($results as $r) {
      $r['ok'] ? $succeeded++ : $failed++;
    }

    return adminJson($response, [
      'success' => $failed === 0,
      'processed' => count($results),
      'succeeded' => $succeeded,
      'failed' => $failed,
      'results' => $results,
    ]);
  });

  /**
   * POST /admin/moderation/ban-purge   body: { npub, after_id, limit (max 50) }
   * Ban a user and delete ALL their media server-side, in keyset batches, so a
   * user with tens of thousands of uploads is handled without a request
   * timeout and without the old 500-row DOM cap. The first call (after_id == 0)
   * writes the ban and returns the total count; the caller then polls with the
   * returned cursor until { more: false }. Admin-only (destructive).
   */
  $group->post('/ban-purge', function (Request $request, Response $response) {
    global $link, $awsConfig;
    @set_time_limit(0);

    $perm = new Permission();
    if (!$perm->isAdmin()) {
      return adminError($response, 'Unauthorized: ban-purge requires admin privileges', 403);
    }

    $body    = $request->getParsedBody();
    $npub    = trim($body['npub'] ?? '');
    $afterId = (int) ($body['after_id'] ?? 0);
    $limit   = max(1, min(50, (int) ($body['limit'] ?? 25)));

    if (!preg_match('/^npub1[a-z0-9]{20,90}$/', $npub)) {
      return adminError($response, 'Invalid npub');
    }

    $banned = false;
    $total  = null;
    if ($afterId === 0) {
      // Ban once, on the first batch. Ban writes are best-effort but logged;
      // the purge proceeds regardless so media still gets removed.
      try {
        (new LegacyBlacklist($link))->add($npub, null, null, 'BANNED');
      } catch (\Throwable $e) {
        error_log("ban-purge blacklist add failed for {$npub}: " . $e->getMessage());
      }
      try {
        $blossomAPI = new BlossomFrontEndAPI($_SERVER['BLOSSOM_API_URL'], $_SERVER['BLOSSOM_API_KEY']);
        $blossomAPI->banUser($npub);
      } catch (\Throwable $e) {
        error_log("ban-purge blossom banUser failed for {$npub}: " . $e->getMessage());
      }
      $banned = true;

      $cStmt = $link->prepare("SELECT COUNT(*) AS c FROM uploads_data WHERE usernpub = ?");
      $cStmt->bind_param('s', $npub);
      $cStmt->execute();
      $total = (int) ($cStmt->get_result()->fetch_assoc()['c'] ?? 0);
      $cStmt->close();
    }

    // Next page of this npub's uploads. Keyset on id ASC: successfully deleted
    // rows fall out of the table, and any row that fails to delete keeps an id
    // <= cursor so the next call (id > cursor) skips it — no infinite loop.
    $stmt = $link->prepare(
      "SELECT id FROM uploads_data WHERE usernpub = ? AND id > ? ORDER BY id ASC LIMIT ?"
    );
    $stmt->bind_param('sii', $npub, $afterId, $limit);
    $stmt->execute();
    $rs = $stmt->get_result();
    $ids = [];
    while ($r = $rs->fetch_assoc()) {
      $ids[] = (int) $r['id'];
    }
    $stmt->close();

    $results = rejectUploadsByIds($link, $awsConfig, $ids);

    $succeeded = 0;
    $failed = 0;
    foreach ($results as $r) {
      $r['ok'] ? $succeeded++ : $failed++;
    }

    return adminJson($response, [
      'banned'    => $banned,
      'total'     => $total,
      'cursor'    => $ids === [] ? $afterId : max($ids),
      'scanned'   => count($ids),
      'succeeded' => $succeeded,
      'failed'    => $failed,
      'more'      => count($ids) === $limit,
      'results'   => $results,
    ]);
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
    } catch (\Throwable $e) {
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
      // previewReport() returns the raw XML string. adminJson is typed
      // `array $data`, so wrap the XML in an envelope. JS already pretty-
      // prints whatever shape it receives via JSON.stringify().
      $xml = $ncmecHandler->previewReport();
      return adminJson($response, ['xml' => $xml]);
    } catch (\Throwable $e) {
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
    } catch (\Throwable $e) {
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
    } catch (\Throwable $e) {
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
    } catch (\Throwable $e) {
      error_log("Admin submit-single error for incident {$incidentId}: " . $e->getMessage());
      return adminJson($response, [
        'success' => false,
        'incidentId' => $incidentId,
        'error' => 'Error submitting report',
      ], 500);
    }
  });

  /**
   * GET /admin/csam/offender-uploads/{caseId}
   * For an already-NCMEC-submitted case, return the offender's npub plus every
   * still-active upload they own. Caller iterates over upload IDs and rejects
   * each via /admin/moderation/status (status=rejected) — same per-item flow as
   * approve.php's "Reject All & Ban User" button.
   *
   * Refuses to act when:
   *   - the case has not been submitted (numeric NCMEC report id),
   *   - or the offender npub cannot be confidently extracted from logs.
   */
  $group->get('/offender-uploads/{caseId:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $caseId = (int) $args['caseId'];

    $stmt = $link->prepare('SELECT logs, ncmec_report_id FROM identified_csam_cases WHERE id = ?');
    $stmt->bind_param('i', $caseId);
    $stmt->execute();
    $caseRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($caseRow === null) {
      return adminError($response, 'Case not found', 404);
    }

    $reportId = (string) ($caseRow['ncmec_report_id'] ?? '');
    if (!csamCaseAllowsOffenderCleanup($reportId)) {
      return adminError($response, 'Case is not in a delete-eligible state (need numeric NCMEC report id or EVIDENCE_EXPIRED).', 422);
    }

    $npub = extractOffenderNpubFromLogs($caseRow['logs']);
    if ($npub === null || !isLikelyValidNpub($npub)) {
      return adminError($response, 'Unable to determine offender npub from case logs.', 422);
    }

    // Only list uploads still alive (not already rejected or csam'd).
    // The usernpub <> '' / IS NOT NULL guards are belt-and-suspenders against
    // ever scanning legacy anonymous uploads — `extractOffenderNpubFromLogs`
    // already refuses to return empty/null, but defending at the SQL layer
    // means a future bug in extraction can never sweep those rows.
    $stmt = $link->prepare(
      "SELECT id, filename, type, approval_status
         FROM uploads_data
        WHERE usernpub = ?
          AND usernpub <> ''
          AND usernpub IS NOT NULL
          AND approval_status NOT IN ('rejected', 'csam')
        ORDER BY upload_date DESC"
    );
    $stmt->bind_param('s', $npub);
    $stmt->execute();
    $result = $stmt->get_result();
    $uploads = [];
    while ($r = $result->fetch_assoc()) {
      $uploads[] = [
        'id' => (int) $r['id'],
        'filename' => (string) $r['filename'],
        'type' => (string) $r['type'],
        'approval_status' => (string) $r['approval_status'],
      ];
    }
    $stmt->close();

    return adminJson($response, [
      'caseId' => $caseId,
      'reportId' => $reportId,
      'npub' => $npub,
      'count' => count($uploads),
      'uploads' => $uploads,
    ]);
  });

  /**
   * GET /admin/csam/submitted-offenders?days=N
   * Bulk variant of /offender-uploads/{caseId}: collect every offender npub
   * extracted from CSAM cases that were *successfully* submitted to NCMEC in
   * the past N days, dedupe by npub, and attach each offender's remaining
   * active uploads. The caller iterates the flat list of upload ids.
   *
   * "Successfully submitted" = ncmec_report_id is non-empty AND not TEST_/
   * FALSE_MATCH/Null:Technical Error AND is_numeric (final guard in PHP).
   */
  $group->get('/submitted-offenders', function (Request $request, Response $response) {
    global $link;
    $params = $request->getQueryParams();
    $days = isset($params['days']) ? min(90, max(1, (int) $params['days'])) : 7;

    $stmt = $link->prepare(
      "SELECT id, logs, ncmec_report_id
         FROM identified_csam_cases
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND ncmec_report_id IS NOT NULL
          AND ncmec_report_id NOT LIKE 'TEST_%'
          AND ncmec_report_id <> 'FALSE_MATCH'
          AND ncmec_report_id <> 'Null: Technical Error'
        ORDER BY timestamp ASC"
    );
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();

    /** @var array<string,array{case_ids:list<int>,report_ids:list<string>}> $offenderMap */
    $offenderMap = [];
    while ($r = $result->fetch_assoc()) {
      // Final guard — only truly numeric NCMEC report ids count as "submitted".
      if (!is_numeric((string) $r['ncmec_report_id'])) continue;
      $npub = extractOffenderNpubFromLogs($r['logs']);
      if ($npub === null) continue;
      if (!isset($offenderMap[$npub])) {
        $offenderMap[$npub] = ['case_ids' => [], 'report_ids' => []];
      }
      $offenderMap[$npub]['case_ids'][]   = (int) $r['id'];
      $offenderMap[$npub]['report_ids'][] = (string) $r['ncmec_report_id'];
    }
    $stmt->close();

    $offenders = [];
    $totalUploads = 0;
    if ($offenderMap !== []) {
      // Re-validate every key — extractOffenderNpubFromLogs already does, but
      // a future refactor breaking that must not let a bad value reach the IN
      // clause.
      $npubs = array_values(array_filter(array_keys($offenderMap), 'isLikelyValidNpub'));

      // Group by npub in PHP from a single SELECT instead of one query per
      // offender. The usernpub <> '' / IS NOT NULL guards are belt-and-
      // suspenders against ever scanning legacy anonymous uploads.
      $uploadsByNpub = [];
      if ($npubs !== []) {
        $placeholders = implode(',', array_fill(0, count($npubs), '?'));
        $listStmt = $link->prepare(
          "SELECT id, filename, type, usernpub
             FROM uploads_data
            WHERE usernpub IN ($placeholders)
              AND usernpub <> ''
              AND usernpub IS NOT NULL
              AND approval_status NOT IN ('rejected', 'csam')
            ORDER BY upload_date DESC"
        );
        $listStmt->bind_param(str_repeat('s', count($npubs)), ...$npubs);
        $listStmt->execute();
        $rs = $listStmt->get_result();
        while ($u = $rs->fetch_assoc()) {
          $uploadsByNpub[(string) $u['usernpub']][] = [
            'id' => (int) $u['id'],
            'filename' => (string) $u['filename'],
            'type' => (string) $u['type'],
          ];
        }
        $rs->free();
        $listStmt->close();
      }

      foreach ($offenderMap as $npub => $info) {
        if (!isLikelyValidNpub($npub)) continue;
        $uploads = $uploadsByNpub[$npub] ?? [];
        $totalUploads += count($uploads);
        $offenders[] = [
          'npub' => $npub,
          'case_ids' => $info['case_ids'],
          'report_ids' => $info['report_ids'],
          'uploads' => $uploads,
          'remaining_count' => count($uploads),
        ];
      }
    }

    return adminJson($response, [
      'days' => $days,
      'total_offenders' => count($offenders),
      'total_uploads' => $totalUploads,
      'offenders' => $offenders,
    ]);
  });

})->add(adminOnlyMiddleware());

// =============================================================================
// Security routes — admin only
//   IP blocklist/whitelist management + Team Cymru WHOIS lookup.
//   First phase: management UI only — enforcement is wired separately.
// =============================================================================

/**
 * Pull IP candidates out of an identified_csam_cases.logs JSON blob.
 * Handles both Type 1 (filename-keyed with uploadedFileInfo JSON string)
 * and Type 2 (evidenceData.ViolationContentCollection / ReporteeIPAddress).
 *
 * @return list<array{ip:string,source:string,npub:?string,filename:?string,datetime:?string}>
 */
function extractIpCandidatesFromLogs(?string $logsJson): array
{
  if ($logsJson === null || $logsJson === '') return [];
  $data = json_decode($logsJson, true);
  if (!is_array($data)) return [];

  $out = [];
  $seen = [];
  $push = function (string $ip, string $source, ?string $npub, ?string $filename, ?string $datetime) use (&$out, &$seen) {
    $ip = trim($ip);
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) return;
    $key = $ip . '|' . $source;
    if (isset($seen[$key])) return;
    $seen[$key] = true;
    $out[] = [
      'ip' => $ip,
      'source' => $source,
      'npub' => $npub,
      'filename' => $filename,
      'datetime' => $datetime,
    ];
  };

  if (isset($data['evidenceData']) && is_array($data['evidenceData'])) {
    $ed = $data['evidenceData'];
    if (!empty($ed['ReporteeIPAddress'])) {
      $push((string) $ed['ReporteeIPAddress'], 'ReporteeIPAddress', $ed['ReporteeName'] ?? null, null, $ed['IncidentTime'] ?? null);
    }
    $vc = $ed['ViolationContentCollection'] ?? [];
    if (isset($vc['UploadIpAddress']) || isset($vc['LocationOfFile'])) {
      $vc = [$vc];
    }
    if (is_array($vc)) {
      foreach ($vc as $v) {
        if (!is_array($v)) continue;
        if (!empty($v['UploadIpAddress'])) {
          $push(
            (string) $v['UploadIpAddress'],
            'UploadIpAddress',
            null,
            $v['Name'] ?? null,
            $v['UploadDateTime'] ?? null
          );
        }
      }
    }
  } else {
    foreach ($data as $key => $entry) {
      if (!is_array($entry)) continue;
      $info = $entry['uploadedFileInfo'] ?? null;
      $infoArr = is_string($info) ? json_decode($info, true) : (is_array($info) ? $info : null);
      if (!is_array($infoArr)) continue;
      $ip = $infoArr['realIp'] ?? null;
      if (!is_string($ip) || $ip === '') continue;
      // CSAM-case logs are keyed by filename; R2 logs are keyed by R2 path.
      // Prefer the explicit fileName field; fall back to the key only if it
      // looks like a filename (contains "." with no "/").
      $filename = $entry['fileName'] ?? null;
      if ($filename === null && is_string($key) && str_contains($key, '.') && !str_contains($key, '/')) {
        $filename = $key;
      }
      $npub = $entry['uploadNpub'] ?? null;
      $datetime = isset($entry['uploadTime']) ? date('Y-m-d\TH:i:s\Z', (int) $entry['uploadTime']) : null;
      $push($ip, 'realIp', $npub, $filename, $datetime);
    }
  }

  return $out;
}

/**
 * A CSAM case is "delete-eligible" (offender confirmed, cleanup allowed) when
 * its NCMEC report id is either a real numeric report id or the manual
 * EVIDENCE_EXPIRED sentinel — i.e. the offender has been positively identified
 * even if the formal submission couldn't go through. TEST_/FALSE_MATCH/
 * Null:Technical Error/empty are NOT eligible.
 */
function csamCaseAllowsOffenderCleanup(?string $reportId): bool
{
  if ($reportId === null || $reportId === '') return false;
  if ($reportId === 'EVIDENCE_EXPIRED') return true;
  return is_numeric($reportId);
}

/**
 * Tight npub-shape validator. Real bech32 npub is `npub1` + 58 bech32 chars
 * (63 total). We allow [60, 100] to be tolerant of any future format drift but
 * still reject empty / "anonymous" / "Unknown" / partial sentinels — anything
 * that could land in `uploads_data.usernpub = ''` and bulk-match legacy
 * anonymous uploads.
 */
function isLikelyValidNpub(?string $n): bool
{
  if ($n === null) return false;
  $len = strlen($n);
  return $len >= 60 && $len <= 100 && str_starts_with($n, 'npub1');
}

/**
 * Pull the offender (uploader) npub out of an identified_csam_cases.logs JSON.
 *
 * Type 1 logs (filename-keyed): each entry has uploadNpub.
 * Type 2 logs (evidenceData):   ReporteeName carries the npub.
 *
 * Returns null when the npub cannot be confidently identified as a real npub.
 * Critical: the caller MUST treat null as "no actionable target" — never fall
 * back to an empty-string match, which would sweep up every legacy anonymous
 * upload (rows with usernpub = '' or NULL).
 */
function extractOffenderNpubFromLogs(?string $logsJson): ?string
{
  if ($logsJson === null || $logsJson === '') return null;
  $data = json_decode($logsJson, true);
  if (!is_array($data)) return null;

  if (isset($data['evidenceData']['ReporteeName'])) {
    $n = trim((string) $data['evidenceData']['ReporteeName']);
    if (isLikelyValidNpub($n)) return $n;
  }

  foreach ($data as $entry) {
    if (!is_array($entry)) continue;
    $n = isset($entry['uploadNpub']) ? trim((string) $entry['uploadNpub']) : '';
    if (isLikelyValidNpub($n)) return $n;
  }

  return null;
}

$app->group('/admin/security', function (RouteCollectorProxy $group) {

  /**
   * GET /admin/security/case-ip/{id}
   * Return IP candidates extracted from a CSAM case's logs column.
   */
  $group->get('/case-ip/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];

    $stmt = $link->prepare('SELECT logs FROM identified_csam_cases WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null) {
      return adminError($response, 'Incident not found', 404);
    }

    $candidates = extractIpCandidatesFromLogs($row['logs']);
    return adminJson($response, ['incidentId' => $id, 'candidates' => $candidates]);
  });

  /**
   * GET /admin/security/whois?ip=X
   * Team Cymru ASN/prefix/country/registry lookup.
   */
  $group->get('/whois', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    $ip = trim($params['ip'] ?? '');
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
      return adminError($response, 'Valid ip parameter is required');
    }

    try {
      $whois = new CymruWhois();
      $info = $whois->lookup($ip);
      if ($info === null) {
        return adminJson($response, ['ip' => $ip, 'found' => false]);
      }
      return adminJson($response, ['found' => true] + $info);
    } catch (\Throwable $e) {
      error_log('Admin whois error: ' . $e->getMessage());
      return adminError($response, 'WHOIS lookup failed', 500);
    }
  });

  /**
   * POST /admin/security/blocklist/check
   * Body: { ip: "...", userId?: "..." }
   * Returns whether the IP is currently blocked.
   */
  $group->post('/blocklist/check', function (Request $request, Response $response) {
    global $link;
    $body = $request->getParsedBody() ?? [];
    $ip = trim($body['ip'] ?? '');
    $userId = trim((string) ($body['userId'] ?? ''));
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
      return adminError($response, 'Valid ip is required');
    }

    $iac = new IpAccessControl($link);
    $match = $iac->findBlock($ip, $userId);
    return adminJson($response, [
      'ip' => $ip,
      'blocked' => $match !== null,
      'match' => $match,
    ]);
  });

  // -------------------------------------------------------------------------
  // Blocklist CRUD
  // -------------------------------------------------------------------------

  /**
   * GET /admin/security/blocklist
   * Query: source?, active_only?, limit?, offset?
   */
  $group->get('/blocklist', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $opts = [
      'limit' => isset($q['limit']) ? (int) $q['limit'] : 100,
      'offset' => isset($q['offset']) ? (int) $q['offset'] : 0,
      'active_only' => !empty($q['active_only']),
    ];
    if (isset($q['source']) && $q['source'] !== '') {
      $opts['source'] = (string) $q['source'];
    }

    $iac = new IpAccessControl($link);
    $rows = $iac->listBlocks($opts);
    $total = $iac->countBlocks($opts['source'] ?? null);

    return adminJson($response, [
      'rows' => $rows,
      'total' => $total,
      'limit' => $opts['limit'],
      'offset' => $opts['offset'],
    ]);
  });

  /**
   * POST /admin/security/blocklist
   * Body: { cidr, reason?, source?, expires_at? }
   */
  $group->post('/blocklist', function (Request $request, Response $response) {
    global $link;
    $body = $request->getParsedBody() ?? [];
    $cidr = trim((string) ($body['cidr'] ?? ''));
    if ($cidr === '') {
      return adminError($response, 'cidr is required');
    }
    $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;
    $source = isset($body['source']) && $body['source'] !== '' ? (string) $body['source'] : 'manual';
    $expiresAt = isset($body['expires_at']) && $body['expires_at'] !== '' ? (string) $body['expires_at'] : null;

    if ($expiresAt !== null && strtotime($expiresAt) === false) {
      return adminError($response, 'Invalid expires_at format');
    }

    try {
      $iac = new IpAccessControl($link);
      $id = $iac->addBlock($cidr, $reason ?: null, $source, $expiresAt);
      if ($id === null) {
        return adminError($response, 'Duplicate range — this CIDR is already blocked', 409);
      }
      return adminJson($response, ['success' => true, 'id' => $id, 'cidr' => IpAccessControl::normalizeCidr($cidr)]);
    } catch (InvalidArgumentException $e) {
      return adminError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('Admin blocklist add error: ' . $e->getMessage());
      return adminError($response, 'Failed to add block', 500);
    }
  });

  /**
   * PATCH /admin/security/blocklist/{id}
   * Body: any of { reason, source, expires_at }
   */
  $group->patch('/blocklist/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];
    $body = $request->getParsedBody() ?? [];

    $fields = [];
    foreach (['reason', 'source', 'expires_at'] as $f) {
      if (array_key_exists($f, $body)) {
        $fields[$f] = $body[$f] === '' ? null : (string) $body[$f];
      }
    }
    if ($fields === []) {
      return adminError($response, 'No updatable fields provided');
    }
    if (isset($fields['expires_at']) && $fields['expires_at'] !== null && strtotime($fields['expires_at']) === false) {
      return adminError($response, 'Invalid expires_at format');
    }

    try {
      $iac = new IpAccessControl($link);
      $ok = $iac->updateBlock($id, $fields);
      if (!$ok) {
        return adminError($response, 'Block not found or unchanged', 404);
      }
      return adminSuccess($response, ['id' => $id]);
    } catch (InvalidArgumentException $e) {
      return adminError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('Admin blocklist update error: ' . $e->getMessage());
      return adminError($response, 'Failed to update block', 500);
    }
  });

  /**
   * DELETE /admin/security/blocklist/{id}
   */
  $group->delete('/blocklist/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];
    $iac = new IpAccessControl($link);
    $ok = $iac->removeBlock($id);
    if (!$ok) {
      return adminError($response, 'Block not found', 404);
    }
    return adminSuccess($response, ['id' => $id]);
  });

  // -------------------------------------------------------------------------
  // Whitelist CRUD
  // -------------------------------------------------------------------------

  /**
   * GET /admin/security/whitelist
   */
  $group->get('/whitelist', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $opts = [
      'limit' => isset($q['limit']) ? (int) $q['limit'] : 100,
      'offset' => isset($q['offset']) ? (int) $q['offset'] : 0,
      'active_only' => !empty($q['active_only']),
    ];
    $iac = new IpAccessControl($link);
    $rows = $iac->listWhitelist($opts);
    return adminJson($response, [
      'rows' => $rows,
      'limit' => $opts['limit'],
      'offset' => $opts['offset'],
    ]);
  });

  /**
   * POST /admin/security/whitelist
   * Body: { user_id, reason?, expires_at? }
   * (user_id is application-level — stored as-is. Caller decides npub vs numeric.)
   */
  $group->post('/whitelist', function (Request $request, Response $response) {
    global $link;
    $body = $request->getParsedBody() ?? [];
    $userId = trim((string) ($body['user_id'] ?? ''));
    if ($userId === '') {
      return adminError($response, 'user_id is required');
    }
    $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;
    $expiresAt = isset($body['expires_at']) && $body['expires_at'] !== '' ? (string) $body['expires_at'] : null;
    if ($expiresAt !== null && strtotime($expiresAt) === false) {
      return adminError($response, 'Invalid expires_at format');
    }

    try {
      $iac = new IpAccessControl($link);
      $iac->addToWhitelist($userId, $reason ?: null, $expiresAt);
      return adminSuccess($response, ['user_id' => $userId]);
    } catch (InvalidArgumentException $e) {
      return adminError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('Admin whitelist add error: ' . $e->getMessage());
      return adminError($response, 'Failed to add whitelist entry', 500);
    }
  });

  /**
   * PATCH /admin/security/whitelist/{userId}
   */
  $group->patch('/whitelist/{userId}', function (Request $request, Response $response, array $args) {
    global $link;
    $userId = (string) $args['userId'];
    $body = $request->getParsedBody() ?? [];

    $fields = [];
    foreach (['reason', 'expires_at'] as $f) {
      if (array_key_exists($f, $body)) {
        $fields[$f] = $body[$f] === '' ? null : (string) $body[$f];
      }
    }
    if ($fields === []) {
      return adminError($response, 'No updatable fields provided');
    }
    if (isset($fields['expires_at']) && $fields['expires_at'] !== null && strtotime($fields['expires_at']) === false) {
      return adminError($response, 'Invalid expires_at format');
    }

    try {
      $iac = new IpAccessControl($link);
      $ok = $iac->updateWhitelist($userId, $fields);
      if (!$ok) {
        return adminError($response, 'Whitelist entry not found or unchanged', 404);
      }
      return adminSuccess($response, ['user_id' => $userId]);
    } catch (InvalidArgumentException $e) {
      return adminError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('Admin whitelist update error: ' . $e->getMessage());
      return adminError($response, 'Failed to update whitelist entry', 500);
    }
  });

  /**
   * DELETE /admin/security/whitelist/{userId}
   */
  $group->delete('/whitelist/{userId}', function (Request $request, Response $response, array $args) {
    global $link;
    $userId = (string) $args['userId'];
    $iac = new IpAccessControl($link);
    $ok = $iac->removeFromWhitelist($userId);
    if (!$ok) {
      return adminError($response, 'Whitelist entry not found', 404);
    }
    return adminSuccess($response, ['user_id' => $userId]);
  });

  // -------------------------------------------------------------------------
  // Per-upload IP lookup (used by approve.php "Lookup & Block IP" modal).
  // Pulls the upload's R2 logs by file hash and extracts IP candidates.
  // -------------------------------------------------------------------------

  /**
   * GET /admin/security/upload-ip/{id}
   * Returns IP candidates extracted from the upload's R2 log JSON, plus the
   * upload's npub for npub-side blacklisting.
   */
  $group->get('/upload-ip/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link, $csamReportingConfig;
    $id = (int) $args['id'];

    $stmt = $link->prepare('SELECT filename, type, usernpub FROM uploads_data WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null) {
      return adminError($response, 'Upload not found', 404);
    }

    $filename = (string) ($row['filename'] ?? '');
    $type     = (string) ($row['type'] ?? '');
    $usernpub = (string) ($row['usernpub'] ?? '');

    $fileHash = pathinfo($filename, PATHINFO_FILENAME);
    if ($fileHash === '' || !preg_match('/^[a-f0-9]{64}$/i', $fileHash)) {
      return adminJson($response, [
        'uploadId' => $id,
        'filename' => $filename,
        'usernpub' => $usernpub,
        'candidates' => [],
        'note' => 'Filename is not a sha256-prefixed hash; cannot fetch R2 logs.',
      ]);
    }

    try {
      $logs = fetchJsonFromR2Bucket(
        prefix: $fileHash,
        endPoint: $csamReportingConfig['r2EndPoint'],
        accessKey: $csamReportingConfig['r2AccessKey'],
        secretKey: $csamReportingConfig['r2SecretKey'],
        bucket: $csamReportingConfig['r2LogsBucket'],
      );
    } catch (\Throwable $e) {
      error_log('Admin upload-ip R2 fetch error: ' . $e->getMessage());
      return adminError($response, 'Failed to fetch upload logs', 500);
    }

    $candidates = extractIpCandidatesFromLogs($logs ? json_encode($logs) : null);

    return adminJson($response, [
      'uploadId' => $id,
      'filename' => $filename,
      'type' => $type,
      'usernpub' => $usernpub,
      'candidates' => $candidates,
    ]);
  });

  // -------------------------------------------------------------------------
  // Legacy npub/IP blacklist (the existing `blacklist` table).
  // Stop-gap manager — schema unchanged; CRUD only.
  // -------------------------------------------------------------------------

  /**
   * GET /admin/security/legacy-blacklist
   * Query: q?, limit?, offset?
   */
  $group->get('/legacy-blacklist', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $opts = [
      'q' => isset($q['q']) ? (string) $q['q'] : '',
      'limit' => isset($q['limit']) ? (int) $q['limit'] : 100,
      'offset' => isset($q['offset']) ? (int) $q['offset'] : 0,
    ];
    $bl = new LegacyBlacklist($link);
    $rows = $bl->list($opts);
    $total = $bl->count($opts['q']);
    return adminJson($response, [
      'rows' => $rows,
      'total' => $total,
      'limit' => $opts['limit'],
      'offset' => $opts['offset'],
      'q' => $opts['q'],
    ]);
  });

  /**
   * GET /admin/security/legacy-blacklist/check?npub=&ip=
   */
  $group->get('/legacy-blacklist/check', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $npub = trim((string) ($q['npub'] ?? ''));
    $ip = trim((string) ($q['ip'] ?? ''));

    if ($npub === '' && $ip === '') {
      return adminError($response, 'Provide npub and/or ip');
    }

    $bl = new LegacyBlacklist($link);
    return adminJson($response, [
      'npub' => $npub,
      'ip' => $ip,
      'npub_banned' => $npub !== '' ? $bl->isNpubBanned($npub) : null,
      'ip_banned' => $ip !== '' ? $bl->isIpBanned($ip) : null,
    ]);
  });

  /**
   * POST /admin/security/legacy-blacklist
   * Body: { npub?, ip?, user_agent?, reason? } — at least one of npub/ip required.
   */
  $group->post('/legacy-blacklist', function (Request $request, Response $response) {
    global $link;
    $body = $request->getParsedBody() ?? [];
    $npub = isset($body['npub']) ? trim((string) $body['npub']) : '';
    $ip = isset($body['ip']) ? trim((string) $body['ip']) : '';
    $ua = isset($body['user_agent']) ? trim((string) $body['user_agent']) : '';
    $reason = isset($body['reason']) ? trim((string) $body['reason']) : '';

    try {
      $bl = new LegacyBlacklist($link);
      $id = $bl->add(
        $npub !== '' ? $npub : null,
        $ip !== '' ? $ip : null,
        $ua !== '' ? $ua : null,
        $reason !== '' ? $reason : null,
      );
      return adminJson($response, ['success' => true, 'id' => $id]);
    } catch (InvalidArgumentException $e) {
      return adminError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('Admin legacy-blacklist add error: ' . $e->getMessage());
      return adminError($response, 'Failed to add blacklist entry', 500);
    }
  });

  /**
   * DELETE /admin/security/legacy-blacklist/{id}
   */
  $group->delete('/legacy-blacklist/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];
    $bl = new LegacyBlacklist($link);
    $ok = $bl->removeById($id);
    if (!$ok) {
      return adminError($response, 'Blacklist entry not found', 404);
    }
    return adminSuccess($response, ['id' => $id]);
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

/**
 * HEAD-check a video poster URL. Returns true only on HTTP 200.
 * Missing posters redirect (302) at the origin, so anything != 200 = missing.
 * Cache-busted to avoid a stale CDN 302 masking a freshly-uploaded poster.
 */
function posterBackfillHasPoster(string $posterUrl): bool
{
  $bust = $posterUrl . '?cb=' . substr(hash('sha256', $posterUrl . uniqid('', true)), 0, 12);
  $ch = curl_init($bust);
  curl_setopt_array($ch, [
    CURLOPT_NOBODY => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_HTTPHEADER => ['Cache-Control: no-cache', 'Pragma: no-cache'],
  ]);
  curl_exec($ch);
  $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  return $code === 200;
}

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
        $purgeBatch[] = $filename;
        $s3->deleteFromS3(objectKey: $objectName, paidAccount: false);
      }

      // Purge Cloudflare cache
      try {
        $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
        $result = $purger->purgeFiles($purgeBatch);
        if ($result !== false) {
          error_log("Mass delete purge result: " . json_encode($result));
        }
      } catch (\Throwable $e) {
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

  /**
   * GET /admin/media/poster-backfill?npub=...
   * List all video files for an npub (pro/v.nostr.build). The browser batches
   * these and POSTs them back; the per-video missing-poster check happens at
   * process time. Cheap: a single indexed query, no HTTP.
   */
  $group->get('/poster-backfill', function (Request $request, Response $response) {
    global $link;

    $params = $request->getQueryParams();

    // Scan-all init: return the total video count for the progress bar.
    if (!empty($params['all'])) {
      $res = $link->query("SELECT COUNT(*) AS c FROM users_images WHERE mime_type LIKE 'video%'");
      $row = $res->fetch_assoc();
      return adminJson($response, ['total' => (int) $row['c']]);
    }

    $npub = trim($params['npub'] ?? '');
    if (!preg_match('/^npub1[a-z0-9]{20,90}$/', $npub)) {
      return adminError($response, 'Invalid npub');
    }

    $stmt = $link->prepare(
      "SELECT id, image FROM users_images
       WHERE usernpub = ? AND mime_type LIKE 'video%'
       ORDER BY created_at ASC"
    );
    $stmt->bind_param('s', $npub);
    $stmt->execute();
    $result = $stmt->get_result();

    $videos = [];
    while ($row = $result->fetch_assoc()) {
      $videos[] = ['id' => (int) $row['id'], 'image' => $row['image']];
    }
    $stmt->close();

    return adminJson($response, [
      'npub'   => $npub,
      'count'  => count($videos),
      'videos' => $videos,
    ]);
  });

  /**
   * POST /admin/media/poster-backfill   body: { npub, ids: [int, ...max 5] }
   * For each id: confirm it's a video owned by npub, HEAD-check its poster, and
   * if missing run the production VideoPosterExtractor against the public CDN
   * URL. Per-id try/catch so one bad video can't sink the batch; returns a
   * per-id result the browser uses to drive progress and retries.
   */
  $group->post('/poster-backfill', function (Request $request, Response $response) {
    global $link, $awsConfig;
    @set_time_limit(0);

    $body = $request->getParsedBody();
    $npub = trim($body['npub'] ?? '');
    $ids  = $body['ids'] ?? [];

    if (!preg_match('/^npub1[a-z0-9]{20,90}$/', $npub)) {
      return adminError($response, 'Invalid npub');
    }
    if (!is_array($ids) || count($ids) === 0) {
      return adminError($response, 'ids is required');
    }
    if (count($ids) > 10) {
      return adminError($response, 'Max 10 ids per batch');
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/VideoPosterExtractor.class.php';

    $usersImages = new UsersImages($link);
    $extractor   = new VideoPosterExtractor($awsConfig, $usersImages);

    $results = [];
    foreach ($ids as $rawId) {
      $id = (int) $rawId;
      try {
        $file = $usersImages->getFile($npub, $id);
        if (empty($file) || empty($file['image'])) {
          $results[] = ['id' => $id, 'status' => 'failed', 'message' => 'not found for npub'];
          continue;
        }
        $image = $file['image'];
        if (!preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9]+$/', $image)) {
          $results[] = ['id' => $id, 'status' => 'failed', 'message' => 'unexpected filename'];
          continue;
        }
        if (strncmp((string) ($file['mime_type'] ?? ''), 'video', 5) !== 0) {
          $results[] = ['id' => $id, 'status' => 'skipped', 'message' => 'not a video'];
          continue;
        }

        if (posterBackfillHasPoster("https://v.nostr.build/{$image}/poster.jpg")) {
          $results[] = ['id' => $id, 'status' => 'skipped', 'message' => 'has poster'];
          continue;
        }

        $ok = $extractor->extractAndUpload("https://v.nostr.build/{$image}", $image, $id, $npub);
        $results[] = $ok
          ? ['id' => $id, 'status' => 'created', 'message' => $image]
          : ['id' => $id, 'status' => 'failed', 'message' => 'extraction failed'];
      } catch (\Throwable $e) {
        $results[] = ['id' => $id, 'status' => 'failed', 'message' => substr($e->getMessage(), 0, 200)];
      }
    }

    return adminJson($response, ['results' => $results]);
  });

  /**
   * POST /admin/media/poster-backfill-all   body: { after_id, limit: max 10 }
   * Cursor-paginated scan across ALL paid-subscriber videos, oldest first
   * (id ASC keyset). The browser stores only { after_id, counters } so a
   * 50k+ run survives a crash without holding the whole list client-side.
   * Same per-id HEAD-then-extract logic as the single-npub route.
   */
  $group->post('/poster-backfill-all', function (Request $request, Response $response) {
    global $link, $awsConfig;
    @set_time_limit(0);

    $body    = $request->getParsedBody();
    $afterId = (int) ($body['after_id'] ?? 0);
    $limit   = (int) ($body['limit'] ?? 10);
    $limit   = max(1, min(10, $limit));

    $stmt = $link->prepare(
      "SELECT id, image, usernpub FROM users_images
       WHERE mime_type LIKE 'video%' AND id > ?
       ORDER BY id ASC LIMIT ?"
    );
    $stmt->bind_param('ii', $afterId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImages.class.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/VideoPosterExtractor.class.php';

    $usersImages = new UsersImages($link);
    $extractor   = new VideoPosterExtractor($awsConfig, $usersImages);

    $results = [];
    $cursor  = $afterId;
    foreach ($rows as $row) {
      $id     = (int) $row['id'];
      $cursor = $id;
      $image  = $row['image'];
      $npub   = $row['usernpub'];
      try {
        if (!preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9]+$/', $image)) {
          $results[] = ['id' => $id, 'image' => $image, 'npub' => $npub, 'status' => 'failed', 'message' => 'unexpected filename'];
          continue;
        }
        if (posterBackfillHasPoster("https://v.nostr.build/{$image}/poster.jpg")) {
          $results[] = ['id' => $id, 'image' => $image, 'npub' => $npub, 'status' => 'skipped', 'message' => 'has poster'];
          continue;
        }
        $ok = $extractor->extractAndUpload("https://v.nostr.build/{$image}", $image, $id, $npub);
        $results[] = ['id' => $id, 'image' => $image, 'npub' => $npub, 'status' => $ok ? 'created' : 'failed', 'message' => $ok ? '' : 'extraction failed'];
      } catch (\Throwable $e) {
        $results[] = ['id' => $id, 'image' => $image, 'npub' => $npub, 'status' => 'failed', 'message' => substr($e->getMessage(), 0, 200)];
      }
    }

    return adminJson($response, [
      'results' => $results,
      'cursor'  => $cursor,
      'scanned' => count($rows),
      'more'    => count($rows) === $limit,
    ]);
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
 * Delete a file from S3, ban from blossom, insert into rejected_files, delete
 * from uploads_data. Returns true on full success, false when the S3 delete
 * failed — the DB row is preserved in that case so the admin can retry
 * without orphaning the S3 object.
 *
 * Ordering rule (matches /admin/moderation/reject-batch): S3 first, DB last.
 * Once we lose the uploads_data row we lose the only handle pointing to the
 * S3 object, so the row must outlive any failure that leaves the file in S3.
 */
function deleteAndRejectUpload(mysqli $link, S3Service $s3, BlossomFrontEndAPI $blossomAPI, int $id, string $filename, string $type): bool
{
  $objectName = match ($type) {
    'picture' => 'i/' . $filename,
    'profile' => 'i/p/' . $filename,
    default   => 'av/' . $filename,
  };

  // S3Service::deleteFromS3 returns true on success (and treats NoSuchKey as
  // success), false on AWS error. It catches its own exceptions internally,
  // so the outer try/catch only fires if something pre-S3 throws.
  $s3DeleteOk = false;
  try {
    $s3DeleteOk = $s3->deleteFromS3(objectKey: $objectName, paidAccount: false) === true;
  } catch (\Throwable $e) {
    error_log("deleteAndRejectUpload S3 exception for id {$id} ({$filename}): " . $e->getMessage());
  }

  if (!$s3DeleteOk) {
    error_log("deleteAndRejectUpload: S3 delete failed for id {$id} ({$filename}); preserving DB row for retry");
    return false;
  }

  // CF cache purge — best-effort. A failure here just leaves a stale cache
  // entry that expires on its own; never a reason to keep the DB row.
  try {
    $purger = new CloudflarePurger($_SERVER['NB_API_SECRET'], $_SERVER['NB_API_PURGE_URL']);
    $purgeFilename = $filename;
    $result = $purger->purgeFiles([$purgeFilename]);
    if ($result !== false) {
      error_log(json_encode($result));
    }
  } catch (\Throwable $e) {
    error_log("PURGE error occurred: " . $e->getMessage());
  }

  // Ban from blossom if hash exists — best-effort, same reasoning as CF.
  try {
    banFromBlossomIfHashExists($link, $blossomAPI, $id);
  } catch (\Throwable $e) {
    error_log("Blossom hash ban failed for id {$id}: " . $e->getMessage());
  }

  // Insert into rejected_files (best-effort: file is already gone from S3,
  // so a missing rejected_files row only weakens the duplicate-rejection
  // check for free re-uploads. Log and continue to the DB delete.)
  try {
    $stmt = $link->prepare("INSERT INTO rejected_files (filename, type) VALUES (?, ?)");
    $stmt->bind_param("ss", $filename, $type);
    $stmt->execute();
    $stmt->close();
  } catch (\Throwable $e) {
    error_log("rejected_files insert failed for id {$id} ({$filename}): " . $e->getMessage());
  }

  // Delete from uploads_data — last write. If this fails the file is gone
  // from S3 but the DB row remains; admin retry will see the file missing
  // (S3Service treats NoSuchKey as success) and complete the cleanup.
  try {
    $stmt = $link->prepare("DELETE FROM uploads_data WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
  } catch (\Throwable $e) {
    error_log("uploads_data delete failed for id {$id}: " . $e->getMessage());
    return false;
  }

  return true;
}

/**
 * Ban user (legacy blacklist + blossom ban), then delete the file. Returns
 * true on full success, false when the deletion step fails — in that case
 * the user IS still banned (we keep the ban writes regardless), but the
 * file remains in S3 + DB and the admin should retry the reject.
 */
function banUserAndDeleteUpload(mysqli $link, S3Service $s3, BlossomFrontEndAPI $blossomAPI, array $csamReportingConfig, int $id, string $filename, string $type): bool
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
    $bl = new LegacyBlacklist($link);
    foreach ($logsJSON as $log) {
      $logData = json_decode($log['uploadedFileInfo'] ?? '', true);
      $ip = is_array($logData) ? ($logData['realIp'] ?? null) : null;
      $ua = is_array($logData) ? ($logData['userAgent'] ?? null) : null;
      $npub = $log['uploadNpub'] ?? "anonymous";
      try {
        // npub is the actual block target; ip/ua kept as forensic context only.
        $bl->add($npub, $ip, $ua, 'BANNED');
      } catch (\Throwable $e) {
        error_log('Legacy blacklist insert (BANNED) failed for ' . $npub . ': ' . $e->getMessage());
      }
      try {
        $blossomAPI->banUser($npub, 'Repeated TOS Violation or legal reasons');
      } catch (\Throwable $e) {
        error_log('Blossom banUser failed for ' . $npub . ': ' . $e->getMessage());
      }
    }
  }

  // Delete the file (reuse reject helper logic). Ban writes above already
  // succeeded — even if delete returns false, the user is banned.
  return deleteAndRejectUpload($link, $s3, $blossomAPI, $id, $filename, $type);
}

/**
 * Full CSAM workflow: archive evidence, blacklist, delete.
 * Returns true on success, or an error message string on failure.
 */
function processCsamReport($link, S3Service $s3, BlossomFrontEndAPI $blossomAPI, array $csamReportingConfig, int $id, string $filename, string $type, ?string $reportingNpub = null): string|bool
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

  // Store case info in DB. The reporting npub comes from the session for the
  // legacy admin pages; Worker-proxied callers (routes_accounts_admin.php)
  // have no PHP session and pass it explicitly via $reportingNpub.
  $stmt = $link->prepare("INSERT INTO identified_csam_cases (identified_by_npub, evidence_location_url, file_sha256_hash, logs) VALUES (?, ?, ?, ?)");
  $evidenceReportingNpub = $reportingNpub ?? ($_SESSION['usernpub'] ?? 'unknown');
  $evidenceLocationURL = "{$csamReportingConfig['r2EndPoint']}/{$csamReportingConfig['r2EvidenceBucket']}/{$file_sha256_hash}/";
  $evidenceJSONLogs = json_encode($logsJSON);
  $stmt->bind_param("ssss", $evidenceReportingNpub, $evidenceLocationURL, $file_sha256_hash, $evidenceJSONLogs);
  $stmt->execute();
  $stmt->close();

  // Blacklist all associated users
  if (!empty($logsJSON)) {
    $bl = new LegacyBlacklist($link);
    foreach ($logsJSON as $log) {
      $logData = json_decode($log['uploadedFileInfo'], true);
      $ip = $logData['realIp'] ?? null;
      $ua = $logData['userAgent'] ?? null;
      $npub = $log['uploadNpub'] ?? "anonymous";
      try {
        // npub is the block target; ip/ua kept as forensic context only.
        $bl->add($npub, $ip, $ua, 'CSAM');
      } catch (\Throwable $e) {
        error_log('Legacy blacklist insert (CSAM) failed for ' . $npub . ': ' . $e->getMessage());
      }
      try {
        $blossomAPI->banUser($npub, 'Confirmed CSAM report');
      } catch (\Throwable $e) {
        error_log('Blossom banUser failed for ' . $npub . ': ' . $e->getMessage());
      }
    }
  }

  // Delete file from S3, purge CF, ban blossom hash, reject, delete from DB.
  // CRITICAL: at this point the case row + R2 evidence are already persisted,
  // so reporting to NCMEC is still possible even if local cleanup fails.
  // Surface the partial state so the admin can retry the cleanup; the case
  // row stays intact regardless.
  if (!deleteAndRejectUpload($link, $s3, $blossomAPI, $id, $filename, $type)) {
    return 'CSAM case recorded and user banned, but file cleanup failed (S3 delete error). The evidence and ban are intact; retry the action to remove the file.';
  }

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
