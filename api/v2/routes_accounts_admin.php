<?php
/**
 * Proxied admin routes — User Management tool.
 *
 * Mounted at /api/v2/accounts/admin/users/* and reached only via the
 * account.nostr.build Worker proxy (HMAC + X-Accounts-Npub). Path mirror:
 *
 *   Browser → Worker:   /api/admin/users/{lookup,plan,extend,expiry,password-reset}
 *   Worker → PHP:       /api/v2/accounts/admin/users/{...}
 *
 * Security layers, outermost in:
 *   1. CSRF + session cookie verified by the Worker
 *   2. Worker-side admin gate (SessionDO snapshot accountLevel === 99) early-rejects
 *   3. HmacAuthMiddleware: only the Worker can hit these routes
 *   4. ProxiedAdminMiddleware: fresh DB read of acctlevel for X-Accounts-Npub
 *   5. Per-route input validation (npub regex, level enum, date range, days range)
 *   6. Per-route self-modify guard (target_npub !== admin_npub)
 *
 * Each successful mutation emits a `profile-changed` event via
 * WorkerEventsClient targeting the AFFECTED USER (not the admin) so their
 * other devices update. Password reset additionally emits `banned` to
 * force-logout the target user's other sessions.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/HmacAuthMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/ProxiedAdminMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UsersImagesFolders.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/WorkerEventsClient.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/IpAccessControl.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/LegacyBlacklist.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/utils.funcs.php'; // getFileTypeFromName (canonical type map)
require_once __DIR__ . '/helper_functions.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// ----- Helpers (local to this file; named to avoid clashing with route_*) -----

function aaJson(Response $response, array $data, int $status = 200): Response
{
  $response->getBody()->write(json_encode($data));
  return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
}

function aaError(Response $response, string $error, int $status = 400, array $extra = []): Response
{
  return aaJson($response, array_merge(['error' => $error], $extra), $status);
}

/**
 * Validate a target npub. Returns the trimmed npub or null on rejection.
 * Tighter than the cookie path's check because the admin tool is the
 * only legitimate caller and we want input errors surfaced cleanly.
 */
function aaValidNpub(?string $raw): ?string
{
  if (!is_string($raw)) return null;
  $npub = trim($raw);
  if ($npub === '' || strlen($npub) > 255) return null;
  // bech32 alphabet (lowercase letters + digits, minus the bech32-excluded
  // chars 1 b i o — but `npub1` is fixed prefix so just allow a..z 0..9).
  if (!preg_match('/^npub1[02-9ac-hj-np-z]+$/', $npub)) return null;
  return $npub;
}

/** Parse the nl_sub_activation_return_value JSON column. The tier name
 *  is dynamic — read `request.tier` first, then index `current_tier_ends`
 *  by that name. Today only "plus" exists; this keeps the lookup honest
 *  if/when a new tier is added (e.g. "premium").
 *
 *  Returns the legacy three-key display subset (`tier`, `tierEndsAt`,
 *  `timeAddedSeconds`) plus the full set of partner-receipt fields the
 *  billing report needs for reconciliation. The billing fields are keyed
 *  `partner*` so existing callers picking the legacy three are unaffected.
 *
 *  `partnerReceiptRaw` carries the verbatim JSON — safety net for any
 *  receipt key we didn't enumerate today but might need tomorrow. */
