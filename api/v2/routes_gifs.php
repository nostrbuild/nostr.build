<?php

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/gifs', function (RouteCollectorProxy $group) {
  // Route to get gifs
  $group->get('/get', function (Request $request, Response $response) {
    // Get query parameters
    $params = $request->getQueryParams();
    // Check each parameter and set default if not provided
    $start = isset($params['cursor']) ? (int) $params['cursor'] : 0; // Max 500
    $limit = isset($params['limit']) ? (int) $params['limit'] : 10; // Max 50
    $random = isset($params['random']) ? (bool) $params['random'] : false;

    $gifBrowser = $this->get('gifBrowser');
    try {
      $apiResponse = $gifBrowser->getApiResponse($start, $limit, 'DESC', $random);
      $response->getBody()->write($apiResponse);
      // Set JSON response headers
      $response = $response->withHeader('Content-Type', 'application/json');
      return $response;
    } catch (Exception $e) {
      error_log('Error fetching gifs: ' . $e->getMessage());
      $ret = [
        'status' => 'error',
        'message' => 'Failed to fetch gifs'
      ];
      $response->getBody()->write(json_encode($ret));
      // Set JSON response headers
      $response = $response->withHeader('Content-Type', 'application/json');
      return $response;
    }
  });
});
