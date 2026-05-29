<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/HmacAuthMiddlewareBodyless.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/WorkerEventsClient.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Route to upload a file using Blossom.
 * Only accessible privately via HMAC authentication.
 */

$app->group('/blossom', function (RouteCollectorProxy $group) {
  // Route to upload file
  $group->put('/upload', function (Request $request, Response $response) {
    $headers = $request->getHeaders(); // Authenticated path, we trust headers
    // Get body content-length and content-type
    $body = $request->getBody(); // This is not required in case of mirror upload

    $metadata = metadataFromHeaders($headers);

    // The Cloudflare Worker proxy (blossom-band) forwards the real end-user
    // client info (incl. cf-connecting-ip as realIp) via x-blossom-client-info.
    // nginx sets $_SERVER['CLIENT_REQUEST_INFO'] from the immediate peer, which
    // here is the Worker's egress IP (a Cloudflare /16). Replace it with the
    // worker-supplied info so any downstream consumer (CSAM logging, webhooks,
    // S3Multipart, etc.) records the real client IP.
    if (!empty($metadata['client_info'])) {
      $_SERVER['CLIENT_REQUEST_INFO'] = $metadata['client_info'];
    }

    error_log('Route: /blossom/upload - PUT: ' . json_encode($metadata) . PHP_EOL);

    $no_transform = $metadata['endpoint'] === 'upload' ||
      $metadata['endpoint'] === 'mirror' ? true : false;
    $contentLength = $metadata['content_length'] ?? 0;
    $sha256 = $metadata['sha256'] ?? '';

    // NIP-98 handling
    $npub = $metadata['npub'] ?? null;
    $account = $this->get('accountClass')($npub);
    // isUploadEligible() rolls in valid + not-expired + not-on-blacklist.
    // A banned npub falls back to the free path (and gets caught a second
    // time by UploadValidator's npub gate); a banned-but-paid user no
    // longer sneaks through with $pro=true.
    $accountUploadEligible = $account->isUploadEligible()
      && $account->hasSufficientStorageSpace($contentLength);
    $accountDefaultFolder = $account->getDefaultFolder() ?? null;
    $factory = $this->get('multimediaUploadFactory');

    error_log('npub: ' . $npub . ' uploading files');
    $upload = $factory->create($accountUploadEligible, $npub);
    if (!empty($accountDefaultFolder)) {
      $upload->setDefaultFolderName($accountDefaultFolder);
    }

    try {
      if ($contentLength > 0 && ($metadata['endpoint'] === 'upload' || $metadata['endpoint'] === 'media')) {
        $upload->setPutFile($body, $metadata);
        [$status, $code, $message] = $upload->uploadFiles(no_transform: $no_transform, blossom: true, sha256: $sha256, clientInfo: $metadata['client_info']);
      } else {
        [$status, $code, $message] = $upload->uploadFileFromUrl(url: $metadata['mirror_url'] ?? '', no_transform: $no_transform, blossom: true, sha256: $sha256, clientInfo: $metadata['client_info']);
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
      // Signal upstream is uploafEligible
      $data[0]['upload_eligible'] = $accountUploadEligible;
      // Add fallback if url is set for uploaded files
      if ($metadata['endpoint'] === 'mirror') {
        $data[0]['fallback'] = $metadata['mirror_url'];
      }
      //error_log('Upload successful' . json_encode(['code' => $code, 'message' => $message]));

      // Cross-device sync: notify the Worker so every other tab/device for
      // this user updates its grid + storage indicator without waiting for
      // the staleTime refetch. Swallowed failures — see WorkerEventsClient.
      try {
        $uuid = $account->getAccountUuid();
        if ($uuid !== null) {
          $events = new WorkerEventsClient();
          $folders = !empty($accountDefaultFolder) ? [$accountDefaultFolder] : null;
          $events->emitFilesChanged($uuid, $folders, added: 1);
          $events->emitProfileChanged($uuid);
        }
      } catch (\Throwable $e) {
        error_log('blossom upload: WorkerEventsClient failed: ' . $e->getMessage());
      }

      return nip96Response(
        response: $response,
        status: 'success',
        statusCode: $code,
        message: $message,
        data: reset($data),
      );
    } catch (\Throwable $e) {
      error_log('Upload failed: ' . $e->getMessage());
      return nip96Response(
        response: $response,
        status: 'error',
        statusCode: 500,
        message: 'Upload failed: ' . $e->getMessage(),
        data: new stdClass(),
      );
    }
  });

  // Delete file route
  $group->delete('/delete[/{params:.*}]', function (Request $request, Response $response, array $args) {
    $fileId = $args['params'];
    error_log('Route: /blossom/delete/{id} - DELETE: ' . "$fileId" . PHP_EOL);
    $headers = $request->getHeaders(); // Authenticated path, we trust headers
    $metadata = metadataFromHeaders($headers);

    // Same override as /upload — see comment there for rationale.
    if (!empty($metadata['client_info'])) {
      $_SERVER['CLIENT_REQUEST_INFO'] = $metadata['client_info'];
    }

    $factory = $this->get('deleteMediaFactory');

    $npub = $metadata['npub'] ?? null;
    error_log('metadata: ' . json_encode($metadata));

    if (null !== $npub) {
      // Nip-98 authentication is required for our implementation of nip-96
      error_log('npub: ' . $npub . ' deleting file');
      try {
        $delete = $factory->create($npub, $fileId);
      } catch (\Throwable $e) {
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
        message: 'Unauthorized',
        data: new stdClass(),
      );
    }

    try {
      $res = $delete->deleteBlossomMediaAPIMethod();
      if (!$res) {
        error_log('Failed to delete file');
        //throw new Exception('Failed to delete file');
      }
      return nip96Response(
        response: $response,
        status: 'success',
        statusCode: 200,
        message: 'File deleted.',
        data: [],
      );
    } catch (\Throwable $e) {
      error_log('Delete failed (delete): ' . $e->getMessage());
      return nip96Response(
        response: $response,
        status: 'error',
        statusCode: $e->getCode() ?: 500,
        message: 'Delete failed: ' . $e->getMessage(),
        data: new stdClass(),
      );
    }
  });

  // Phony GET route for nip96 upload
  $group->map(['HEAD'], '/upload', function (Request $request, Response $response) {
    error_log('Route: /blossom/upload - HEAD');
    return nip96Response(
      response: $response,
      status: 'error',
      statusCode: 405, // 405 Method Not Allowed
      message: 'Method not allowed',
      data: new stdClass(),
    );
  });

  // Account Info route
  $group->get('/account[/{params:.*}]', function (Request $request, Response $response, array $args) {
    // Bech32 encoded npub
    $npub = $args['params'];
    $account = $this->get('accountClass')($npub);
    $accountInfo = $account->getAccountInfo();
    if (empty($accountInfo)) {
      $statusCode = 404;
      $status = 'error';
      $message = 'Account not found';
      $data = new stdClass();
    } else {
      $statusCode = 200;
      $status = 'success';
      $message = 'Account info retrieved successfully';
      $data = $accountInfo;
    }
    return jsonResponse($response, $status, $message, $data, $statusCode);
  });
})->add(new HmacAuthMiddlewareBodyless($_SERVER['BLOSSOM_HMAC_SECRETS']));