function aaParseNlActivation(mixed $raw): array
{
  // PHP's $accountInfo['nl_sub_activation_return_value'] is already
  // decoded (Account::getAccountInfo passes through whatever was stored).
  // Defensive — handle both decoded array and raw JSON string.
  $data = is_string($raw) ? json_decode($raw, true) : $raw;
  $rawJson = is_string($raw)
    ? $raw
    : (is_array($raw) ? json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null);

  if (!is_array($data)) {
    return [
      'tier' => null, 'tierEndsAt' => null, 'timeAddedSeconds' => null,
      'partnerActivationId' => null, 'partnerPubkey' => null,
      'partnerUserId' => null, 'partnerTxnBundle' => null,
      'partnerRequestTier' => null, 'partnerRequestPartner' => null,
      'partnerExecutedAtMs' => null, 'partnerCurrentTierEndsJson' => null,
      'partnerReceiptRaw' => is_string($rawJson) ? $rawJson : null,
    ];
  }
  $tier = $data['request']['tier'] ?? null;
  $tierEndsMs = is_string($tier) ? ($data['current_tier_ends'][$tier] ?? null) : null;
  $currentTierEnds = $data['current_tier_ends'] ?? null;

  return [
    // ----- Legacy display subset (unchanged contract) -----
    'tier'             => $tier,
    'tierEndsAt'       => is_int($tierEndsMs) ? date('Y-m-d H:i:s', intdiv($tierEndsMs, 1000)) : null,
    'timeAddedSeconds' => $data['request']['time_added'] ?? null,
    // ----- Partner-receipt fields for billing reconciliation -----
    'partnerActivationId'        => $data['id'] ?? null,
    'partnerPubkey'              => $data['request']['pubkey'] ?? null,
    'partnerUserId'              => $data['request']['user_id'] ?? null,
    'partnerTxnBundle'           => $data['request']['txn_bundle'] ?? null,
    'partnerRequestTier'         => $data['request']['tier'] ?? null,
    'partnerRequestPartner'      => $data['request']['partner'] ?? null,
    'partnerExecutedAtMs'        => $data['request']['executed_at'] ?? null,
    'partnerCurrentTierEndsJson' => is_array($currentTierEnds)
      ? json_encode($currentTierEnds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
      : null,
    'partnerReceiptRaw' => is_string($rawJson) ? $rawJson : null,
  ];
}

/** Normalised media-usage block for the admin snapshot. One aggregate query
 *  (getTotalStats) yields the per-type counts + sizes AND the storage total,
 *  so this costs a single extra round-trip on top of the lookup. Works for
 *  expired accounts — users_images rows survive expiry, the query keys on
 *  usernpub only. getTotalStats uses inconsistent key names for the tail
 *  categories (documentCount / archiveCount / otherCount), normalised here. */
function aaMediaStats(mysqli $link, string $npub): array
{
  $s = (new UsersImagesFolders($link))->getTotalStats($npub);
  $s = is_array($s) ? $s : [];
  $n = static fn(string $k): int => (int) ($s[$k] ?? 0);
  return [
    'total'        => $n('all'),            'totalSize'     => $n('allSize'),
    'images'       => $n('images'),         'imagesSize'    => $n('imageSize'),
    'gifs'         => $n('gifs'),           'gifsSize'      => $n('gifSize'),
    'videos'       => $n('videos'),         'videosSize'    => $n('videoSize'),
    'audio'        => $n('audio'),          'audioSize'     => $n('audioSize'),
    'documents'    => $n('documentCount'),  'documentsSize' => $n('documentSize'),
    'archives'     => $n('archiveCount'),   'archivesSize'  => $n('archiveSize'),
    'others'       => $n('otherCount'),     'othersSize'    => $n('otherSize'),
    'publicCount'  => $n('publicCount'),
  ];
}

/** Build the lookup response payload from an Account. Mirrors the field
 *  names the TS handler validates against. */
function aaUserSnapshot(Account $account, mysqli $link): array
{
  $info = $account->getAccountInfo();
  // NL status: parse the stored JSON for tier name + tier-ends timestamp,
  // so the admin doesn't have to read raw blobs. eligible/activated come
  // straight from getAccountInfo (mirrors what the profile endpoint returns).
  $nl = aaParseNlActivation($info['nl_sub_activation_return_value'] ?? null);
  // Storage: getAccountInfo already computed both of these (used = SUM over
  // users_images, limit = plan tier + addons), so surfacing them is free.
  // Unlimited tiers (admin) report PHP_INT_MAX — send null + a flag so the
  // client never tries to render a bar or a meaningless percentage.
  $limitRaw = (int) ($info['storage_space_limit'] ?? 0);
  $unlimited = $limitRaw === PHP_INT_MAX;
  return [
    'npub'               => $account->getNpub(),
    'userId'             => $account->getAccountNumericId(),
    'uuidId'             => $account->getAccountUuid(),
    'nym'                => $info['nym'] ?? null,
    'pfpUrl'             => $info['ppic'] ?? null,
    'acctlevel'          => (int) ($info['acctlevel'] ?? 0),
    'planStartDate'      => $info['plan_start_date'] ?? null,
    'planUntilDate'      => $info['plan_until_date'] ?? null,
    'subscriptionPeriod' => $info['subscription_period'] ?? null,
    'remainingDays'      => $account->getRemainingSubscriptionDays(),
    'isExpired'          => $account->isExpired(),
    'npubVerified'       => (bool) ($info['npub_verified'] ?? false),
    'allowNpubLogin'     => (bool) ($info['allow_npub_login'] ?? true),
    'accountFlags'       => $info['accflags'] ?? null,
    'banned'             => (bool) ($info['banned'] ?? false),
    'banReason'          => (string) ($info['ban_reason'] ?? ''),
    'createdAt'          => $info['created_at'] ?? null,
    // Self-service deletion lifecycle (so the admin can see + cancel a pending
    // deletion). deleteAfter is unix seconds. Defaults tolerate a pre-migration DB.
    'deletionStatus'     => $info['deletion_status'] ?? 'none',
    'deletionDeleteAfter' => !empty($info['delete_after']) ? strtotime($info['delete_after']) : null,
    'nlSubEligible'      => (bool) ($info['nl_sub_eligible'] ?? false),
    'nlSubActivated'     => (bool) ($info['nl_sub_activated'] ?? false),
    'nlSubActivatedDate' => $info['nl_sub_activated_date'] ?? null,
    'nlSubActivationId'  => $info['nl_sub_activation_id'] ?? null,
    'nlSubTier'          => $nl['tier'],
    'nlSubTierEndsAt'    => $nl['tierEndsAt'],
    // Storage + media usage (works for active and expired accounts alike).
    'storageUsed'        => (int) ($info['used_storage_space'] ?? 0),
    'storageLimit'       => $unlimited ? null : $limitRaw,
    'storageUnlimited'   => $unlimited,
    // Add-on storage (bytes) granted on top of the plan tier — drives the
    // admin add-on panel's "current" value.
    'storageAddon'       => $account->getAccountAdditionStorage(),
    'media'              => aaMediaStats($link, $account->getNpub()),
  ];
}

/** Emit a profile-changed event for the AFFECTED user (target_npub).
 *  Swallows failures — webhook hiccups must not fail the admin action.
 *
 *  Always pass the new field values when known: this keeps the SessionDO
 *  snapshot coherent for Worker-side fast reads (requireAdmin etc.). Without
 *  `fields`, the broadcast still invalidates the client's profile query, but
 *  the DO snapshot stays stale until the next dashboard profile refetch
 *  round-trips back — leaving a small window where the Worker's early-reject
 *  gates could see old values. PHP's per-route middleware does fresh SQL
 *  reads, so the security boundary holds either way; this is just snapshot
 *  hygiene. */
function aaEmitProfileChanged(?string $targetUuid, array $changed, ?array $fields = null): void
{
  if ($targetUuid === null) return;
  try {
    (new WorkerEventsClient())->emitProfileChanged($targetUuid, $fields, $changed);
  } catch (\Throwable $e) {
    error_log('admin/users: emitProfileChanged failed: ' . $e->getMessage());
  }
}

// ----- CSAM offender-cleanup helpers -----
// Relocated here from the now-removed routes_admin.php (the legacy session
// admin area was deleted). The CSAM routes below are the only remaining
// callers. Names are kept verbatim — isLikelyValidNpub is invoked as a
// string callable in array_filter(), so it must not be renamed.

/**
 * A CSAM case is "delete-eligible" (offender confirmed, cleanup allowed) when
 * its NCMEC report id is either a real numeric report id or the manual
 * EVIDENCE_EXPIRED sentinel — i.e. the offender has been positively identified
 * even if the formal submission couldn't go through. TEST_/FALSE_MATCH/
 * Null:Technical Error/empty are NOT eligible.
 */
function csamCaseAllowsOffenderCleanup(?string $reportId): bool
{
  if ($reportId === null || $reportId === '') return false;
  if ($reportId === 'EVIDENCE_EXPIRED') return true;
  return is_numeric($reportId);
}

/**
 * Tight npub-shape validator. Real bech32 npub is `npub1` + 58 bech32 chars
 * (63 total). We allow [60, 100] to be tolerant of any future format drift but
 * still reject empty / "anonymous" / "Unknown" / partial sentinels — anything
 * that could land in `uploads_data.usernpub = ''` and bulk-match legacy
 * anonymous uploads.
 */
function isLikelyValidNpub(?string $n): bool
{
  if ($n === null) return false;
  $len = strlen($n);
  return $len >= 60 && $len <= 100 && str_starts_with($n, 'npub1');
}

/**
 * Pull the offender (uploader) npub out of an identified_csam_cases.logs JSON.
 *
 * Type 1 logs (filename-keyed): each entry has uploadNpub.
 * Type 2 logs (evidenceData):   ReporteeName carries the npub.
 *
 * Returns null when the npub cannot be confidently identified as a real npub.
 * Critical: the caller MUST treat null as "no actionable target" — never fall
 * back to an empty-string match, which would sweep up every legacy anonymous
 * upload (rows with usernpub = '' or NULL).
 */
function extractOffenderNpubFromLogs(?string $logsJson): ?string
{
  if ($logsJson === null || $logsJson === '') return null;
  $data = json_decode($logsJson, true);
  if (!is_array($data)) return null;

  if (isset($data['evidenceData']['ReporteeName'])) {
    $n = trim((string) $data['evidenceData']['ReporteeName']);
    if (isLikelyValidNpub($n)) return $n;
  }

  foreach ($data as $entry) {
    if (!is_array($entry)) continue;
    $n = isset($entry['uploadNpub']) ? trim((string) $entry['uploadNpub']) : '';
    if (isLikelyValidNpub($n)) return $n;
  }

  return null;
}

// ----- Routes -----

$app->group('/accounts/admin/users', function (RouteCollectorProxy $group) {

  /**
   * GET /accounts/admin/users/lookup?npub=...
   * Fetch full snapshot for the admin's user-card display.
   */
  $group->get('/lookup', function (Request $request, Response $response) {
    global $link;
    // Search term is either an npub (starts with `npub1`) or a uuid — detect by
    // prefix and resolve the right way. npub1… → by npub; otherwise by uuid_id.
    $q = trim((string) ($request->getQueryParams()['q'] ?? ''));
    if ($q === '' || strlen($q) > 255) return aaError($response, 'invalid-query', 400);

    if (str_starts_with($q, 'npub1')) {
      $npub = aaValidNpub($q);
      if ($npub === null) return aaError($response, 'invalid-query', 400);
      $account = new Account($npub, $link);
    } else {
      if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $q)) {
        return aaError($response, 'invalid-query', 400);
      }
      $account = Account::fromUuid($q, $link);
      if ($account === null) return aaError($response, 'not-found', 404);
    }
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    return aaJson($response, aaUserSnapshot($account, $link));
  });

  /**
   * GET /accounts/admin/users/overview
   * Default landing stats for the User Management tool — shown before the
   * admin looks anyone up. Three LIMIT-bounded reads over the (small) users
   * table; the client caches the result (short staleTime) so navigating
   * back to the tool doesn't re-hit the DB. No input.
   */
  $group->get('/overview', function (Request $request, Response $response) {
    global $link;

    // ----- Recently expired paid plans (last 30 days, newest-first) -----
    // Paid tiers only (1..10 covers Creator/Pro/Purist/Viewer/Starter/Advanced;
    // excludes Free=0 and staff 89/99 which have no meaningful expiry).
    $recentlyExpired = [];
    $sql = "SELECT usernpub, uuid_id, nym, ppic, acctlevel, plan_until_date
            FROM users
            WHERE acctlevel BETWEEN 1 AND 10
              AND plan_until_date IS NOT NULL
              AND plan_until_date < CURDATE()
              AND plan_until_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            ORDER BY plan_until_date DESC
            LIMIT 20";
    if ($res = $link->query($sql)) {
      while ($row = $res->fetch_assoc()) {
        $recentlyExpired[] = [
          'npub'          => $row['usernpub'],
          'uuidId'        => $row['uuid_id'],
          'nym'           => $row['nym'],
          'pfpUrl'        => $row['ppic'],
          'acctlevel'     => (int) $row['acctlevel'],
          'planUntilDate' => $row['plan_until_date'],
        ];
      }
      $res->free();
    }

    // ----- Expiring soon (paid plans lapsing within 14 days, soonest-first) ---
    // The proactive mirror of recentlyExpired — catch churn before it happens.
    $expiringSoon = [];
    $sql = "SELECT usernpub, uuid_id, nym, ppic, acctlevel, plan_until_date
            FROM users
            WHERE acctlevel BETWEEN 1 AND 10
              AND plan_until_date IS NOT NULL
              AND plan_until_date >= CURDATE()
              AND plan_until_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
            ORDER BY plan_until_date ASC
            LIMIT 20";
    if ($res = $link->query($sql)) {
      while ($row = $res->fetch_assoc()) {
        $expiringSoon[] = [
          'npub'          => $row['usernpub'],
          'uuidId'        => $row['uuid_id'],
          'nym'           => $row['nym'],
          'pfpUrl'        => $row['ppic'],
          'acctlevel'     => (int) $row['acctlevel'],
          'planUntilDate' => $row['plan_until_date'],
        ];
      }
      $res->free();
    }

    // ----- Oldest expired (longest-lapsed paid plans, oldest-first) -----
    // Win-back / storage-reclaim triage: accounts expired long ago whose media
    // still survives. No time window — we want the oldest, full stop.
    $oldestExpired = [];
    $sql = "SELECT usernpub, uuid_id, nym, ppic, acctlevel, plan_until_date
            FROM users
            WHERE acctlevel BETWEEN 1 AND 10
              AND plan_until_date IS NOT NULL
              AND plan_until_date < CURDATE()
            ORDER BY plan_until_date ASC
            LIMIT 20";
    if ($res = $link->query($sql)) {
      while ($row = $res->fetch_assoc()) {
        $oldestExpired[] = [
          'npub'          => $row['usernpub'],
          'uuidId'        => $row['uuid_id'],
          'nym'           => $row['nym'],
          'pfpUrl'        => $row['ppic'],
          'acctlevel'     => (int) $row['acctlevel'],
          'planUntilDate' => $row['plan_until_date'],
        ];
      }
      $res->free();
    }

    // ----- Newest accounts (ordered by PK = creation order, so this rides
    // the primary index instead of a created_at filesort) -----
    $newestAccounts = [];
    $sql = "SELECT usernpub, uuid_id, nym, ppic, acctlevel, created_at
            FROM users
            ORDER BY id DESC
            LIMIT 20";
    if ($res = $link->query($sql)) {
      while ($row = $res->fetch_assoc()) {
        $newestAccounts[] = [
          'npub'      => $row['usernpub'],
          'uuidId'    => $row['uuid_id'],
          'nym'       => $row['nym'],
          'pfpUrl'    => $row['ppic'],
          'acctlevel' => (int) $row['acctlevel'],
          'createdAt' => $row['created_at'],
        ];
      }
      $res->free();
    }

    // ----- Plan distribution (one grouped scan over users) -----
    // active/expired split is only meaningful for paid tiers; for Free/staff
    // it stays 0 and the client just shows the total.
    $planDistribution = [];
    $sql = "SELECT acctlevel,
              COUNT(*) AS total,
              SUM(CASE WHEN acctlevel BETWEEN 1 AND 10
                        AND plan_until_date >= CURDATE() THEN 1 ELSE 0 END) AS active,
              SUM(CASE WHEN acctlevel BETWEEN 1 AND 10
                        AND (plan_until_date IS NULL OR plan_until_date < CURDATE()) THEN 1 ELSE 0 END) AS expired
            FROM users
            GROUP BY acctlevel
            ORDER BY acctlevel";
    if ($res = $link->query($sql)) {
      while ($row = $res->fetch_assoc()) {
        $planDistribution[] = [
          'level'   => (int) $row['acctlevel'],
          'total'   => (int) $row['total'],
          'active'  => (int) $row['active'],
          'expired' => (int) $row['expired'],
        ];
      }
      $res->free();
    }

    return aaJson($response, [
      'expiringSoon'     => $expiringSoon,
      'recentlyExpired'  => $recentlyExpired,
      'oldestExpired'    => $oldestExpired,
      'newestAccounts'   => $newestAccounts,
      'planDistribution' => $planDistribution,
    ]);
  });

  /**
   * GET /accounts/admin/users/banned?cursor=&limit=&q=
   * Banned registered accounts only — blacklist ⋈ users. The INNER JOIN drops
   * anonymous npub/IP-only bans that have no account. Keyset-paginated on the
   * blacklist PK (newest ban first); `q` matches nym / npub / uuid. Returns the
   * REAL per-row blacklist.reason (the single lookup hardcodes its reason).
   */
  $group->get('/banned', function (Request $request, Response $response) {
    global $link;
    $params = $request->getQueryParams();
    $limit  = max(1, min(200, (int) ($params['limit'] ?? 50)));
    $cursor = max(0, (int) ($params['cursor'] ?? 0)); // 0 = first page (no upper bound)
    $q      = isset($params['q']) ? trim((string) $params['q']) : '';
    if (strlen($q) > 255) return aaError($response, 'invalid-query', 400);

    // Keyset cursor and search are both optional — build the WHERE dynamically.
    $where = [];
    $types = '';
    $args  = [];
    if ($cursor > 0) {
      $where[] = 'b.id < ?';
      $types  .= 'i';
      $args[]  = $cursor;
    }
    if ($q !== '') {
      $like    = '%' . $q . '%';
      $where[] = '(u.nym LIKE ? OR b.npub LIKE ? OR u.uuid_id LIKE ?)';
      $types  .= 'sss';
      $args[]  = $like; $args[] = $like; $args[] = $like;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    // blacklist has NO unique constraint on npub — a user can have many ban
    // rows. Collapse to the latest ban per npub (MAX(id)) so each account
    // shows once; that id is unique, keeping the keyset clean across pages.
    $sql = "SELECT b.id AS ban_id, b.npub, b.timestamp AS ban_timestamp, b.reason AS ban_reason,
                   u.uuid_id, u.nym, u.ppic, u.acctlevel, u.plan_until_date, u.created_at
              FROM blacklist b
              INNER JOIN (
                SELECT npub, MAX(id) AS max_id
                  FROM blacklist
                 WHERE npub IS NOT NULL
                 GROUP BY npub
              ) latest ON b.id = latest.max_id
              INNER JOIN users u ON b.npub = u.usernpub
              $whereSql
             ORDER BY b.id DESC
             LIMIT ?";
    $types .= 'i';
    $args[] = $limit;

    $stmt = $link->prepare($sql);
    $stmt->bind_param($types, ...$args);
    $stmt->execute();
    $rs = $stmt->get_result();

    $today = date('Y-m-d');
    $rows = [];
    while ($r = $rs->fetch_assoc()) {
      $level = (int) $r['acctlevel'];
      $until = $r['plan_until_date'] ?? null;
      // Expiry only means something for paid tiers (1..10); Free/staff never expire.
      $isExpired = $level >= 1 && $level <= 10 && (empty($until) || $until < $today);
      $rows[] = [
        'npub'          => (string) $r['npub'],
        'uuidId'        => $r['uuid_id'],
        'nym'           => $r['nym'],
        'pfpUrl'        => $r['ppic'],
        'acctlevel'     => $level,
        'planUntilDate' => $until,
        'createdAt'     => $r['created_at'],
        'banTimestamp'  => (string) $r['ban_timestamp'],
        'banReason'     => $r['ban_reason'] !== null ? (string) $r['ban_reason'] : '',
        'isExpired'     => $isExpired,
        'banId'         => (int) $r['ban_id'],
      ];
    }
    $stmt->close();

    // Another page exists only if this one filled; next cursor = last ban id.
    $nextCursor = count($rows) === $limit ? (int) $rows[count($rows) - 1]['banId'] : null;

    return aaJson($response, ['rows' => $rows, 'nextCursor' => $nextCursor]);
  });

  /**
   * POST /accounts/admin/users/plan
   * Body: { npub, level: 0..99, period: '1y'|'2y'|'3y' }
   * Sets the user's level + plan dates. Level 0 demotes to Free (clears plan dates).
   */
  $group->post('/plan', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub  = aaValidNpub($body['npub'] ?? null);
    $level = isset($body['level']) && is_int($body['level']) ? $body['level'] : null;
    $period = isset($body['period']) && is_string($body['period']) ? $body['period'] : null;

    if ($npub === null)   return aaError($response, 'invalid-npub', 400);
    if ($level === null || !in_array($level, [0, 1, 2, 3, 4, 5, 10, 89, 99], true)) {
      return aaError($response, 'invalid-level', 400);
    }
    if ($period === null || !in_array($period, ['1y', '2y', '3y'], true)) {
      return aaError($response, 'invalid-period', 400);
    }

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $result = $account->adminSetPlan($level, $period);
    } catch (\InvalidArgumentException $e) {
      return aaError($response, 'invalid-input', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
      error_log("admin/users/plan failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    // adminSetPlan re-fetches account data; remainingDays is fresh here.
    aaEmitProfileChanged(
      $account->getAccountUuid(),
      ['accountLevel', 'remainingDays'],
      [
        'accountLevel'  => $result['level'],
        'remainingDays' => $account->getRemainingSubscriptionDays(),
      ],
    );
    return aaJson($response, ['ok' => true, 'planUntilDate' => $result['planUntilDate'], 'level' => $result['level']]);
  });

  /**
   * POST /accounts/admin/users/extend
   * Body: { npub, days: 1..3650 }
   * Adds N days to plan_until_date (from current expiry, or today if expired).
   */
  $group->post('/extend', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    $days = isset($body['days']) && is_int($body['days']) ? $body['days'] : null;

    if ($npub === null)  return aaError($response, 'invalid-npub', 400);
    if ($days === null || $days < 1 || $days > 3650) {
      return aaError($response, 'invalid-days', 400);
    }

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $newEnd = $account->adminExtendSubscription($days);
    } catch (\InvalidArgumentException $e) {
      return aaError($response, 'invalid-input', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
      error_log("admin/users/extend failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    // Reactivation clears any pending deletion (adminExtendSubscription did it in
    // the DB) — mirror that into the DO snapshot so the user's deletion banner /
    // forced redirect clears without a PHP refetch. An active account's status is
    // always 'none'; emitting it is a no-op when nothing was pending.
    aaEmitProfileChanged(
      $account->getAccountUuid(),
      ['remainingDays', 'deletionStatus', 'deletionDeleteAfter'],
      [
        'remainingDays' => $account->getRemainingSubscriptionDays(),
        'deletionStatus' => 'none',
        'deletionDeleteAfter' => null,
      ],
    );
    return aaJson($response, ['ok' => true, 'planUntilDate' => $newEnd]);
  });

  /**
   * POST /accounts/admin/users/expiry
   * Body: { npub, date: 'YYYY-MM-DD' (today or later) }
   */
  $group->post('/expiry', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    $date = isset($body['date']) && is_string($body['date']) ? $body['date'] : null;

    if ($npub === null) return aaError($response, 'invalid-npub', 400);
    if ($date === null) return aaError($response, 'invalid-date', 400);

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $newEnd = $account->adminSetExpiryDate($date);
    } catch (\InvalidArgumentException $e) {
      return aaError($response, 'invalid-input', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
      error_log("admin/users/expiry failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    // Reactivation clears any pending deletion (adminSetExpiryDate did it in the
    // DB) — mirror it into the DO snapshot (see /extend).
    aaEmitProfileChanged(
      $account->getAccountUuid(),
      ['remainingDays', 'deletionStatus', 'deletionDeleteAfter'],
      [
        'remainingDays' => $account->getRemainingSubscriptionDays(),
        'deletionStatus' => 'none',
        'deletionDeleteAfter' => null,
      ],
    );
    return aaJson($response, ['ok' => true, 'planUntilDate' => $newEnd]);
  });

  /**
   * POST /accounts/admin/users/addon-storage
   * Body: { npub, blocks: 0..1000 }  (each block = 10 GiB)
   * Sets the account's add-on storage allowance, added on top of the plan
   * tier limit. Absolute SET (idempotent); blocks=0 removes the add-on.
   */
  $group->post('/addon-storage', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    $blocks = isset($body['blocks']) && is_int($body['blocks']) ? $body['blocks'] : null;

    if ($npub === null) return aaError($response, 'invalid-npub', 400);
    if ($blocks === null || $blocks < 0 || $blocks > 1000) {
      return aaError($response, 'invalid-blocks', 400);
    }

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    // 10 GiB per block. blocks <= 1000 → <= 10 TiB, comfortably within int64.
    $bytes = $blocks * 10 * 1024 * 1024 * 1024;
    try {
      $stored = $account->setAddonStorage($bytes);
    } catch (\InvalidArgumentException $e) {
      return aaError($response, 'invalid-input', 400, ['detail' => $e->getMessage()]);
    } catch (\Throwable $e) {
      error_log("admin/users/addon-storage failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    // Invalidate the target's profile so their storage quota updates live.
    aaEmitProfileChanged($account->getAccountUuid(), ['storage']);
    return aaJson($response, ['ok' => true, 'addonBytes' => $stored, 'blocks' => $blocks]);
  });

  /**
   * POST /accounts/admin/users/verify-npub
   * Body: { npub, verified: bool }
   * Toggle the npub_verified marker. Usually set by NIP-05/DM verification —
   * this is the admin override for support cases.
   */
  $group->post('/verify-npub', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    // Strict boolean — no string coercion. Mirrors password-reset's killSessions.
    if (!isset($body['verified']) || !is_bool($body['verified'])) {
      return aaError($response, 'invalid-verified', 400);
    }
    $verified = $body['verified'];

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $account->adminSetNpubVerified($verified);
    } catch (\Throwable $e) {
      error_log("admin/users/verify-npub failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged(
      $account->getAccountUuid(),
      ['npubVerified'],
      ['npubVerified' => $verified],
    );
    return aaJson($response, ['ok' => true, 'npubVerified' => $verified]);
  });

  /**
   * POST /accounts/admin/users/allow-npub-login
   * Body: { npub, allow: bool }
   * Toggle allow_npub_login. When false, future npub-based logins are
   * blocked — existing sessions stay live (use password-reset with
   * killSessions to also kick them).
   */
  $group->post('/allow-npub-login', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    if (!isset($body['allow']) || !is_bool($body['allow'])) {
      return aaError($response, 'invalid-allow', 400);
    }
    $allow = $body['allow'];

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $account->adminSetAllowNpubLogin($allow);
    } catch (\Throwable $e) {
      error_log("admin/users/allow-npub-login failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged(
      $account->getAccountUuid(),
      ['allowNostrLogin'],
      ['allowNostrLogin' => $allow],
    );
    return aaJson($response, ['ok' => true, 'allowNpubLogin' => $allow]);
  });

  /**
   * POST /accounts/admin/users/cancel-deletion
   * Body: { npub }
   * Admin override: cancel a pending self-service account deletion WITHOUT
   * requiring a renewal/payment (legal hold, user asked for more time, support).
   * The user's own cancel path is renew/upgrade; this is the staff escape hatch.
   * Idempotent (safe when nothing is pending).
   */
  $group->post('/cancel-deletion', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $account->cancelDeletion();
    } catch (\Throwable $e) {
      error_log("admin/users/cancel-deletion failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    aaEmitProfileChanged(
      $account->getAccountUuid(),
      ['deletionStatus', 'deletionDeleteAfter'],
      ['deletionStatus' => 'none', 'deletionDeleteAfter' => null],
    );
    return aaJson($response, ['ok' => true, 'deletionStatus' => 'none']);
  });

  /**
   * POST /accounts/admin/users/password-reset
   * Body: { npub, killSessions?: bool (default false) }
   * Generates a random password, persists hashes, returns plaintext ONCE.
   * Optionally force-logs-out the target user's other sessions —
   * defaults OFF because the typical case is "user forgot password,
   * admin shares the new one out-of-band, user keeps using their
   * already-authenticated phone." Switch ON for the
   * compromised-account / disgruntled-employee case.
   */
  $group->post('/password-reset', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);

    $npub = aaValidNpub($body['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);

    // Optional, default false. Accept actual booleans only — string
    // "false"/"true" coercion is too lenient for an action this destructive.
    $killSessions = isset($body['killSessions']) ? $body['killSessions'] === true : false;

    $adminNpub = (string) $request->getAttribute('admin_npub');
    if ($adminNpub !== '' && $adminNpub === $npub) {
      return aaError($response, 'self-modify-forbidden', 403);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    try {
      $plaintext = $account->adminResetPassword();
    } catch (\Throwable $e) {
      error_log("admin/users/password-reset failed for {$npub}: " . $e->getMessage());
      return aaError($response, 'server-error', 500);
    }

    // Optional force-logout: clears every active session for the target
    // user. Done via the existing `banned` event arm; not a literal ban,
    // just reusing the broadcast-and-close machinery.
    $targetUuid = $account->getAccountUuid();
    if ($killSessions && $targetUuid !== null) {
      try {
        (new WorkerEventsClient())->emitBanned($targetUuid);
      } catch (\Throwable $e) {
        error_log('admin/users/password-reset: emitBanned failed: ' . $e->getMessage());
      }
    }

    // NOTE: we deliberately do NOT log the plaintext. error_log of this
    // payload would defeat the entire "shown once" guarantee.
    return aaJson($response, [
      'ok' => true,
      'password' => $plaintext,
      'sessionsKilled' => $killSessions,
    ]);
  });

})
  ->add(new ProxiedAdminMiddleware())
  ->add(new HmacAuthMiddleware());

// ----- Activations report group --------------------------------------------
// Not scoped to a single user, so it gets its own group. Same auth stack.

$app->group('/accounts/admin/nl-activations', function (RouteCollectorProxy $group) {
  /**
   * GET /accounts/admin/nl-activations?month=YYYY-MM
   * Returns every NL activation triggered during the selected month.
   * Each row = one billable API call to the NL partner.
   *
   * Response:
   *   {
   *     month:                  "2026-05",
   *     monthStart:             "2026-05-01 00:00:00",
   *     monthEnd:               "2026-06-01 00:00:00",   // exclusive
   *     count:                  N,
   *     totalTimeAddedSeconds:  N,
   *     activations: [
   *       { npub, nym, activatedDate, activationId, tier, tierEndsAt,
   *         timeAddedSeconds }
   *     ]
   *   }
   */
  $group->get('', function (Request $request, Response $response) {
    global $link;
    $monthParam = trim((string) ($request->getQueryParams()['month'] ?? ''));
    // Strict YYYY-MM. Anything else: 400. Default-to-current is the
    // CLIENT's job — keeping the server explicit avoids the "I sent
    // nothing and got data" ambiguity.
    if (!preg_match('/^(\d{4})-(\d{2})$/', $monthParam, $m)) {
      return aaError($response, 'invalid-month', 400, ['detail' => 'expected YYYY-MM']);
    }
    $year  = (int) $m[1];
    $month = (int) $m[2];
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
      return aaError($response, 'invalid-month', 400);
    }
    $monthStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    // Exclusive upper bound = first day of next month.
    $monthEndYear  = $month === 12 ? $year + 1 : $year;
    $monthEndMonth = $month === 12 ? 1 : $month + 1;
    $monthEnd = sprintf('%04d-%02d-01 00:00:00', $monthEndYear, $monthEndMonth);

    $stmt = $link->prepare(
      "SELECT usernpub, nym, nl_sub_activated_date, nl_sub_activation_id, nl_sub_activation_return_value
       FROM users
       WHERE nl_sub_activated_date >= ? AND nl_sub_activated_date < ?
       ORDER BY nl_sub_activated_date DESC"
    );
    if (!$stmt) {
      error_log('admin/nl-activations prepare failed: ' . $link->error);
      return aaError($response, 'server-error', 500);
    }

    $activations = [];
    $totalSeconds = 0;
    try {
      $stmt->bind_param('ss', $monthStart, $monthEnd);
      if (!$stmt->execute()) {
        error_log('admin/nl-activations execute failed: ' . $stmt->error);
        return aaError($response, 'server-error', 500);
      }
      $res = $stmt->get_result();
      while ($row = ($res ? $res->fetch_assoc() : null)) {
        $nl = aaParseNlActivation($row['nl_sub_activation_return_value']);
        $secs = is_int($nl['timeAddedSeconds']) ? $nl['timeAddedSeconds'] : 0;
        $totalSeconds += $secs;
        $activations[] = [
          'npub'             => $row['usernpub'],
          'nym'              => $row['nym'],
          'activatedDate'    => $row['nl_sub_activated_date'],
          'activationId'     => $row['nl_sub_activation_id'],
          'tier'             => $nl['tier'],
          'tierEndsAt'       => $nl['tierEndsAt'],
          'timeAddedSeconds' => $nl['timeAddedSeconds'],
          // Partner-receipt fields used by the billing report (admin
          // panel → Billing tab). All nullable on the wire — existing
          // callers that don't need them ignore the extra keys.
          'partnerActivationId'        => $nl['partnerActivationId'],
          'partnerPubkey'              => $nl['partnerPubkey'],
          'partnerUserId'              => $nl['partnerUserId'],
          'partnerTxnBundle'           => $nl['partnerTxnBundle'],
          'partnerRequestTier'         => $nl['partnerRequestTier'],
          'partnerRequestPartner'      => $nl['partnerRequestPartner'],
          'partnerExecutedAtMs'        => $nl['partnerExecutedAtMs'],
          'partnerCurrentTierEndsJson' => $nl['partnerCurrentTierEndsJson'],
          'partnerReceiptRaw'          => $nl['partnerReceiptRaw'],
        ];
      }
    } finally {
      $stmt->close();
    }

    return aaJson($response, [
      'month'                 => $monthParam,
      'monthStart'            => $monthStart,
      'monthEnd'              => $monthEnd,
      'count'                 => count($activations),
      'totalTimeAddedSeconds' => $totalSeconds,
      'activations'           => $activations,
    ]);
  });
})
  ->add(new ProxiedAdminMiddleware())
  ->add(new HmacAuthMiddleware());

// ----- IP & Access Control group --------------------------------------------
// Worker-proxied backend for the account.nostr.build "IP & Access" admin tool.
// Mounted at /api/v2/accounts/admin/security/*. Same auth stack as above
// (HMAC + ProxiedAdmin). Delegates all CIDR/range math + dedup to
// IpAccessControl; legacy npub/ip bans to LegacyBlacklist; WHOIS to CymruWhois.
// Response shapes mirror the TS Zod schemas in src/server/handlers/admin/security.ts.

$app->group('/accounts/admin/security', function (RouteCollectorProxy $group) {

  // ---- Blocklist (CIDR) ----

  $group->get('/blocklist', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $opts = [
      'limit'       => isset($q['limit']) ? (int) $q['limit'] : 100,
      'offset'      => isset($q['offset']) ? (int) $q['offset'] : 0,
      'active_only' => !empty($q['active_only']),
    ];
    if (isset($q['source']) && $q['source'] !== '') $opts['source'] = (string) $q['source'];
    if (isset($q['q']) && $q['q'] !== '') $opts['q'] = (string) $q['q'];
    $iac = new IpAccessControl($link);
    return aaJson($response, [
      'rows'   => $iac->listBlocks($opts),
      'total'  => $iac->countBlocks($opts['source'] ?? null, $opts['active_only'], $opts['q'] ?? ''),
      'limit'  => $opts['limit'],
      'offset' => $opts['offset'],
    ]);
  });

  $group->post('/blocklist', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);
    $cidr = trim((string) ($body['cidr'] ?? ''));
    if ($cidr === '') return aaError($response, 'cidr is required', 400);
    $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;
    $source = isset($body['source']) && $body['source'] !== '' ? (string) $body['source'] : 'manual';
    $expiresAt = isset($body['expires_at']) && $body['expires_at'] !== '' ? (string) $body['expires_at'] : null;
    if ($expiresAt !== null && strtotime($expiresAt) === false) return aaError($response, 'Invalid expires_at format', 400);
    try {
      $iac = new IpAccessControl($link);
      $id = $iac->addBlock($cidr, $reason ?: null, $source, $expiresAt);
      if ($id === null) return aaError($response, 'Duplicate range — this CIDR is already blocked', 409);
      return aaJson($response, ['success' => true, 'id' => $id, 'cidr' => IpAccessControl::normalizeCidr($cidr)]);
    } catch (InvalidArgumentException $e) {
      return aaError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('aa security blocklist add: ' . $e->getMessage());
      return aaError($response, 'Failed to add block', 500);
    }
  });

  $group->post('/blocklist/bulk', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);
    $source = trim((string) ($body['source'] ?? ''));
    if ($source === '') return aaError($response, 'source is required', 400);
    $cidrs = $body['cidrs'] ?? [];
    if (!is_array($cidrs)) return aaError($response, 'cidrs must be an array', 400);
    $reason = isset($body['reason']) && $body['reason'] !== '' ? (string) $body['reason'] : null;
    try {
      $count = (new IpAccessControl($link))->replaceBySource($source, $cidrs, $reason);
      return aaJson($response, ['success' => true, 'count' => $count, 'source' => $source]);
    } catch (InvalidArgumentException $e) {
      return aaError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('aa security blocklist bulk: ' . $e->getMessage());
      return aaError($response, 'Bulk replace failed', 500);
    }
  });

  $group->delete('/blocklist/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];
    if (!(new IpAccessControl($link))->removeBlock($id)) return aaError($response, 'Block not found', 404);
    return aaJson($response, ['success' => true, 'id' => $id]);
  });

  // ---- Whitelist (per-user override) ----

  $group->get('/whitelist', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $opts = [
      'limit'       => isset($q['limit']) ? (int) $q['limit'] : 100,
      'offset'      => isset($q['offset']) ? (int) $q['offset'] : 0,
      'active_only' => !empty($q['active_only']),
    ];
    if (isset($q['q']) && $q['q'] !== '') $opts['q'] = (string) $q['q'];
    $iac = new IpAccessControl($link);
    return aaJson($response, [
      'rows'   => $iac->listWhitelist($opts),
      'total'  => $iac->countWhitelist($opts),
      'limit'  => $opts['limit'],
      'offset' => $opts['offset'],
    ]);
  });

  $group->post('/whitelist', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);
    $userId = trim((string) ($body['user_id'] ?? ''));
    if ($userId === '') return aaError($response, 'user_id is required', 400);
    $reason = isset($body['reason']) ? trim((string) $body['reason']) : null;
    $expiresAt = isset($body['expires_at']) && $body['expires_at'] !== '' ? (string) $body['expires_at'] : null;
    if ($expiresAt !== null && strtotime($expiresAt) === false) return aaError($response, 'Invalid expires_at format', 400);
    try {
      (new IpAccessControl($link))->addToWhitelist($userId, $reason ?: null, $expiresAt);
      return aaJson($response, ['success' => true, 'user_id' => $userId]);
    } catch (InvalidArgumentException $e) {
      return aaError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('aa security whitelist add: ' . $e->getMessage());
      return aaError($response, 'Failed to add whitelist entry', 500);
    }
  });

  $group->delete('/whitelist/{userId}', function (Request $request, Response $response, array $args) {
    global $link;
    $userId = (string) $args['userId'];
    if (!(new IpAccessControl($link))->removeFromWhitelist($userId)) return aaError($response, 'Whitelist entry not found', 404);
    return aaJson($response, ['success' => true, 'user_id' => $userId]);
  });

  // ---- Legacy npub/IP blacklist ----

  $group->get('/legacy-blacklist', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $opts = [
      'q'      => isset($q['q']) ? (string) $q['q'] : '',
      'limit'  => isset($q['limit']) ? (int) $q['limit'] : 100,
      'offset' => isset($q['offset']) ? (int) $q['offset'] : 0,
    ];
    $bl = new LegacyBlacklist($link);
    return aaJson($response, [
      'rows'   => $bl->list($opts),
      'total'  => $bl->count($opts['q']),
      'limit'  => $opts['limit'],
      'offset' => $opts['offset'],
      'q'      => $opts['q'],
    ]);
  });

  $group->post('/legacy-blacklist', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);
    $npub = isset($body['npub']) ? trim((string) $body['npub']) : '';
    $ip = isset($body['ip']) ? trim((string) $body['ip']) : '';
    $ua = isset($body['user_agent']) ? trim((string) $body['user_agent']) : '';
    $reason = isset($body['reason']) ? trim((string) $body['reason']) : '';
    if ($npub === '' && $ip === '') return aaError($response, 'Provide npub and/or ip', 400);
    try {
      $id = (new LegacyBlacklist($link))->add(
        $npub !== '' ? $npub : null,
        $ip !== '' ? $ip : null,
        $ua !== '' ? $ua : null,
        $reason !== '' ? $reason : null,
      );
      return aaJson($response, ['success' => true, 'id' => $id]);
    } catch (InvalidArgumentException $e) {
      return aaError($response, $e->getMessage(), 422);
    } catch (\Throwable $e) {
      error_log('aa security legacy add: ' . $e->getMessage());
      return aaError($response, 'Failed to add blacklist entry', 500);
    }
  });

  $group->delete('/legacy-blacklist/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];
    if (!(new LegacyBlacklist($link))->removeById($id)) return aaError($response, 'Blacklist entry not found', 404);
    return aaJson($response, ['success' => true, 'id' => $id]);
  });

  // WHOIS is app-native (Team Cymru over DoH in the Worker) — no PHP route.

})
  ->add(new ProxiedAdminMiddleware())
  ->add(new HmacAuthMiddleware());

