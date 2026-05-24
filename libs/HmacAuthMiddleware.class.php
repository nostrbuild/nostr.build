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

    // Step 1 — verify the HMAC signature. Only HMAC verification belongs inside
    // this try/catch; the downstream handler runs OUTSIDE so its exceptions
    // propagate to Slim's error middleware untouched. Previously the
    // `$handler->handle($request)` call sat inside the try, which silently
    // converted any downstream throw (e.g. the upstream /sd/credits call
    // failing for level-0 or expired users) into a misleading 401 here.
    $authenticated = false;
    foreach ($secrets as $secret) {
      try {
        $hmacAuthHandler = new HmacAuthHandler($request, $secret);
        $hmacAuthHandler->authenticate();
        $authenticated = true;
        break;
      } catch (\Exception $e) {
        error_log('HmacAuthHandler error: ' . $e->getMessage());
        // Try the next secret on HMAC failure only.
      }
    }

    if (!$authenticated) {
      return new Psr7Response(401);
    }

    // Step 2 — run the downstream handler outside the HMAC try/catch so its
    // exceptions surface as the right status (typically 500 via Slim) instead
    // of being conflated with HMAC failure.
    return $handler->handle($request);
  }
}
