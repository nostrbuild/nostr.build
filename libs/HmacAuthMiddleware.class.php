<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/HmacAuthHandler.class.php';

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Server\Response as ResponseClass;
use Slim\Psr7\Response as Psr7Response;

/**
 * Summary of HmacAuthMiddleware
 */
class HmacAuthMiddleware implements MiddlewareInterface
{
  /**
   * Summary of process
   * @param Psr\Http\Message\ServerRequestInterface $request
   * @param Psr\Http\Server\RequestHandlerInterface $handler
   * @return Psr\Http\Message\ResponseInterface
   * 
   * With this middleware we want to achieve the following:
   * Verify the HMAC signature in the request headers
   */

  public function process(Request $request, RequestHandler $handler): Response
  {
    $secrets = explode(',', $_SERVER['NB_HMAC_SECRETS']);

    foreach ($secrets as $secret) {
      try {
        $hmacAuthHandler = new HmacAuthHandler($request, $secret);
        $hmacAuthHandler->authenticate();
        $response = $handler->handle($request);
        return $response;
      } catch (\Exception $e) {
        error_log('HmacAuthHandler error: ' . $e->getMessage());
      }
    }
    return new Psr7Response(401); 
  }
}