// ----- CSAM Reporting group ---------------------------------------------------
// Worker-proxied backend for the "CSAM Reporting" admin tool (the
// admin_csam_cases.php port). Mounted at /api/v2/accounts/admin/csam/*; same
// auth stack (HMAC + ProxiedAdmin → admin-only).
//
// IMPORTANT division of labor: the NCMEC CyberTipline submission itself now
// runs in the WORKER (TS port; evidence read straight from R2). PHP keeps the
// thin data endpoints over identified_csam_cases (the Worker has no direct
// DB), the guarded record-submission write, the unblacklist flow, and the
// offender enumerations with their npub mass-delete SQL guards. The npub
// engine helpers (csamCaseAllowsOffenderCleanup, extractOffenderNpubFromLogs,
// isLikelyValidNpub) are defined at the top of this file; NCMECReportHandler
// comes from NCMECReportHandler.class.php.

$app->group('/accounts/admin/csam', function (RouteCollectorProxy $group) {

  /**
   * GET /accounts/admin/csam/cases?page=&limit=&hash=
   * Paginated case list (newest first). `hash` = file_sha256_hash prefix
   * search. Row payload excludes the heavy JSON columns; `GET /case/{id}`
   * fetches one case in full.
   */
  $group->get('/cases', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $limit = max(1, min(200, (int) ($q['limit'] ?? 50)));
    $page  = max(0, (int) ($q['page'] ?? 0));
    $start = $page * $limit;
    $hash  = isset($q['hash']) ? trim((string) $q['hash']) : '';

    if ($hash !== '') {
      if (!preg_match('/^[a-f0-9]{1,64}$/i', $hash)) {
        return aaError($response, 'Invalid hash prefix', 400);
      }
      $like = $hash . '%';
      $cnt = $link->prepare('SELECT COUNT(*) AS c FROM identified_csam_cases WHERE file_sha256_hash LIKE ?');
      $cnt->bind_param('s', $like);
      $cnt->execute();
      $total = (int) ($cnt->get_result()->fetch_assoc()['c'] ?? 0);
      $cnt->close();

      $stmt = $link->prepare(
        'SELECT id, timestamp, identified_by_npub, file_sha256_hash, ncmec_report_id,
                (ncmec_submitted_report IS NOT NULL) AS has_report
           FROM identified_csam_cases
          WHERE file_sha256_hash LIKE ?
          ORDER BY id DESC LIMIT ?, ?'
      );
      $stmt->bind_param('sii', $like, $start, $limit);
    } else {
      $total = (int) ($link->query('SELECT COUNT(*) AS c FROM identified_csam_cases')->fetch_assoc()['c'] ?? 0);
      $stmt = $link->prepare(
        'SELECT id, timestamp, identified_by_npub, file_sha256_hash, ncmec_report_id,
                (ncmec_submitted_report IS NOT NULL) AS has_report
           FROM identified_csam_cases
          ORDER BY id DESC LIMIT ?, ?'
      );
      $stmt->bind_param('ii', $start, $limit);
    }
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($r = $rs->fetch_assoc()) {
      $rows[] = [
        'id' => (int) $r['id'],
        'timestamp' => (string) $r['timestamp'],
        'identified_by_npub' => (string) ($r['identified_by_npub'] ?? ''),
        'file_sha256_hash' => (string) ($r['file_sha256_hash'] ?? ''),
        'ncmec_report_id' => $r['ncmec_report_id'] !== null ? (string) $r['ncmec_report_id'] : null,
        'has_report' => (bool) $r['has_report'],
      ];
    }
    $stmt->close();

    return aaJson($response, ['rows' => $rows, 'total' => $total, 'page' => $page, 'limit' => $limit]);
  });

  /**
   * GET /accounts/admin/csam/case/{id}
   * One case in full — logs + submitted report JSON + evidence location. The
   * Worker uses this for the per-case viewers, the NCMEC report build, and
   * the never-double-report idempotency re-check before each submission.
   */
  $group->get('/case/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];
    $stmt = $link->prepare('SELECT * FROM identified_csam_cases WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
      return aaError($response, 'Case not found', 404);
    }
    return aaJson($response, [
      'id' => (int) $row['id'],
      'timestamp' => (string) $row['timestamp'],
      'identified_by_npub' => (string) ($row['identified_by_npub'] ?? ''),
      'evidence_location_url' => (string) ($row['evidence_location_url'] ?? ''),
      'file_sha256_hash' => (string) ($row['file_sha256_hash'] ?? ''),
      'logs' => $row['logs'] !== null ? (string) $row['logs'] : null,
      'ncmec_report_id' => $row['ncmec_report_id'] !== null ? (string) $row['ncmec_report_id'] : null,
      'ncmec_submitted_report' => $row['ncmec_submitted_report'] !== null ? (string) $row['ncmec_submitted_report'] : null,
    ]);
  });

  /**
   * POST /accounts/admin/csam/record-case
   * {evidenceLocationUrl, fileSha256Hash, logs} — insert a CSAM case after the
   * Worker has archived the evidence (media + logs → evidence bucket). The
   * identified_by_npub is taken from the verified admin (ProxiedAdminMiddleware),
   * never the body. Idempotent: a case already on file for this hash is returned
   * as-is so a Workflow retry / re-report never creates a duplicate.
   */
  $group->post('/record-case', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);
    $hash = trim((string) ($body['fileSha256Hash'] ?? ''));
    $evidenceUrl = (string) ($body['evidenceLocationUrl'] ?? '');
    $logs = array_key_exists('logs', $body) && $body['logs'] !== null ? (string) $body['logs'] : null;
    if ($hash === '' || $evidenceUrl === '') {
      return aaError($response, 'Invalid parameters', 400);
    }

    $adminNpub = $request->getAttribute('admin_npub');
    $identifiedBy = is_string($adminNpub) && $adminNpub !== '' ? $adminNpub : 'unknown';

    $sel = $link->prepare('SELECT id FROM identified_csam_cases WHERE file_sha256_hash = ? ORDER BY id ASC LIMIT 1');
    $sel->bind_param('s', $hash);
    $sel->execute();
    $existing = $sel->get_result()->fetch_assoc();
    $sel->close();
    if ($existing !== null) {
      return aaJson($response, ['id' => (int) $existing['id'], 'existed' => true]);
    }

    $stmt = $link->prepare(
      'INSERT INTO identified_csam_cases (identified_by_npub, evidence_location_url, file_sha256_hash, logs) VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('ssss', $identifiedBy, $evidenceUrl, $hash, $logs);
    $stmt->execute();
    $newId = (int) $link->insert_id;
    $stmt->close();

    return aaJson($response, ['id' => $newId, 'existed' => false]);
  });

  /**
   * POST /accounts/admin/csam/record-submission
   * {incidentId, reportId, report} — the Worker records the NCMEC outcome
   * here after submitting (TEST_/numeric/ERROR semantics are computed Worker-
   * side). GUARD: a real (numeric) report id is never overwritten with a
   * different value — double-submission protection at the write layer.
   */
  $group->post('/record-submission', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);
    $incidentId = (int) ($body['incidentId'] ?? 0);
    $reportId = trim((string) ($body['reportId'] ?? ''));
    $report = $body['report'] ?? null;
    if ($incidentId <= 0 || $reportId === '' || strlen($reportId) > 64) {
      return aaError($response, 'Invalid parameters', 400);
    }

    $stmt = $link->prepare('SELECT ncmec_report_id FROM identified_csam_cases WHERE id = ?');
    $stmt->bind_param('i', $incidentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
      return aaError($response, 'Case not found', 404);
    }
    $existing = $row['ncmec_report_id'] !== null ? (string) $row['ncmec_report_id'] : null;
    if ($existing !== null && is_numeric($existing) && $existing !== $reportId) {
      return aaError($response, 'Case already has a submitted NCMEC report id', 409);
    }

    $reportJson = $report !== null ? json_encode($report) : null;
    $stmt = $link->prepare('UPDATE identified_csam_cases SET ncmec_report_id = ?, ncmec_submitted_report = ? WHERE id = ?');
    $stmt->bind_param('ssi', $reportId, $reportJson, $incidentId);
    $stmt->execute();
    $stmt->close();

    return aaJson($response, ['success' => true, 'incidentId' => $incidentId, 'reportId' => $reportId]);
  });

  /**
   * POST /accounts/admin/csam/unblacklist  {incidentId, npub, incidentTime}
   * PhotoDNA false-match path. Deliberately SLIM (DB + blossom only): the
   * WORKER derives npub/incidentTime from the case logs (the same parsing the
   * NCMEC report build uses) and passes them in; PHP removes the blacklist
   * rows written around the incident time, unbans from blossom, and marks the
   * case FALSE_MATCH. Mirrors NCMECReportHandler::unBlacklistUser without
   * dragging the NCMEC client into this path.
   */
  $group->post('/unblacklist', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) return aaError($response, 'invalid-body', 400);
    $incidentId = (int) ($body['incidentId'] ?? 0);
    $npub = aaValidNpub($body['npub'] ?? null);
    $incidentTime = trim((string) ($body['incidentTime'] ?? ''));
    if ($incidentId <= 0 || $npub === null || $incidentTime === '' || strtotime($incidentTime) === false) {
      return aaError($response, 'Invalid parameters', 400);
    }

    // Belt: never clear a case that already has a real (numeric) report id.
    $stmt = $link->prepare('SELECT ncmec_report_id FROM identified_csam_cases WHERE id = ?');
    $stmt->bind_param('i', $incidentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row === null) {
      return aaError($response, 'Case not found', 404);
    }
    if ($row['ncmec_report_id'] !== null && is_numeric((string) $row['ncmec_report_id'])) {
      return aaError($response, 'Case already has a submitted NCMEC report id', 409);
    }

    try {
      $reason = 'PhotoDNA CSAM Match API Match';
      $timestamp = date('Y-m-d H:i:s', strtotime($incidentTime));
      $timestampStart = date('Y-m-d H:i:s', strtotime($timestamp . ' -10 minutes'));
      $timestampEnd = date('Y-m-d H:i:s', strtotime($timestamp . ' +10 minutes'));

      $affectedRows = (new LegacyBlacklist($link))
        ->removeByNpubReasonInWindow($npub, $reason, $timestampStart, $timestampEnd);

      $blossomAPI = new BlossomFrontEndAPI($_SERVER['BLOSSOM_API_URL'], $_SERVER['BLOSSOM_API_KEY']);
      $blossomAPI->unbanUser($npub);

      // Mark the case FALSE_MATCH even when no blacklist row was in range
      // (same semantics as the legacy handler).
      $stmt = $link->prepare("UPDATE identified_csam_cases SET ncmec_report_id = 'FALSE_MATCH', ncmec_submitted_report = '{}' WHERE id = ?");
      $stmt->bind_param('i', $incidentId);
      $stmt->execute();
      $stmt->close();

      return aaJson($response, [
        'success' => true,
        'incidentId' => $incidentId,
        'removed' => (int) $affectedRows,
      ]);
    } catch (\Throwable $e) {
      error_log('aa csam unblacklist error: ' . $e->getMessage());
      return aaError($response, 'Error unblacklisting user', 500);
    }
  });

  /**
   * GET /accounts/admin/csam/unsubmitted?days=
   * Unsubmitted case ids from the past N days (SQL mirrors the legacy
   * endpoint, sentinels included). The CsamReportWorkflow enumerates here.
   */
  $group->get('/unsubmitted', function (Request $request, Response $response) {
    global $link;
    $params = $request->getQueryParams();
    $days = isset($params['days']) ? min(90, max(1, intval($params['days']))) : 7;

    $sql = "SELECT id FROM identified_csam_cases
            WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND (ncmec_report_id IS NULL
                   OR ncmec_report_id LIKE 'TEST_%'
                   OR ncmec_report_id = 'Null: Technical Error')
            ORDER BY timestamp ASC";
    $stmt = $link->prepare($sql);
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
      $ids[] = (int) $row['id'];
    }
    $stmt->close();

    return aaJson($response, ['ids' => $ids, 'count' => count($ids), 'days' => $days]);
  });

  /**
   * GET /accounts/admin/csam/offender-uploads/{caseId}
   * Mirrors the legacy endpoint: delete-eligible check + offender npub
   * extraction + the SQL anonymous-upload guards.
   */
  $group->get('/offender-uploads/{caseId:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $caseId = (int) $args['caseId'];

    $stmt = $link->prepare('SELECT logs, ncmec_report_id FROM identified_csam_cases WHERE id = ?');
    $stmt->bind_param('i', $caseId);
    $stmt->execute();
    $caseRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($caseRow === null) {
      return aaError($response, 'Case not found', 404);
    }

    $reportId = (string) ($caseRow['ncmec_report_id'] ?? '');
    if (!csamCaseAllowsOffenderCleanup($reportId)) {
      return aaError($response, 'Case is not in a delete-eligible state (need numeric NCMEC report id or EVIDENCE_EXPIRED).', 422);
    }

    $npub = extractOffenderNpubFromLogs($caseRow['logs']);
    if ($npub === null || !isLikelyValidNpub($npub)) {
      return aaError($response, 'Unable to determine offender npub from case logs.', 422);
    }

    $stmt = $link->prepare(
      "SELECT id, filename, type, approval_status
         FROM uploads_data
        WHERE usernpub = ?
          AND usernpub <> ''
          AND usernpub IS NOT NULL
          AND approval_status NOT IN ('rejected', 'csam')
        ORDER BY upload_date DESC"
    );
    $stmt->bind_param('s', $npub);
    $stmt->execute();
    $result = $stmt->get_result();
    $uploads = [];
    while ($r = $result->fetch_assoc()) {
      $uploads[] = [
        'id' => (int) $r['id'],
        'filename' => (string) $r['filename'],
        'type' => (string) $r['type'],
        'approval_status' => (string) $r['approval_status'],
      ];
    }
    $stmt->close();

    return aaJson($response, [
      'caseId' => $caseId,
      'reportId' => $reportId,
      'npub' => $npub,
      'count' => count($uploads),
      'uploads' => $uploads,
    ]);
  });

  /**
   * GET /accounts/admin/csam/submitted-offenders?days=
   * Mirrors the legacy endpoint (numeric-report-id final guard + npub
   * re-validation + single grouped SELECT).
   */
  $group->get('/submitted-offenders', function (Request $request, Response $response) {
    global $link;
    $params = $request->getQueryParams();
    $days = isset($params['days']) ? min(90, max(1, (int) $params['days'])) : 7;

    $stmt = $link->prepare(
      "SELECT id, logs, ncmec_report_id
         FROM identified_csam_cases
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
          AND ncmec_report_id IS NOT NULL
          AND ncmec_report_id NOT LIKE 'TEST_%'
          AND ncmec_report_id <> 'FALSE_MATCH'
          AND ncmec_report_id <> 'Null: Technical Error'
        ORDER BY timestamp ASC"
    );
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();

    $offenderMap = [];
    while ($r = $result->fetch_assoc()) {
      if (!is_numeric((string) $r['ncmec_report_id'])) continue;
      $npub = extractOffenderNpubFromLogs($r['logs']);
      if ($npub === null) continue;
      if (!isset($offenderMap[$npub])) {
        $offenderMap[$npub] = ['case_ids' => [], 'report_ids' => []];
      }
      $offenderMap[$npub]['case_ids'][]   = (int) $r['id'];
      $offenderMap[$npub]['report_ids'][] = (string) $r['ncmec_report_id'];
    }
    $stmt->close();

    $offenders = [];
    $totalUploads = 0;
    if ($offenderMap !== []) {
      $npubs = array_values(array_filter(array_keys($offenderMap), 'isLikelyValidNpub'));

      $uploadsByNpub = [];
      if ($npubs !== []) {
        $placeholders = implode(',', array_fill(0, count($npubs), '?'));
        $listStmt = $link->prepare(
          "SELECT id, filename, type, usernpub
             FROM uploads_data
            WHERE usernpub IN ($placeholders)
              AND usernpub <> ''
              AND usernpub IS NOT NULL
              AND approval_status NOT IN ('rejected', 'csam')
            ORDER BY upload_date DESC"
        );
        $listStmt->bind_param(str_repeat('s', count($npubs)), ...$npubs);
        $listStmt->execute();
        $rs = $listStmt->get_result();
        while ($u = $rs->fetch_assoc()) {
          $uploadsByNpub[(string) $u['usernpub']][] = [
            'id' => (int) $u['id'],
            'filename' => (string) $u['filename'],
            'type' => (string) $u['type'],
          ];
        }
        $rs->free();
        $listStmt->close();
      }

      foreach ($offenderMap as $npub => $info) {
        if (!isLikelyValidNpub($npub)) continue;
        $uploads = $uploadsByNpub[$npub] ?? [];
        $totalUploads += count($uploads);
        $offenders[] = [
          'npub' => $npub,
          'case_ids' => $info['case_ids'],
          'report_ids' => $info['report_ids'],
          'uploads' => $uploads,
          'remaining_count' => count($uploads),
        ];
      }
    }

    return aaJson($response, [
      'days' => $days,
      'total_offenders' => count($offenders),
      'total_uploads' => $totalUploads,
      'offenders' => $offenders,
    ]);
  });

})
  ->add(new ProxiedAdminMiddleware())
  ->add(new HmacAuthMiddleware());

