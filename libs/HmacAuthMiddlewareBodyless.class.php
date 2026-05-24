<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/HmacAuthHandlerBodyless.class.php';

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as Psr7Response;

/**
 * Summary of HmacAuthMiddleware
 */
class HmacAuthMiddlewareBodyless implements MiddlewareInterface
{
  private $secrets;

  public function __construct(?string $secrets = null)
  {
    $this->secrets = $secrets ?? $_SERVER['NB_HMAC_SECRETS'];
  }
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
    $secrets = explode(',', $this->secrets);

    // See HmacAuthMiddleware::process — the downstream handler MUST run outside
    // this try/catch so its exceptions don't get rebranded as HMAC failures.
    $authenticated = false;
    foreach ($secrets as $secret) {
      try {
        $hmacAuthHandler = new HmacAuthHandlerBodyless($request, $secret);
        $hmacAuthHandler->authenticate();
        $authenticated = true;
        break;
      } catch (\Exception $e) {
        error_log('HmacAuthHandler error: ' . $e->getMessage());
      }
    }

    if (!$authenticated) {
      return new Psr7Response(401);
    }

    return $handler->handle($request);
  }
}
