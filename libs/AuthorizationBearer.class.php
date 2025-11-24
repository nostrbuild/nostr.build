<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/HmacAuthHandlerBodyless.class.php';

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as Psr7Response;

/**
 * Summary of AuthorizationBearer
 */
class AuthorizationBearer implements MiddlewareInterface
{
  private $secrets;

  public function __construct(?string $secrets = null)
  {
    $this->secrets = $secrets;
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
    // Return 401 if no secrets are defined
    if (empty($secrets)) {
      return new Psr7Response(401);
    }

    foreach ($secrets as $secret) {
      try {
        $this->authenticate($request, $secret);
        $response = $handler->handle($request);
        return $response;
      } catch (\Exception $e) {
        error_log('HmacAuthHandler error: ' . $e->getMessage());
      }
    }
    return new Psr7Response(401); 
  }

  private function authenticate(Request $request, string $secret): bool
  {
    $authorizationHeader = $request->getHeaderLine('Authorization');
    if (empty($authorizationHeader) || !preg_match('/^Bearer\s+(.*)$/i', $authorizationHeader, $matches)) {
      throw new \Exception('Missing or invalid Authorization header');
    }

    $providedSignature = $matches[1];

    if (!hash_equals($secret, $providedSignature)) {
      throw new \Exception('Invalid HMAC signature');
    }

    return true;
  }
}
