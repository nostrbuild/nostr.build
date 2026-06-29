<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/utils.funcs.php'; // resolveIdentityUuid() + resolveIdentityNpub()

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
 * This middleware closes that gap: it reads the acting user's stable uuid from
 * X-Accounts-Uuid (already validated by HmacAuthMiddleware upstream — the
 * Worker is the only party that can set that header with a valid HMAC),
 * does a fresh SQL lookup of acctlevel by uuid_id, and rejects with 403 if the
 * user isn't level 99 (ADMIN). Fresh DB read, NOT a session read — defense in
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

    // Gate on the STABLE uuid, not npub: the Worker always sends X-Accounts-Uuid
    // (npub is optional, and absent for an email-only account). resolveIdentityUuid
    // takes the uuid header fast-path, or resolves it from a bare npub. uuid_id is
    // populated for every account, so this returns the same admin row the old
    // usernpub lookup did — without locking out a future email admin.
    $uuid = resolveIdentityUuid($request);
    if (
      $uuid === '' ||
      !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $uuid)
    ) {
      return $this->forbidden('not-admin');
    }

    $stmt = $link->prepare("SELECT acctlevel FROM users WHERE uuid_id = ?");
    if (!$stmt) {
      error_log("ProxiedAdminMiddleware: prepare failed: " . $link->error);
      return $this->forbidden('not-admin');
    }
    $level = null;
    try {
      $stmt->bind_param('s', $uuid);
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
      error_log("ProxiedAdminMiddleware: denied for uuid={$uuid} level=" . ($level ?? 'null'));
      return $this->forbidden('not-admin');
    }

    // Stash both identities for handlers: admin_uuid is the stable key; admin_npub
    // (may be '' for an email admin) is what the per-route self-modify guard still
    // compares today.
    $npub = resolveIdentityNpub($request);
    $request = $request
      ->withAttribute('admin_npub', $npub)
      ->withAttribute('admin_uuid', $uuid);
    return $handler->handle($request);
  }

  private function forbidden(string $error): Response
  {
    $r = new Psr7Response(403);
    $r->getBody()->write(json_encode(['error' => $error]));
    return $r->withHeader('Content-Type', 'application/json');
  }
}