// ----- Uploads Moderation group ----------------------------------------------
// Worker-proxied backend for the "Uploads Moderation" admin tool (the
// approve.php port). Mounted at /api/v2/accounts/admin/moderation/*; same auth
// stack (HMAC + ProxiedAdmin → admin-only, fresh acctlevel=99 DB read).
//
// THIN SQL ONLY. The byte-work for destructive actions (S3/R2/E2 deletion,
// blossom ban fan-out, CDN purge, CSAM evidence) now runs in the Worker; these
// routes are pure DB reads/writes (rows / ban-purge-page / delete-rows /
// blacklist-add / adult-batch / status=approved|adult). The old composite
// engine helpers (rejectUploadsByIds, deleteAndRejectUpload, processCsamReport,
// …) were removed together with the legacy session admin (routes_admin.php) and
// the account/admin/*.php pages; the byte-work now runs in the Worker.
//
// NOTE: unlike the old /admin/moderation/* routes, the per-action
// `new Permission()` session checks are intentionally absent — the PHP session
// is empty on HMAC-proxied calls; ProxiedAdminMiddleware is the canonical gate
// for every action here, including ban/csam (admin-only by construction).

$app->group('/accounts/admin/moderation', function (RouteCollectorProxy $group) {

  /**
   * GET /accounts/admin/moderation/queue
   * JSON reader for the moderation queue (approve.php rendered this list
   * server-side; the app needs rows). Modes, mirroring approve.php exactly:
   *   (default)         pending uploads, paginated (?page=, ?limit= ≤204)
   *   ?filename=<pfx>   filename prefix search, ANY status, capped 500
   *   ?npub=<npub>      ALL of one npub's media (any status), capped 500 —
   *                     the view that unlocks the bulk actions in the UI
   * Rows carry computed url/thumb so the app doesn't duplicate SiteConfig.
   */
  $group->get('/queue', function (Request $request, Response $response) {
    global $link;
    $q = $request->getQueryParams();
    $searchFile = isset($q['filename']) ? trim((string) $q['filename']) : '';
    $searchNpub = isset($q['npub']) ? trim((string) $q['npub']) : '';
    $SEARCH_LIMIT = 500;

    $total = null;
    if ($searchFile !== '') {
      if (strlen($searchFile) > 128 || str_contains($searchFile, '%') || str_contains($searchFile, '_')) {
        return aaError($response, 'Invalid filename prefix', 400);
      }
      $stmt = $link->prepare("SELECT id, filename, type, usernpub, approval_status FROM uploads_data WHERE filename LIKE ? ORDER BY upload_date DESC LIMIT ?");
      $like = $searchFile . '%';
      $stmt->bind_param('si', $like, $SEARCH_LIMIT);
    } elseif ($searchNpub !== '') {
      if (aaValidNpub($searchNpub) === null) {
        return aaError($response, 'Invalid npub', 400);
      }
      $stmt = $link->prepare("SELECT id, filename, type, usernpub, approval_status FROM uploads_data WHERE usernpub = ? ORDER BY upload_date DESC LIMIT ?");
      $stmt->bind_param('si', $searchNpub, $SEARCH_LIMIT);
    } else {
      $limit = max(1, min(204, (int) ($q['limit'] ?? 60)));
      $page  = max(0, (int) ($q['page'] ?? 0));
      $start = $page * $limit;
      // Group each uploader's pending media together (the hover-highlight then
      // lights the whole group); newest-first within a group. idx_optimization
      // (approval_status, …) filters to the pending set first — which is
      // AI-pre-filtered and small (~hundreds of rows) — so the usernpub/date
      // filesort over it is negligible. Deliberately NO (…,usernpub,…) index:
      // not worth the write cost on this 3M-row, write-hot table to save a
      // sub-ms sort over a tiny set.
      $stmt = $link->prepare(
        "SELECT id, filename, type, usernpub, approval_status
           FROM uploads_data
          WHERE approval_status = 'pending'
          ORDER BY usernpub, upload_date DESC
          LIMIT ?, ?"
      );
      $stmt->bind_param('ii', $start, $limit);
      $cnt = $link->query("SELECT COUNT(*) AS c FROM uploads_data WHERE approval_status = 'pending'");
      $total = (int) (($cnt ? $cnt->fetch_assoc() : null)['c'] ?? 0);
    }

    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($r = $rs->fetch_assoc()) {
      $type = (string) $r['type'];
      $filename = (string) $r['filename'];
      // URL mapping mirrors approve.php; unknown types are skipped there too.
      [$urlBase, $thumbBase, $media] = match ($type) {
        'picture' => [SiteConfig::getFullyQualifiedUrl('image'), SiteConfig::getThumbnailUrl('image'), 'image'],
        'profile' => [SiteConfig::getFullyQualifiedUrl('profile_picture'), SiteConfig::getThumbnailUrl('profile_picture'), 'image'],
        'video'   => [SiteConfig::getFullyQualifiedUrl('video'), SiteConfig::getThumbnailUrl('video'), 'video'],
        default   => [null, null, null],
      };
      if ($urlBase === null) {
        continue;
      }
      $rows[] = [
        'id' => (int) $r['id'],
        'filename' => $filename,
        'type' => $type,
        'media' => $media,
        'usernpub' => (string) $r['usernpub'],
        'approval_status' => (string) $r['approval_status'],
        'url' => $urlBase . $filename,
        'thumb' => $thumbBase . $filename,
      ];
    }
    $stmt->close();

    return aaJson($response, ['rows' => $rows, 'total' => $total, 'count' => count($rows)]);
  });

  /**
   * GET /accounts/admin/moderation/upload-info/{id}
   * Thin DB read: filename/type/npub for one upload. The Worker uses it to
   * derive the sha256 prefix, then reads the R2 upload logs ITSELF (app-side
   * S3 API) and extracts IP candidates there — R2 log fetching deliberately
   * does not happen in PHP on this path.
   */
  $group->get('/upload-info/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];

    $stmt = $link->prepare('SELECT filename, type, usernpub FROM uploads_data WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null) {
      return aaError($response, 'Upload not found', 404);
    }

    return aaJson($response, [
      'uploadId' => $id,
      'filename' => (string) ($row['filename'] ?? ''),
      'type' => (string) ($row['type'] ?? ''),
      'usernpub' => (string) ($row['usernpub'] ?? ''),
    ]);
  });

  /**
   * POST /accounts/admin/moderation/status  {id, status}
   * THIN reclassification only — approved | adult (a plain UPDATE). The
   * destructive actions (rejected | ban | csam) are now orchestrated in the
   * Worker (S3/R2/E2 deletion, blossom ban fan-out, CDN purge, CSAM evidence)
   * and persist via the thin DB endpoints below (/rows, /delete-rows,
   * /blacklist-add, /csam/record-case). They no longer pass through this route.
   */
  $group->post('/status', function (Request $request, Response $response) {
    global $link;

    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) {
      return aaError($response, 'invalid-body', 400);
    }
    $id = (int) ($body['id'] ?? 0);
    $status = (string) ($body['status'] ?? '');
    if ($id <= 0 || !in_array($status, ['approved', 'adult'], true)) {
      return aaError($response, 'Invalid parameters (thin status accepts approved|adult only)', 400);
    }

    $stmt = $link->prepare("UPDATE uploads_data SET approval_status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    $stmt->close();

    return aaJson($response, ['success' => true, 'id' => $id, 'status' => $status]);
  });

  /**
   * POST /accounts/admin/moderation/rows  {ids:int[]}
   * Thin read: the (filename, type, blossom_hash, usernpub) the Worker needs to
   * locate + ban the bytes before tearing an upload down. Missing ids are
   * simply absent from the response (the Worker treats them as already-gone).
   */
  $group->post('/rows', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['ids']) || !is_array($body['ids'])) {
      return aaError($response, 'Invalid payload, expecting an "ids" array', 400);
    }
    $ids = array_values(array_unique(array_filter(
      array_map('intval', $body['ids']),
      fn (int $i): bool => $i > 0,
    )));
    if ($ids === []) {
      return aaJson($response, ['rows' => []]);
    }
    if (count($ids) > 5000) {
      return aaError($response, 'Too many IDs, max 5000 per request', 400);
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $link->prepare(
      "SELECT id, filename, type, blossom_hash, usernpub FROM uploads_data WHERE id IN ($placeholders)"
    );
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($r = $rs->fetch_assoc()) {
      $rows[] = [
        'id' => (int) $r['id'],
        'filename' => (string) $r['filename'],
        'type' => (string) ($r['type'] ?? ''),
        'blossom_hash' => $r['blossom_hash'] !== null ? (string) $r['blossom_hash'] : null,
        'usernpub' => $r['usernpub'] !== null ? (string) $r['usernpub'] : null,
      ];
    }
    $rs->free();
    $stmt->close();
    return aaJson($response, ['rows' => $rows]);
  });

  /**
   * POST /accounts/admin/moderation/ban-purge-page  {npub, after_id, limit}
   * Thin keyset read of ONE page of an npub's uploads (read-only — the ban
   * writes + media teardown happen in the Worker). Keeps the anonymous-sweep
   * SQL guard as defense-in-depth behind the npub-format check.
   */
  $group->post('/ban-purge-page', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body)) {
      return aaError($response, 'invalid-body', 400);
    }
    $npub    = trim((string) ($body['npub'] ?? ''));
    $afterId = (int) ($body['after_id'] ?? 0);
    $limit   = max(1, min(50, (int) ($body['limit'] ?? 30)));
    if (!preg_match('/^npub1[a-z0-9]{20,90}$/', $npub)) {
      return aaError($response, 'Invalid npub', 400);
    }

    $total = null;
    if ($afterId === 0) {
      $cStmt = $link->prepare("SELECT COUNT(*) AS c FROM uploads_data WHERE usernpub = ?");
      $cStmt->bind_param('s', $npub);
      $cStmt->execute();
      $total = (int) ($cStmt->get_result()->fetch_assoc()['c'] ?? 0);
      $cStmt->close();
    }

    // Keyset on id ASC: deleted rows fall out; failures keep id <= cursor so the
    // next page (id > cursor) skips them — no infinite loop.
    $stmt = $link->prepare(
      "SELECT id, filename, type, blossom_hash FROM uploads_data
        WHERE usernpub = ? AND usernpub <> '' AND usernpub IS NOT NULL AND id > ?
        ORDER BY id ASC LIMIT ?"
    );
    $stmt->bind_param('sii', $npub, $afterId, $limit);
    $stmt->execute();
    $rs = $stmt->get_result();
    $rows = [];
    while ($r = $rs->fetch_assoc()) {
      $rows[] = [
        'id' => (int) $r['id'],
        'filename' => (string) $r['filename'],
        'type' => (string) ($r['type'] ?? ''),
        'blossom_hash' => $r['blossom_hash'] !== null ? (string) $r['blossom_hash'] : null,
        'usernpub' => $npub,
      ];
    }
    $stmt->close();

    $cursor = $rows === [] ? $afterId : max(array_column($rows, 'id'));
    return aaJson($response, [
      'total'  => $total,
      'rows'   => $rows,
      'cursor' => $cursor,
      'more'   => count($rows) === $limit,
    ]);
  });

  /**
   * POST /accounts/admin/moderation/delete-rows  {rows:[{id,filename,type}]}
   * Thin DB-last write: record each filename in rejected_files (idempotent —
   * insert only if absent, no unique key on the table) then DELETE the
   * uploads_data rows. The caller has already removed the bytes, so this is the
   * final step of S3-delete-first / DB-row-last.
   */
  $group->post('/delete-rows', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['rows']) || !is_array($body['rows'])) {
      return aaError($response, 'Invalid payload, expecting a "rows" array', 400);
    }
    $rows = [];
    foreach ($body['rows'] as $r) {
      if (!is_array($r)) continue;
      $id = (int) ($r['id'] ?? 0);
      $filename = (string) ($r['filename'] ?? '');
      $type = (string) ($r['type'] ?? '');
      if ($id > 0 && $filename !== '') {
        $rows[] = ['id' => $id, 'filename' => $filename, 'type' => $type];
      }
    }
    if ($rows === []) {
      return aaJson($response, ['success' => true, 'deleted' => 0]);
    }
    if (count($rows) > 5000) {
      return aaError($response, 'Too many rows, max 5000 per request', 400);
    }

    // rejected_files: insert each filename only if not already present (the
    // table has no unique key; this keeps Workflow retries idempotent).
    $insStmt = $link->prepare(
      "INSERT INTO rejected_files (filename, type)
         SELECT ?, ? FROM DUAL
          WHERE NOT EXISTS (SELECT 1 FROM rejected_files WHERE filename = ?)"
    );
    foreach ($rows as $r) {
      try {
        $insStmt->bind_param('sss', $r['filename'], $r['type'], $r['filename']);
        $insStmt->execute();
      } catch (\Throwable $e) {
        error_log('aa delete-rows rejected_files insert failed for ' . $r['filename'] . ': ' . $e->getMessage());
      }
    }
    $insStmt->close();

    // uploads_data: single batch DELETE (the critical write).
    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $delStmt = $link->prepare("DELETE FROM uploads_data WHERE id IN ($placeholders)");
    $delStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    if (!$delStmt->execute()) {
      $err = $delStmt->error;
      $delStmt->close();
      error_log('aa delete-rows uploads_data delete failed: ' . $err);
      return aaError($response, 'Database delete failed', 500);
    }
    $deleted = $delStmt->affected_rows;
    $delStmt->close();

    return aaJson($response, ['success' => true, 'deleted' => $deleted]);
  });

  /**
   * POST /accounts/admin/moderation/blacklist-add  {entries:[{npub,ip,ua,reason}]}
   * Thin npub/ip blacklist write for ban + CSAM offender bans. Deduped on
   * (npub, reason) so Workflow retries don't pile duplicate rows (no unique key
   * on the table). LegacyBlacklist::add does the normalization + ip validation.
   */
  $group->post('/blacklist-add', function (Request $request, Response $response) {
    global $link;
    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['entries']) || !is_array($body['entries'])) {
      return aaError($response, 'Invalid payload, expecting an "entries" array', 400);
    }
    if (count($body['entries']) > 5000) {
      return aaError($response, 'Too many entries, max 5000 per request', 400);
    }
    $bl = new LegacyBlacklist($link);
    $added = 0;
    foreach ($body['entries'] as $e) {
      if (!is_array($e)) continue;
      $npub = isset($e['npub']) ? (string) $e['npub'] : '';
      $ip = isset($e['ip']) && $e['ip'] !== null ? (string) $e['ip'] : null;
      $ua = isset($e['ua']) && $e['ua'] !== null ? (string) $e['ua'] : null;
      $reason = isset($e['reason']) ? (string) $e['reason'] : 'BANNED';

      if ($npub !== '') {
        $dStmt = $link->prepare('SELECT 1 FROM blacklist WHERE npub = ? AND reason = ? LIMIT 1');
        $dStmt->bind_param('ss', $npub, $reason);
        $dStmt->execute();
        $exists = $dStmt->get_result()->fetch_assoc() !== null;
        $dStmt->close();
        if ($exists) {
          continue;
        }
      }
      try {
        $bl->add($npub, $ip, $ua, $reason);
        $added++;
      } catch (\Throwable $ex) {
        error_log('aa blacklist-add failed for ' . $npub . ': ' . $ex->getMessage());
      }
    }
    return aaJson($response, ['success' => true, 'added' => $added]);
  });

  /**
   * POST /accounts/admin/moderation/approve-all  {ids: [...]} (≤1000)
   * Bulk approve — single UPDATE, pending rows only.
   */
  $group->post('/approve-all', function (Request $request, Response $response) {
    global $link;

    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['ids']) || !is_array($body['ids'])) {
      return aaError($response, 'Invalid payload, expecting an "ids" array', 400);
    }

    $ids = array_values(array_filter(array_map('intval', $body['ids']), fn ($id) => $id > 0));
    if ($ids === []) {
      return aaError($response, 'No valid IDs provided', 400);
    }
    if (count($ids) > 1000) {
      return aaError($response, 'Too many IDs, max 1000 per request', 400);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "UPDATE uploads_data SET approval_status='approved' WHERE id IN ($placeholders) AND approval_status='pending'";
    $stmt = $link->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

    if (!$stmt->execute()) {
      $error = $stmt->error;
      $stmt->close();
      error_log('aa moderation approve-all error: ' . $error);
      return aaError($response, 'Database error', 500);
    }
    $count = $stmt->affected_rows;
    $stmt->close();

    return aaJson($response, ['success' => true, 'count' => $count]);
  });

  /**
   * POST /accounts/admin/moderation/adult-batch  {ids: [...]} (≤1000)
   * Bulk mark-as-adult — single UPDATE, any current status (mirrors the legacy
   * "Mark All as Adult" per-id loop, consolidated). The ModerationWorkflow
   * drives this in chunks for the npub-view bulk action.
   */
  $group->post('/adult-batch', function (Request $request, Response $response) {
    global $link;

    $body = json_decode((string) $request->getBody(), true);
    if (!is_array($body) || !isset($body['ids']) || !is_array($body['ids'])) {
      return aaError($response, 'Invalid payload, expecting an "ids" array', 400);
    }

    $ids = array_values(array_filter(array_map('intval', $body['ids']), fn ($id) => $id > 0));
    if ($ids === []) {
      return aaError($response, 'No valid IDs provided', 400);
    }
    if (count($ids) > 1000) {
      return aaError($response, 'Too many IDs, max 1000 per request', 400);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $link->prepare("UPDATE uploads_data SET approval_status='adult' WHERE id IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);

    if (!$stmt->execute()) {
      $error = $stmt->error;
      $stmt->close();
      error_log('aa moderation adult-batch error: ' . $error);
      return aaError($response, 'Database error', 500);
    }
    $count = $stmt->affected_rows;
    $stmt->close();

    return aaJson($response, ['success' => true, 'count' => $count]);
  });

})
  ->add(new ProxiedAdminMiddleware())
  ->add(new HmacAuthMiddleware());

