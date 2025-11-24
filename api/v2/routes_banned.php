<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/AuthorizationBearer.class.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/banned', function (RouteCollectorProxy $group) {
  // Get banned media
  $group->get('/media', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    // Check each parameter and set default if not provided
    $start = isset($params['cursor']) ? (int) $params['cursor'] : 0;
    $limit = isset($params['limit']) ? (int) $params['limit'] : 100;
    // Reject invalid cursor or limit
    if ($start < 0 || $limit <= 0 || $limit > 1000) {
      $response->getBody()->write(json_encode(['error' => 'Invalid cursor or limit']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
      // Instantiate BTCPayWebhook
      $rejectedFilesTable = $this->get('rejectedFilesTable');
      $bannedMedia = $rejectedFilesTable->getList($start, $limit);
      $response->getBody()->write(json_encode($bannedMedia));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
      // Retrun empty array on error
      $response->getBody()->write(json_encode([]));
      return $response->withHeader('Content-Type', 'application/json');
    }
  });

  // Get banned blacklisted users
  $group->get('/users', function (Request $request, Response $response) {
    $params = $request->getQueryParams();
    // Check each parameter and set default if not provided
    $start = isset($params['cursor']) ? (int) $params['cursor'] : 0;
    $limit = isset($params['limit']) ? (int) $params['limit'] : 100;
    // Reject invalid cursor or limit
    if ($start < 0 || $limit <= 0 || $limit > 1000) {
      $response->getBody()->write(json_encode(['error' => 'Invalid cursor or limit']));
      return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
      // Instantiate BTCPayWebhook
      $blacklistTable = $this->get('blacklistTable');
      $bannedUsers = $blacklistTable->getList($start, $limit);
      $response->getBody()->write(json_encode($bannedUsers));
      return $response->withHeader('Content-Type', 'application/json');
    } catch (\Exception $e) {
      // Retrun empty array on error
      $response->getBody()->write(json_encode([]));
      return $response->withHeader('Content-Type', 'application/json');
    }
  });

  $group->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
  });
})->add(new AuthorizationBearer($_SERVER['NB_BANNED_ROUTES_API_TOKENS']));
