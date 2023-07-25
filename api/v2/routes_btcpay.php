<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/btcpay', function (RouteCollectorProxy $group) {
  // Route to upload file(s) via form
  $group->post('/webhook', function (Request $request, Response $response) {
    // Get Raw Body
    $body = $request->getBody();
    // Get Signature
    $signature = $request->getHeaderLine('btcpay-sig');

    try {
      // Instantiate BTCPayWebhook
      $webhook = $this->get('btcpayWebhook');

      // Handle exceptions thrown by the MultimediaUpload class
      return btcpayWebhookResponse($response, 'success', 'Webhook processed successfully');
    } catch (\Exception $e) {
      return btcpayWebhookResponse($response, 'error', $e->getMessage());
    }
  });

  $group->get('/ping', function (Request $request, Response $response) {
    $response->getBody()->write('pong');
    return $response;
  });
});
