<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/permissions.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Create a new Permission object
$perm = new Permission();

$app->group('/account', function (RouteCollectorProxy $group) {
  // Route to retrieve files with optional folder id as an argument
  $group->get('/files[/{folder_id}]', function (Request $request, Response $response, array $args) {
    $folderId = $args['folder_id'] ?? null;
    $userImages = $this->get('userImages');
    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImages->getFiles($_SESSION['usernpub'], $folderId);
      return jsonResponse($response, 'success', 'Files retrieved successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Files retrieval failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to upload file(s) via form
  $group->post('/files', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();

    // If no files are provided, return a 400 response
    if (empty($files)) {
      return jsonResponse($response, 'error', 'No files provided', new stdClass(), 400);
    }
    $upload = $this->get('proUpload');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $upload->setPsrFiles($files);
      $data = ($upload->uploadFiles()) ? $upload->getUploadedFiles() : new stdClass();
      return jsonResponse($response, 'success', 'Files uploaded successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Upload failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  $group->post('/files/uppy', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();

    // Get file(s) metadata
    $metadata = $request->getParsedBody();
    if (is_string($metadata)) {
      $metadata = json_decode($metadata, true);
    }

    // If no files are provided, return a 400 response
    if (empty($files)) {
      return uppyResponse($response, 'error', 'No files provided', new stdClass(), 400);
    }

    $upload = $this->get('proUpload');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $upload->setPsrFiles($files, $metadata);
      $data = ($upload->uploadFiles()) ? $upload->getUploadedFiles() : new stdClass();
      return uppyResponse($response, 'success', 'Files uploaded successfully', $data);
    } catch (\Exception $e) {
      return uppyResponse($response, 'error', 'Upload failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to retrieve folders
  $group->get('/folders', function (Request $request, Response $response) {
    $userImagesFolders = $this->get('userImagesFolders');
    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImagesFolders->getFolders($_SESSION['usernpub']);
      return jsonResponse($response, 'success', 'Folders retrieved successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Folders retrieval failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to create a folder
  $group->post('/folders', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // If no folder name is provided, return a 400 response
    if (empty($data['folder_name'])) {
      return jsonResponse($response, 'error', 'No folder name provided', new stdClass(), 400);
    }
    $userImagesFolders = $this->get('userImagesFolders');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImagesFolders->insert([
        'usernpub' => $_SESSION['usernpub'],
        'folder' => $data['folder_name'],
      ]);
      return jsonResponse($response, 'success', 'Folder created successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Folder creation failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to delete a folder
  $group->delete('/folders/{folder_id}', function (Request $request, Response $response, array $args) {
    $folderId = $args['folder_id'] ?? null;

    // If no folder id is provided, return a 400 response
    if (empty($folderId)) {
      return jsonResponse($response, 'error', 'No folder id provided', new stdClass(), 400);
    }
    $userImagesFolders = $this->get('userImagesFolders');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImagesFolders->delete([
        'id' => $folderId,
        'usernpub' => $_SESSION['usernpub'],
      ]);
      return jsonResponse($response, 'success', 'Folder deleted successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'Folder deletion failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to delete a file
  $group->delete('/files/{file_id}', function (Request $request, Response $response, array $args) {
    $fileId = $args['file_id'] ?? null;

    // If no file id is provided, return a 400 response
    if (empty($fileId)) {
      return jsonResponse($response, 'error', 'No file id provided', new stdClass(), 400);
    }
    $userImages = $this->get('userImages');

    // TODO: Implement deletion of files from S3 and DB in one transaction 
    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $data = $userImages->delete([
        'id' => $fileId,
        'usernpub' => $_SESSION['usernpub'],
      ]);
      return jsonResponse($response, 'success', 'File deleted successfully', $data);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'File deletion failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  // Route to upload a file via URL
  $group->post('/url', function (Request $request, Response $response) {
    $data = $request->getParsedBody();

    // If no URL is provided, return a 400 response
    if (empty($data['url'])) {
      return jsonResponse($response, 'error', 'No URL provided', new stdClass(), 400);
    }
    $upload = $this->get('proUpload');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $result = ($upload->uploadFileFromUrl($data['url'])) ? $upload->getUploadedFiles() : new stdClass();
      return jsonResponse($response, 'success', 'URL processed successfully', $result);
    } catch (\Exception $e) {
      return jsonResponse($response, 'error', 'URL processing failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  $group->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
  })->add(function ($request, $handler) {
    // Additional middleware logic for this route
    // Continue to the route if everything checks out
    return $handler->handle($request);
  });
})->add(function ($request, $handler) use ($perm) {
  // Check if the user is logged in
  // This would be the place to allow other authentication methods like JWT, NIP-98, etc.
  if (!$perm->validateLoggedin() || $perm->validatePermissionsLevelEqual(4)) {
    error_log('User not authenticated or authorized');
    $response = new Slim\Psr7\Response(); // Create a new response object
    return jsonResponse($response, 'error', 'User not authenticated or authorized', new stdClass(), 401);
  }
  error_log('User authenticated and authorized: ' . $_SESSION['usernpub'] . PHP_EOL);
  // If the user is logged in and has the necessary permissions, continue to the route
  return $handler->handle($request);
});