// ----- Account Review -------------------------------------------------------
// Report-driven examination of a PAID account. Reads the SUBSCRIBED-user media
// table users_images (NOT uploads_data, which is the free-upload moderation
// queue). No approve/reject concepts here — this is forensic confirmation of a
// report, by reported URL or by identifier.

/**
 * Build one account-review media row from a users_images record (keys:
 * id, image, mime_type, file_size, created_at). Mirrors the dashboard's
 * buildFileListEntry: type is extension-derived (mime first-segment fallback),
 * url from the per-type CDN base, thumb for images only — the client derives
 * video posters + other-type icons.
 */
function aaReviewMediaRow(array $r): array
{
  $filename = pathinfo((string) $r['image'], PATHINFO_BASENAME);
  $type = getFileTypeFromName((string) $r['image']);
  if ($type === 'unknown') {
    $type = explode('/', (string) $r['mime_type'])[0];
  }
  $ptype = 'professional_account_' . $type;
  try {
    $url   = SiteConfig::getFullyQualifiedUrl($ptype) . $filename;
    $thumb = $type === 'image' ? (SiteConfig::getThumbnailUrl($ptype) . $filename) : null;
  } catch (\Throwable $e) {
    // Unknown CDN mapping — fall back to the public path so the row still
    // resolves rather than vanishing from the review.
    $url   = SiteConfig::ACCESS_SCHEME . '://' . SiteConfig::DOMAIN_NAME . '/p/' . $filename;
    $thumb = null;
  }
  return [
    'id'        => (int) $r['id'],
    'filename'  => $filename,
    'mediaType' => $type,
    'mime'      => (string) $r['mime_type'],
    'url'       => $url,
    'thumb'     => $thumb,
    'size'      => (int) ($r['file_size'] ?? 0),
    'createdAt' => $r['created_at'],
  ];
}

