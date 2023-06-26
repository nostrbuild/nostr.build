<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/MultiFileUpload.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;

/**
 * Route to upload a file via from or URL
 * 
 * Returns JSON data with the following structure:
 *   {
 *    'status' => <'success' or 'error'>,
 *    'message' => <message about the status of the request>,
 *    'data' => { /* data about the file, or empty object in case of error * /
 *      'fileName' => <name of the file with extention>,
 *      'url' => <url of the file>,
 *      'thumbnail' => <url of the thumbnail of the file>,
 *      'blurhash' => <blurhash of the file>,
 *      'sha256' => <sha256 of the file>,
 *      'type' => <'image', 'video', 'audio', 'profile' or 'other'>,
 *      'mime' => <mime type of the file>,
 *      'size' => <size of the file in bytes>,
 *      'metadata' => <metadata of the file>,
 *      'dimentions' => {
 *       'width' => <width of the file in pixels>,
 *       'height' => <height of the file in pixels>,
 *      }
 *    }
 *  }
 */

// Route to handle free upload of standard image or video
$app->post('/upload', function (Request $request, Response $response, $args) {
  global $link;
  global $awsConfig;
  $contentType = $request->getHeaderLine('Content-Type');

  // Instantiate S3Service class
  $s3 = new S3Service($awsConfig);
  // Instantiate MultimediaUpload class
  $upload = new MultimediaUpload($link, $s3);

  if (strstr($contentType, 'application/json')) {
    $payload = $request->getParsedBody();

    // Download the file from the URL and get the path and size
    $fileInfo = downloadFile($payload['url']);

    if (!$fileInfo) {
      throw new HttpBadRequestException($request, 'Failed to download file');
    }
  } elseif (strstr($contentType, 'multipart/form-data')) {
    $uploadedFiles = $request->getUploadedFiles();

    // Validate the file and get the path and size
    $fileInfo = handleUploadedFile($uploadedFiles['file']);

    if (!$fileInfo) {
      throw new HttpBadRequestException($request, 'Failed to upload file');
    }
  } else {
    throw new HttpBadRequestException($request, 'Unsupported content type');
  }

  $payload = [
    'status' => 'success',
    'message' => 'File processed successfully',
    'data' => [
      'fileName' => $fileInfo['fileName'],
      'url' => $fileInfo['url'],
      'blurhash' => $fileInfo['blurhash'],
      'sha256' => $fileInfo['sha256'],
      'type' => $fileInfo['type'],
      'storage' => [
        'dimentions' => $fileInfo['dimentions'],
        'size' => $fileInfo['size']
      ]
    ]
  ];

  $response->getBody()->write(json_encode($payload));
  return $response->withHeader('Content-Type', 'application/json');
});

// Route to handle upload of pictures, videos, and music from URL
$app->post('/upload/url', function (Request $request, Response $response, $args) {
  $contentType = $request->getHeaderLine('Content-Type');

  if (strstr($contentType, 'application/json')) {
    $payload = $request->getParsedBody();

    // Download the file from the URL and get the path and size
    $fileInfo = downloadFile($payload['url']);

    if (!$fileInfo) {
      throw new HttpBadRequestException($request, 'Failed to download file');
    }
  } elseif (strstr($contentType, 'multipart/form-data')) {
    $uploadedFiles = $request->getUploadedFiles();

    // Validate the file and get the path and size
    $fileInfo = handleUploadedFile($uploadedFiles['file']);

    if (!$fileInfo) {
      throw new HttpBadRequestException($request, 'Failed to upload file');
    }
  } else {
    throw new HttpBadRequestException($request, 'Unsupported content type');
  }

  $payload = [
    'status' => 'success',
    'message' => 'File processed successfully',
    'data' => [
      'fileName' => $fileInfo['fileName'],
      'url' => $fileInfo['url'],
      'storage' => [
        'directory' => $fileInfo['directory'],
        'size' => $fileInfo['size']
      ]
    ]
  ];

  $response->getBody()->write(json_encode($payload));
  return $response->withHeader('Content-Type', 'application/json');
});

// Route to handle upload of profile pictures for users
$app->post('/upload/profile', function (Request $request, Response $response, $args) {
  $contentType = $request->getHeaderLine('Content-Type');

  if (strstr($contentType, 'application/json')) {
    $payload = $request->getParsedBody();

    // Download the file from the URL and get the path and size
    $fileInfo = downloadFile($payload['url']);

    if (!$fileInfo) {
      throw new HttpBadRequestException($request, 'Failed to download file');
    }
  } elseif (strstr($contentType, 'multipart/form-data')) {
    $uploadedFiles = $request->getUploadedFiles();

    // Validate the file and get the path and size
    $fileInfo = handleUploadedFile($uploadedFiles['file']);

    if (!$fileInfo) {
      throw new HttpBadRequestException($request, 'Failed to upload file');
    }
  } else {
    throw new HttpBadRequestException($request, 'Unsupported content type');
  }

  $payload = [
    'status' => 'success',
    'message' => 'File processed successfully',
    'data' => [
      'fileName' => $fileInfo['fileName'],
      'url' => $fileInfo['url'],
      'storage' => [
        'directory' => $fileInfo['directory'],
        'size' => $fileInfo['size']
      ]
    ]
  ];

  $response->getBody()->write(json_encode($payload));
  return $response->withHeader('Content-Type', 'application/json');
});
