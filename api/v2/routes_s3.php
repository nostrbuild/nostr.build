<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrAuthMiddleware.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Create a new Permission object
$perm = new Permission();

$app->group('/s3', function (RouteCollectorProxy $group) {
  
  // Route to create multipart upload
  $group->post('/multipart', function (Request $request, Response $response) {
    $startTime = microtime(true);
    $data = $request->getParsedBody();
    
    // Validate required fields
    if (empty($data['filename']) || empty($data['type'])) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: POST /multipart - VALIDATION_ERROR - {$duration}ms");
      return jsonResponse($response, 'error', 'Missing required fields: filename, type', new stdClass(), 400);
    }
    
    try {
      $s3Multipart = $this->get('s3Multipart');
      $result = $s3Multipart->createMultipartUpload(
        $data['filename'],
        $data['type'],
        $data['metadata'] ?? [],
        $_SESSION['usernpub']
      );
      
      if (!$result) {
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        error_log("S3 API: POST /multipart - FAILED - {$duration}ms");
        return jsonResponse($response, 'error', 'Failed to create multipart upload', new stdClass(), 500);
      }
      
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: POST /multipart - SUCCESS - {$duration}ms - File: " . substr($data['filename'], 0, 10));
      return jsonResponse($response, 'success', 'Multipart upload created', $result);
      
    } catch (Exception $e) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: POST /multipart - EXCEPTION - {$duration}ms - Error: " . $e->getMessage());
      error_log('S3 multipart create error: ' . $e->getMessage());
      return jsonResponse($response, 'error', 'Failed to create multipart upload', new stdClass(), 500);
    }
  });

  // Route to get signed URL for individual parts (GET)
  $group->get('/multipart/{uploadId}/{partNumber}', function (Request $request, Response $response, array $args) {
    $startTime = microtime(true);
    $uploadId = $args['uploadId'];
    $partNumber = (int)$args['partNumber'];
    $queryParams = $request->getQueryParams();
    $key = $queryParams['key'] ?? '';
    
    $uploadIdShort = substr($uploadId, 0, 10);
    
    // Validate required fields
    if (empty($uploadId) || empty($key) || $partNumber < 1) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: GET /multipart/{$uploadIdShort}/{$partNumber} - VALIDATION_ERROR - {$duration}ms");
      return jsonResponse($response, 'error', 'Missing required parameters: uploadId, key, partNumber', new stdClass(), 400);
    }
    
    try {
      $s3Multipart = $this->get('s3Multipart');
      $result = $s3Multipart->signPart(
        $uploadId,
        $key,
        $partNumber,
        $_SESSION['usernpub']
      );
      
      if (!$result) {
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        error_log("S3 API: GET /multipart/{$uploadIdShort}/{$partNumber} - FAILED - {$duration}ms");
        return jsonResponse($response, 'error', 'Failed to sign part', new stdClass(), 500);
      }
      
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: GET /multipart/{$uploadIdShort}/{$partNumber} - SUCCESS - {$duration}ms");
      return jsonResponse($response, 'success', 'Part signed', $result);
      
    } catch (Exception $e) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      $errorShort = substr($e->getMessage(), 0, 10);
      error_log("S3 API: GET /multipart/{$uploadIdShort}/{$partNumber} - EXCEPTION - {$duration}ms - Error: {$errorShort}");
      error_log('S3 multipart sign error: ' . $e->getMessage());
      return jsonResponse($response, 'error', 'Failed to sign part', new stdClass(), 500);
    }
  });

  // Route to check upload status (for completed uploads) (GET)
  $group->get('/multipart/{uploadId}/status', function (Request $request, Response $response, array $args) {
    $startTime = microtime(true);
    $uploadId = $args['uploadId'];
    $queryParams = $request->getQueryParams();
    $key = $queryParams['key'] ?? '';
    
    $uploadIdShort = substr($uploadId, 0, 10);
    
    // Validate required fields
    if (empty($uploadId) || empty($key)) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: GET /multipart/{$uploadIdShort}/status - VALIDATION_ERROR - {$duration}ms");
      return jsonResponse($response, 'error', 'Missing required parameters: uploadId, key', new stdClass(), 400);
    }
    
    try {
      $s3Multipart = $this->get('s3Multipart');
      
      // Check comprehensive completion status (database + S3)
      $completionStatus = $s3Multipart->checkForCompletedUpload($key, $_SESSION['usernpub']);
      
      if ($completionStatus) {
        if ($completionStatus['status'] === 'fully_completed') {
          // File is fully completed in database
          $endTime = microtime(true);
          $duration = round(($endTime - $startTime) * 1000, 2);
          error_log("S3 API: GET /multipart/{$uploadIdShort}/status - FULLY_COMPLETED - {$duration}ms");
          return jsonResponse($response, 'success', 'Upload fully completed', [
            'completed' => true,
            'fileData' => $completionStatus
          ]);
        } elseif ($completionStatus['status'] === 's3_completed_needs_processing') {
          // S3 object exists but needs processing - instruct client to call completion
          $endTime = microtime(true);
          $duration = round(($endTime - $startTime) * 1000, 2);
          error_log("S3 API: GET /multipart/{$uploadIdShort}/status - CALL_COMPLETION - {$duration}ms");
          return jsonResponse($response, 'success', 'Upload exists in S3, call completion', [
            'call_completion' => true,
            'key' => $completionStatus['key'],
            'uploadInfo' => $completionStatus['uploadInfo']
          ]);
        }
      } else {
        // Not completed at all
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        error_log("S3 API: GET /multipart/{$uploadIdShort}/status - NOT_COMPLETED - {$duration}ms");
        return jsonResponse($response, 'success', 'Upload not completed', [
          'completed' => false
        ]);
      }
      
    } catch (Exception $e) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      $errorShort = substr($e->getMessage(), 0, 50);
      error_log("S3 API: GET /multipart/{$uploadIdShort}/status - EXCEPTION - {$duration}ms - Error: {$errorShort}");
      error_log('S3 multipart status check error: ' . $e->getMessage());
      return jsonResponse($response, 'error', 'Failed to check upload status', new stdClass(), 500);
    }
  });

  // Route to list uploaded parts (GET)
  $group->get('/multipart/{uploadId}', function (Request $request, Response $response, array $args) {
    $startTime = microtime(true);
    $uploadId = $args['uploadId'];
    $queryParams = $request->getQueryParams();
    $key = $queryParams['key'] ?? '';
    
    $uploadIdShort = substr($uploadId, 0, 10);
    
    // Validate required fields
    if (empty($uploadId) || empty($key)) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: GET /multipart/{$uploadIdShort} - VALIDATION_ERROR - {$duration}ms");
      return jsonResponse($response, 'error', 'Missing required parameters: uploadId, key', new stdClass(), 400);
    }
    
    try {
      $s3Multipart = $this->get('s3Multipart');
      $result = $s3Multipart->listParts(
        $uploadId,
        $key,
        $_SESSION['usernpub']
      );
      
      if ($result === false) {
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        error_log("S3 API: GET /multipart/{$uploadIdShort} - FAILED - {$duration}ms");
        return jsonResponse($response, 'error', 'Failed to list parts', new stdClass(), 500);
      }
      
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: GET /multipart/{$uploadIdShort} - SUCCESS - {$duration}ms");
      return jsonResponse($response, 'success', 'Parts listed', $result);
      
    } catch (Exception $e) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      $errorShort = substr($e->getMessage(), 0, 10);
      error_log("S3 API: GET /multipart/{$uploadIdShort} - EXCEPTION - {$duration}ms - Error: {$errorShort}");
      error_log('S3 multipart list error: ' . $e->getMessage());
      return jsonResponse($response, 'error', 'Failed to list parts', new stdClass(), 500);
    }
  });

  // Route to complete multipart upload (POST)
  $group->post('/multipart/{uploadId}/complete', function (Request $request, Response $response, array $args) {
    $startTime = microtime(true);
    $uploadId = $args['uploadId'];
    $queryParams = $request->getQueryParams();
    $key = $queryParams['key'] ?? '';
    $data = $request->getParsedBody();
    
    $uploadIdShort = substr($uploadId, 0, 10);
    $keyShort = substr($key, 0, 10);
    
    // Validate required fields
    if (empty($uploadId) || empty($key) || empty($data['parts'])) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: POST /multipart/{$uploadIdShort}/complete - VALIDATION_ERROR - {$duration}ms");
      return jsonResponse($response, 'error', 'Missing required parameters: uploadId, key, parts', new stdClass(), 400);
    }
    
    try {
      $s3Multipart = $this->get('s3Multipart');
      $result = $s3Multipart->completeMultipartUpload(
        $uploadId,
        $key,
        $data['parts'],
        $_SESSION['usernpub']
      );
      
      if (!$result) {
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        error_log("S3 API: POST /multipart/{$uploadIdShort}/complete - FAILED - {$duration}ms");
        return jsonResponse($response, 'error', 'Failed to complete multipart upload', new stdClass(), 500);
      }
      
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: POST /multipart/{$uploadIdShort}/complete - SUCCESS - {$duration}ms - Key: {$keyShort}");
      return jsonResponse($response, 'success', 'Multipart upload completed', $result);
      
    } catch (Exception $e) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      $errorShort = substr($e->getMessage(), 0, 10);
      error_log("S3 API: POST /multipart/{$uploadIdShort}/complete - EXCEPTION - {$duration}ms - Error: {$errorShort}");
      error_log('S3 multipart complete error: ' . $e->getMessage());
      return jsonResponse($response, 'error', 'Failed to complete multipart upload', new stdClass(), 500);
    }
  });

  // Route to abort multipart upload (DELETE)
  $group->delete('/multipart/{uploadId}', function (Request $request, Response $response, array $args) {
    $startTime = microtime(true);
    $uploadId = $args['uploadId'];
    $queryParams = $request->getQueryParams();
    $key = $queryParams['key'] ?? '';
    
    $uploadIdShort = substr($uploadId, 0, 10);
    $keyShort = substr($key, 0, 10);
    
    // Validate required fields
    if (empty($uploadId) || empty($key)) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: DELETE /multipart/{$uploadIdShort} - VALIDATION_ERROR - {$duration}ms");
      return jsonResponse($response, 'error', 'Missing required parameters: uploadId, key', new stdClass(), 400);
    }
    
    try {
      $s3Multipart = $this->get('s3Multipart');
      $result = $s3Multipart->abortMultipartUpload(
        $uploadId,
        $key,
        $_SESSION['usernpub']
      );
      
      if (!$result) {
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        error_log("S3 API: DELETE /multipart/{$uploadIdShort} - FAILED - {$duration}ms");
        return jsonResponse($response, 'error', 'Failed to abort multipart upload', new stdClass(), 500);
      }
      
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      error_log("S3 API: DELETE /multipart/{$uploadIdShort} - SUCCESS - {$duration}ms - Key: {$keyShort}");
      return jsonResponse($response, 'success', 'Multipart upload aborted', new stdClass());
      
    } catch (Exception $e) {
      $endTime = microtime(true);
      $duration = round(($endTime - $startTime) * 1000, 2);
      $errorShort = substr($e->getMessage(), 0, 10);
      error_log("S3 API: DELETE /multipart/{$uploadIdShort} - EXCEPTION - {$duration}ms - Error: {$errorShort}");
      error_log('S3 multipart abort error: ' . $e->getMessage());
      return jsonResponse($response, 'error', 'Failed to abort multipart upload', new stdClass(), 500);
    }
  });

})->add(function ($request, $handler) use ($perm) {
  // Authentication middleware - same as account routes
  if (!$perm->validateLoggedin() || !$perm->validatePermissionsLevelMoreThanOrEqual(10)) {
    error_log('User not authenticated or authorized');
    $response = new Slim\Psr7\Response();
    return jsonResponse($response, 'error', 'User not authenticated or authorized', new stdClass(), 401);
  }
  
  // Check if the user account has expired
  $account = $this->get('accountClass')($_SESSION['usernpub']);
  if ($account->isExpired()) {
    error_log('User account expired: ' . $_SESSION['usernpub']);
    $response = new Slim\Psr7\Response();
    return jsonResponse($response, 'error', 'User account expired', new stdClass(), 401);
  }
  
  error_log('User authenticated and authorized for S3: ' . $_SESSION['usernpub'] . PHP_EOL);
  return $handler->handle($request);
});