$app->group('/accounts/admin/account-review', function (RouteCollectorProxy $group) {

  /**
   * GET /accounts/admin/account-review/resolve?q=<url|npub|uuid>
   * Turn a report into the owning account. npub/uuid resolve directly; a URL
   * is matched to its users_images row by filename basename. Returns the full
   * account snapshot (real latest ban reason, not the hardcoded one) and, for
   * a URL, the matched item id to highlight in the gallery.
   */
  $group->get('/resolve', function (Request $request, Response $response) {
    global $link;
    $q = trim((string) ($request->getQueryParams()['q'] ?? ''));
    if ($q === '' || strlen($q) > 2048) return aaError($response, 'invalid-query', 400);

    $matchedItemId = null;
    $matchedItem = null;
    $npub = null;

    if (str_starts_with($q, 'npub1')) {
      $npub = aaValidNpub($q);
      if ($npub === null) return aaError($response, 'invalid-query', 400);
    } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $q)) {
      $acct = Account::fromUuid($q, $link);
      if ($acct === null || !$acct->accountExists()) return aaError($response, 'not-found', 404);
      $npub = $acct->getNpub();
    } elseif (str_contains($q, '://')) {
      // Reported URL → filename basename → users_images row. `image` stores the
      // bare filename (the dashboard builds URLs as base . image), so an exact
      // match rides its index instead of a basename scan.
      $path = parse_url($q, PHP_URL_PATH);
      $basename = is_string($path) ? pathinfo($path, PATHINFO_BASENAME) : '';
      if ($basename === '') return aaError($response, 'invalid-query', 400);
      $stmt = $link->prepare("SELECT id, usernpub, image, mime_type, file_size, created_at FROM users_images WHERE image = ? ORDER BY id DESC LIMIT 1");
      $stmt->bind_param('s', $basename);
      $stmt->execute();
      $hit = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if (!$hit) {
        return aaJson($response, ['found' => false, 'note' => 'No paid-account media matches that URL.']);
      }
      $npub = (string) $hit['usernpub'];
      $matchedItemId = (int) $hit['id'];
      // The matched media row, so the URL-match view renders it without a
      // separate /media fetch.
      $matchedItem = aaReviewMediaRow($hit);
    } else {
      return aaError($response, 'invalid-query', 400);
    }

    $account = new Account($npub, $link);
    if (!$account->accountExists()) return aaError($response, 'not-found', 404);

    $snap = aaUserSnapshot($account, $link);
    // Surface the REAL latest blacklist reason (the snapshot hardcodes one).
    if (!empty($snap['banned'])) {
      $bs = $link->prepare("SELECT reason FROM blacklist WHERE npub = ? ORDER BY id DESC LIMIT 1");
      $bs->bind_param('s', $npub);
      $bs->execute();
      $br = $bs->get_result()->fetch_assoc();
      $bs->close();
      if ($br && $br['reason'] !== null) $snap['banReason'] = (string) $br['reason'];
    }

    return aaJson($response, ['found' => true, 'matchedItemId' => $matchedItemId, 'item' => $matchedItem, 'account' => $snap]);
  });

  /**
   * GET /accounts/admin/account-review/media?npub=&cursor=&limit=
   * ALL of the account's media from users_images across all folders (image,
   * video, audio, document, archive, text, other), keyset-paginated newest
   * first. url/thumb/type mirror the dashboard's buildFileListEntry: type is
   * extension-derived (getFileTypeFromName), thumb is set for images only —
   * the client derives video posters (url/poster.jpg) and renders icons for
   * the rest.
   */
  $group->get('/media', function (Request $request, Response $response) {
    global $link;
    $p = $request->getQueryParams();
    $npub = aaValidNpub($p['npub'] ?? null);
    if ($npub === null) return aaError($response, 'invalid-npub', 400);
    $limit  = max(1, min(200, (int) ($p['limit'] ?? 60)));
    $cursor = max(0, (int) ($p['cursor'] ?? 0));

    $where = "WHERE ui.usernpub = ?";
    $types = 's';
    $args  = [$npub];
    if ($cursor > 0) {
      $where .= " AND ui.id < ?";
      $types .= 'i';
      $args[] = $cursor;
    }

    // Optional filters (all keyset-safe: created_at + usernpub are indexed,
    // the rest narrow within one user's rows). Type maps to the SAME extension
    // lists getFileType() uses, matching the gallery's own categorisation —
    // mime_type is unreliable here (defaults to application/octet-stream).
    $type = strtolower(trim((string) ($p['type'] ?? '')));
    if ($type !== '' && $type !== 'all') {
      $exts = getFileTypeExtensions($type);
      if ($exts) {
        $likes = [];
        foreach ($exts as $ext) {
          $likes[] = "LOWER(ui.image) LIKE ?";
          $types  .= 's';
          $args[]  = '%.' . strtolower($ext);
        }
        $where .= " AND (" . implode(' OR ', $likes) . ")";
      }
    }
    $from = trim((string) ($p['from'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
      $where .= " AND ui.created_at >= ?";
      $types .= 's';
      $args[] = $from . ' 00:00:00';
    }
    $to = trim((string) ($p['to'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
      $where .= " AND ui.created_at <= ?";
      $types .= 's';
      $args[] = $to . ' 23:59:59';
    }
    $minSize = max(0, (int) ($p['minSize'] ?? 0));
    if ($minSize > 0) {
      $where .= " AND ui.file_size >= ?";
      $types .= 'i';
      $args[] = $minSize;
    }
    $maxSize = max(0, (int) ($p['maxSize'] ?? 0));
    if ($maxSize > 0) {
      $where .= " AND ui.file_size <= ?";
      $types .= 'i';
      $args[] = $maxSize;
    }
    $fname = trim((string) ($p['filename'] ?? ''));
    if ($fname !== '') {
      // Escape LIKE wildcards in the user's term so it matches literally.
      $where .= " AND ui.image LIKE ?";
      $types .= 's';
      $args[] = '%' . addcslashes($fname, '%_\\') . '%';
    }

    $sql = "SELECT ui.id, ui.image, ui.mime_type, ui.file_size, ui.created_at
              FROM users_images ui
              $where
             ORDER BY ui.id DESC
             LIMIT ?";
    $types .= 'i';
    $args[] = $limit;

    $stmt = $link->prepare($sql);
    $stmt->bind_param($types, ...$args);
    $stmt->execute();
    $rs = $stmt->get_result();

    $rows = [];
    while ($r = $rs->fetch_assoc()) {
      $rows[] = aaReviewMediaRow($r);
    }
    $stmt->close();

    $nextCursor = count($rows) === $limit ? (int) $rows[count($rows) - 1]['id'] : null;
    return aaJson($response, ['rows' => $rows, 'nextCursor' => $nextCursor]);
  });

  /**
   * GET /accounts/admin/account-review/upload-info/{id}
   * Thin read for the IP lookup: the image filename + npub for one users_images
   * row. The Worker derives the R2 log prefix from the filename stem and reads
   * the upload logs itself (same as the moderation path; R2 fetching stays out
   * of PHP).
   */
  $group->get('/upload-info/{id:[0-9]+}', function (Request $request, Response $response, array $args) {
    global $link;
    $id = (int) $args['id'];

    $stmt = $link->prepare('SELECT image, usernpub FROM users_images WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row === null) {
      return aaError($response, 'Media not found', 404);
    }

    return aaJson($response, [
      'uploadId' => $id,
      'filename' => pathinfo((string) ($row['image'] ?? ''), PATHINFO_BASENAME),
      'usernpub' => (string) ($row['usernpub'] ?? ''),
    ]);
  });

})
  ->add(new ProxiedAdminMiddleware())
  ->add(new HmacAuthMiddleware());
