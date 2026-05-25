<?php

require_once __DIR__ . '/../config.php';

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as Psr7Response;

/**
 * Server-side admin gate for Worker-proxied routes.
 *
 * The existing `adminOnlyMiddleware()` in routes_admin.php trusts the PHP
 * session ($_SESSION["acctlevel"]) — which is the right gate for direct
 * browser hits to /account/admin/*.php (logged-in admin's session cookie),
 * but is empty for HMAC-authenticated requests coming through the
 * account.nostr.build Worker proxy.
 *
 * This middleware closes that gap: it reads the acting user's npub from
 * X-Accounts-Npub (already validated by HmacAuthMiddleware upstream — the
 * Worker is the only party that can set that header with a valid HMAC),
 * does a fresh SQL lookup of acctlevel, and rejects with 403 if the user
 * isn't level 99 (ADMIN). Fresh DB read, NOT a session read — defense in
 * depth against a stale session reflecting a recently-demoted admin.
 *
 * Must be applied OUTSIDE HmacAuthMiddleware so the HMAC verifies before
 * this runs:
 *
 *     $app->group('/accounts/admin/users', function (...) {...})
 *       ->add(new ProxiedAdminMiddleware())
 *       ->add(new HmacAuthMiddleware());
 */
class ProxiedAdminMiddleware implements MiddlewareInterface
{
  public function process(Request $request, RequestHandler $handler): Response
  {
    global $link;

    $npub = trim((string) $request->getHeaderLine('X-Accounts-Npub'));
    // Cheap format sanity: every legitimate npub starts with `npub1`. Block
    // empty / malformed before doing the SQL hit. Length cap mirrors the
    // existing /admin/users/* endpoints.
    if ($npub === '' || strpos($npub, 'npub1') !== 0 || strlen($npub) > 255) {
      return $this->forbidden('not-admin');
    }

    $stmt = $link->prepare("SELECT acctlevel FROM users WHERE usernpub = ?");
    if (!$stmt) {
      error_log("ProxiedAdminMiddleware: prepare failed: " . $link->error);
      return $this->forbidden('not-admin');
    }
    $level = null;
    try {
      $stmt->bind_param('s', $npub);
      if (!$stmt->execute()) {
        error_log("ProxiedAdminMiddleware: execute failed: " . $stmt->error);
        return $this->forbidden('not-admin');
      }
      $res = $stmt->get_result();
      $row = $res ? $res->fetch_assoc() : null;
      $level = $row ? (int) $row['acctlevel'] : null;
    } finally {
      $stmt->close();
    }

    if ($level !== 99) {
      // Log non-admin access attempts — these are anomalies worth keeping
      // an eye on, but we don't expose the level in the response (info leak).
      error_log("ProxiedAdminMiddleware: denied for npub={$npub} level=" . ($level ?? 'null'));
      return $this->forbidden('not-admin');
    }

    // Stash the admin npub as a request attribute so handlers don't need
    // to re-read it from the header. The handler also uses it for the
    // self-modify guard (target npub != admin npub).
    $request = $request->withAttribute('admin_npub', $npub);
    return $handler->handle($request);
  }

  private function forbidden(string $error): Response
  {
    $r = new Psr7Response(403);
    $r->getBody()->write(json_encode(['error' => $error]));
    return $r->withHeader('Content-Type', 'application/json');
  }
}
