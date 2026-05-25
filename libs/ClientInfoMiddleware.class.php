<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../api/v2/helper_functions.php';

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Overlay $_SERVER['CLIENT_REQUEST_INFO'] with the real end-user info
 * carried in `x-accounts-client-info` (set by the account.nostr.build
 * Worker proxy in src/server/proxy.ts::buildOutboundHeaders).
 *
 * Why: nginx writes CLIENT_REQUEST_INFO from the immediate TCP peer, which
 * for Worker-proxied requests is the Cloudflare egress fleet (a /16) — not
 * the real user. Consumers that depend on this value (IpAccessControl ban
 * gates, PhotoDNA CSAM reports, S3Multipart audit logs, MultimediaUpload
 * uploadedFileInfo) would otherwise log/decide on the wrong identity.
 *
 * The header value is already a JSON string matching the same shape
 * blossom-band's `x-blossom-client-info` produces — see how routes_blossom.php
 * does the equivalent swap inline for the public Blossom path.
 *
 * Scope: attach this only to the proxied upload routes (the only paths whose
 * downstream code currently consumes CLIENT_REQUEST_INFO). Dashboard
 * mutations get the header too but don't need the swap.
 */
class ClientInfoMiddleware implements MiddlewareInterface
{
  public function process(Request $request, RequestHandler $handler): Response
  {
    $metadata = metadataFromHeaders($request->getHeaders());
    if (!empty($metadata['client_info'])) {
      $_SERVER['CLIENT_REQUEST_INFO'] = $metadata['client_info'];
    }
    return $handler->handle($request);
  }
}
