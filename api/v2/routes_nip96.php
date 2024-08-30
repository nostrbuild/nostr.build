<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/NostrAuthMiddleware.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Route to upload a file using nip-96
 */

$app->group('/nip96', function (RouteCollectorProxy $group) {
  // Route to upload file(s) via form
  $group->post('/upload', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();
    // Get form data: expiration, size, alt, caption, media_type, content_type
    $body = $request->getParsedBody();
    $formParams = [
      'expiration' => isset($body['expiration']) && filter_var($body['expiration'], FILTER_VALIDATE_INT)
        ? (int)$body['expiration']
        : null,
      'size' => isset($body['size']) && filter_var($body['size'], FILTER_VALIDATE_INT)
        ? (int)$body['size']
        : null,
      'alt' => $body['alt'] ?? null,
      'caption' => $body['caption'] ?? null,
      'media_type' => $body['media_type'] ?? null,
      'content_type' => $body['content_type'] ?? null,
      'no_transform' => $body['no_transform'] ?? 'false',
    ];

    // DEBUG
    error_log('Form Params: ' . json_encode($formParams) . PHP_EOL);

    // Log request route
    //error_log('Route: /nip96/upload');

    // If no files or url provided or more than one file is submitted, return a 400 response
    if (
      (empty($files) || count($files) > 1) &&
      empty($body['url'])
    ) {
      error_log('Either no file or more than one file posted. Only one file is expected.');
      return nip96Response(
        response: $response,
        status: 'error',
        statusCode: 400,
        message: 'Either no file or more than one file posted. Only one file is expected.',
        data: new stdClass(),
      );
    }
    // NIP-98 handling
    $npub = $request->getAttribute('npub');
    $accountUploadEligible = $request->getAttribute('account_upload_eligible');
    $accountDefaultFolder = $request->getAttribute('account_default_folder');
    $factory = $this->get('multimediaUploadFactory');

    if (null !== $npub) {
      // Nip-98 authentication is required for our implementation of nip-96
      error_log('npub: ' . $npub . ' uploading files');
      $upload = $factory->create($accountUploadEligible, $npub);
      if (!empty($accountDefaultFolder)) {
        $upload->setDefaultFolderName($accountDefaultFolder);
      }
    } else {
      error_log('Upload unauthorized');
      // Reject with unauthorized error
      return nip96Response(
        response: $response,
        status: 'error',
        statusCode: 401,
        message: 'Unauthorized, please provide a valid nip-98 token',
        data: new stdClass(),
      );
    }
    //error_log(PHP_EOL . "Request URL:" . $request->getUri() . PHP_EOL);

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      if (empty($body['url'])) {
        $upload->setPsrFiles([reset($files)]);
      }
      $upload->setFormParams($formParams);
      if ($formParams['media_type'] === 'avatar') {
        if (empty($body['url'])) {
          [$status, $code, $message] = $upload->uploadProfilePicture();
        } else {
          [$status, $code, $message] = $upload->uploadFileFromUrl($body['url'], true);
        }
      } else {
        $no_transform = $formParams['no_transform'] === 'true' ? true : false;
        if (empty($body['url'])) {
          [$status, $code, $message] = $upload->uploadFiles($no_transform);
        } else {
          [$status, $code, $message] = $upload->uploadFileFromUrl($body['url'], $no_transform);
        }
      }
      if (!$status) {
        error_log('Upload failed' . json_encode(['code' => $code, 'message' => $message]));
        return nip96Response(
          response: $response,
          status: 'error',
          statusCode: $code,
          message: $message,
          data: new stdClass(),
        );
      }
      $data = $upload->getUploadedFiles();
      // Add fallback if url is set for uploaded files
      if (!empty($body['url']) && !empty($data[0]['url'])) {
        $data[0]['fallback'] = $body['url'];
      }
      //error_log('Upload successful' . json_encode(['code' => $code, 'message' => $message]));
      return nip96Response(
        response: $response,
        status: 'success',
        statusCode: $code,
        message: $message,
        data: reset($data),
      );
    } catch (\Exception $e) {
      error_log('Upload failed: ' . $e->getMessage());
      return nip96Response(
        response: $response,
        status: 'error',
        statusCode: 500,
        message: 'Upload failed: ' . $e->getMessage(),
        data: new stdClass(),
      );
    }
  })->add(new NostrAuthMiddleware())
    ->add(new FormAuthorizationMiddleware()); // NIP-96 handling of form based authorization

  // Delete file route
  $group->delete('/upload[/{params:.*}]', function (Request $request, Response $response, array $args) {
    $fileId = $args['params'];
    error_log('Route: /nip96/upload/{id} - DELETE: ' . "$fileId" . PHP_EOL);
    $npub = $request->getAttribute('npub');
    $factory = $this->get('deleteMediaFactory');

    if (null !== $npub) {
      // Nip-98 authentication is required for our implementation of nip-96
      error_log('npub: ' . $npub . ' deleting file');
      try {
        $delete = $factory->create($npub, $fileId);
      } catch (\Exception $e) {
        error_log('Delete failed: ' . $e->getMessage());
        return nip96Response(
          response: $response,
          status: 'error',
          statusCode: $e->getCode() ?: 500,
          message: 'Delete failed: ' . $e->getMessage(),
          data: new stdClass(),
        );
      }
    } else {
      error_log('Delete unauthorized');
      // Reject with unauthorized error
      return nip96Response(
        response: $response,
        status: 'error',
        statusCode: 401,
        message: 'Unauthorized, please provide a valid nip-98 token',
        data: new stdClass(),
      );
    }

    try {
      $res = $delete->deleteMedia();
      if (!$res) {
        throw new Exception('Failed to delete file');
      }
      return nip96Response(
        response: $response,
        status: 'success',
        statusCode: 200,
        message: 'File deleted.',
        data: [],
      );
    } catch (\Exception $e) {
      error_log('Delete failed: ' . $e->getMessage());
      return nip96Response(
        response: $response,
        status: 'error',
        statusCode: $e->getCode() ?: 500,
        message: 'Delete failed: ' . $e->getMessage(),
        data: new stdClass(),
      );
    }
  })->add(new NostrAuthMiddleware())
    ->add(new FormAuthorizationMiddleware()); // NIP-96 handling of form based authorization

  // Phony GET route for nip96 upload
  $group->get('/upload', function (Request $request, Response $response) {
    error_log('Route: /nip96/upload - GET');
    return nip96Response(
      response: $response,
      status: 'error',
      statusCode: 405, // 405 Method Not Allowed
      message: 'Method not allowed',
      data: new stdClass(),
    );
  });

  // Route to upload a file via URL
  $group->get('/ping[/{name}]', function (Request $request, Response $response, array $args) {
    $response->getBody()->write('GFY - ' . $args['name'] ?? 'Anon');
    return $response;
  });
});
