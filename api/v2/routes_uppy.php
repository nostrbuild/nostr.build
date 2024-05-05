<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;

$app->group('/uppy', function (RouteCollectorProxy $group) {
  // Route to upload file(s) via form
  $group->post('/files', function (Request $request, Response $response) {
    $files = $request->getUploadedFiles();

    // If no files are provided, return a 400 response
    if (empty($files)) {
      return uppyResponse($response, 'error', 'No files provided', new stdClass(), 400);
    }
    $upload = $this->get('freeUpload');

    try {
      // Handle exceptions thrown by the MultimediaUpload class
      $upload->setPsrFiles($files);

      // Set headers for streaming response
      $response = $response
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Cache-Control', 'no-cache')
        ->withHeader('Transfer-Encoding', 'chunked');

      // Send the initial response to indicate the file is received and processing started
      $response->getBody()->write("\n");
      flush();

      // Create a promise for the file upload
      $uploadPromise = new Promise(function () use ($upload) {
        return $upload->uploadFiles();
      });

      // Send progress updates every 5 seconds until the upload is complete
      $uploadPromise->then(
        function ($result) use ($upload, $response) {
          [$status, $code, $message] = $result;
          if ($status) {
            $data = $upload->getUploadedFiles();
            error_log('Upload successful: ' . json_encode($data));
            return uppyResponse($response, 'success', $message, $data, $code);
          } else {
            return uppyResponse($response, 'error', $message, new stdClass(), $code);
          }
        },
        function ($exception) use ($response) {
          return uppyResponse($response, 'error', $exception->getMessage(), new stdClass(), $exception->getCode());
        }
      );

      // Send newline characters to keep the connection alive
      while ($uploadPromise->getState() === PromiseInterface::PENDING) {
        $response->getBody()->write("\n");
        flush();
        sleep(1);
      }

      return $response;
    } catch (\Exception $e) {
      return uppyResponse($response, 'error', 'Upload failed: ' . $e->getMessage(), new stdClass(), 500);
    }
  });

  $group->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
  });
});
