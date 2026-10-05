<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/HmacAuthMiddleware.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Account.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/db/UploadsData.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/DeleteMedia.class.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/S3Service.class.php';

require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

// Worker-facing GIF contribution endpoints (HMAC-authed, NB_HMAC_SECRETS). The
// account.nostr.build GifContributionWorkflow copies a user's animated GIF/WebP
// from the paid bucket into the free `-img` bucket itself; PHP only answers
// which library files qualify, records the free uploads_data row, and takes a
// contribution back. Thin SQL on purpose: no uploads, no webhooks, no S3 here
// except through DeleteMedia on withdraw.
// Full paths: POST /api/v2/internal/gif-contributions/{candidates,register,withdraw}.

const GC_MIMES = ['image/gif', 'image/webp'];
const GC_EXT_MIME = ['gif' => 'image/gif', 'webp' => 'image/webp'];
const GC_PAGE_MAX = 100;
const GC_ROW_COLS = 'id, filename, approval_status, metadata, usernpub, user_uuid';

function gcJson(Response $response, int $status, array $body): Response
{
  $response->getBody()->write(json_encode($body));
  return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
}

function gcBody(Request $request): array
{
  $data = json_decode($request->getBody()->getContents(), true);
  return is_array($data) ? $data : [];
}

/**
 * The account a contribution acts for, or the refusal. A contributor can log
 * in, has a verified npub, is not banned and has no deletion pending (the app
 * checks the same; this is the authoritative copy at write time).
 *
 * @return array{0: ?Account, 1: ?array{int, string}}
 */
function gcAccount(mysqli $link, string $uuid): array
{
  $account = Account::fromUuid($uuid, $link);
  if ($account === null) return [null, [404, 'no-such-account']];
  if ($account->getNpub() === '') return [null, [409, 'no-npub']];
  if ((int)($account->getAccount()['npub_verified'] ?? 0) !== 1) return [null, [409, 'npub-not-verified']];
  if ($account->isBanned()) return [null, [403, 'banned']];
  if ($account->getDeletionStatus() !== 'none') return [null, [409, 'deletion-pending']];
  return [$account, null];
}

/** Every row of `$table` whose filename starts with one of `$hashes` (any
 *  extension; one query, index range scans), oldest first. */
function gcPrefixRows(mysqli $link, string $table, string $cols, array $hashes): array
{
  if (count($hashes) === 0) return [];
  $likes = implode(' OR ', array_fill(0, count($hashes), 'filename LIKE ?'));
  $patterns = array_map(fn($h) => $h . '%', $hashes);
  $q = $link->prepare("SELECT {$cols} FROM {$table} WHERE {$likes} ORDER BY id");
  $q->bind_param(str_repeat('s', count($patterns)), ...$patterns);
  $q->execute();
  $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
  $q->close();
  return $rows;
}

/** gcPrefixRows grouped by the 64-hex hash each filename starts with. */
function gcPrefixHits(mysqli $link, string $table, string $cols, array $hashes): array
{
  $hits = [];
  foreach (gcPrefixRows($link, $table, $cols, $hashes) as $row) {
    $hits[strtolower(substr((string)$row['filename'], 0, 64))][] = $row;
  }
  return $hits;
}

/** A free row this account contributed from library file `$imageId` (an
 *  earlier instance's insert, e.g. a reclaimed or retried contribution). Keyed
 *  by user_uuid, which /register writes and an npub rotation leaves intact. */
function gcIsOwnContribution(array $row, array $meta, int $imageId, string $uuid): bool
{
  return ($meta['source'] ?? null) === 'account-contribution'
    && (int)($meta['sourceImageId'] ?? 0) === $imageId
    && (string)($row['user_uuid'] ?? '') === $uuid;
}

function gcIsHash($v): bool
{
  return is_string($v) && preg_match('/^[0-9a-f]{64}$/', $v) === 1;
}

/** Whether one of a hash's LIVE rows is this account's contribution of this file. */
function gcHasOwnContribution(array $rows, int $imageId, string $uuid): bool
{
  foreach ($rows as $row) {
    if (!gcIsLive($row)) continue;
    $meta = json_decode((string)($row['metadata'] ?? ''), true);
    if (gcIsOwnContribution($row, is_array($meta) ? $meta : [], $imageId, $uuid)) return true;
  }
  return false;
}

/** A row that holds bytes: anything but a legacy approval_status 'rejected'
 *  row (today a rejection deletes the row and lists the hash in
 *  rejected_files; the upload manager writes back only approved / adult). */
function gcIsLive(array $row): bool
{
  return ($row['approval_status'] ?? '') !== 'rejected';
}

/** A copy moderation already rejected (the row stays as the record). */
function gcHasRejectedRow(array $rows): bool
{
  foreach ($rows as $row) {
    if (($row['approval_status'] ?? '') === 'rejected') return true;
  }
  return false;
}

/** Whether a free row is this contribution: the instance that inserted it, or
 *  an earlier instance of the same account and library file. */
function gcMine(array $row, string $contributionId, int $imageId, string $uuid): bool
{
  $meta = json_decode((string)($row['metadata'] ?? ''), true);
  $meta = is_array($meta) ? $meta : [];
  return (($meta['source'] ?? null) === 'account-contribution'
      && ($meta['contributionId'] ?? null) === $contributionId)
    || gcIsOwnContribution($row, $meta, $imageId, $uuid);
}

/** The register answer for an existing row. `atKey`: THIS row is named
 *  exactly `<stem>.<ext>`. `keyHasRow`: ANY live row is named so (the one fact
 *  the Worker deletes on), whichever row the answer is about. */
function gcExistsAnswer(array $row, bool $mine, bool $atKey, bool $keyHasRow): array
{
  return [
    'ok' => true,
    'status' => 'exists',
    'id' => (int)$row['id'],
    'filename' => (string)$row['filename'],
    'approvalStatus' => (string)$row['approval_status'],
    'mine' => $mine,
    'atKey' => $atKey,
    'keyHasRow' => $keyHasRow,
  ];
}

/**
 * The answer for a file already in the pool, or null when no row matches the
 * stem or current hash. In order: this account's own LIVE row wins (wherever
 * it is named; a retry must find its own live row); then a copy moderation
 * rejected refuses; then a live row at the exact key owns the object there;
 * then any other row means the file is in the pool elsewhere. Every answer
 * carries keyHasRow (a live row named exactly <stem>.<ext>), computed on its own.
 */
function gcRegisterAnswer(mysqli $link, array $hashes, string $filename, string $contributionId, int $imageId, string $uuid): ?array
{
  $rows = gcPrefixRows($link, 'uploads_data', GC_ROW_COLS, $hashes);
  if (count($rows) === 0) return null;
  $live = array_values(array_filter($rows, 'gcIsLive'));
  $atKey = array_values(array_filter($live, fn($r) => (string)$r['filename'] === $filename));
  $keyHasRow = count($atKey) > 0;
  $own = array_values(array_filter($live, fn($r) => gcMine($r, $contributionId, $imageId, $uuid)));
  if (count($own) > 0) {
    $ownAtKey = array_values(array_filter($own, fn($r) => (string)$r['filename'] === $filename));
    $row = $ownAtKey[0] ?? $own[0];
    gcRecordAttempt($link, (string)$row['filename'], (string)$row['usernpub']);
    return gcExistsAnswer($row, true, (string)$row['filename'] === $filename, $keyHasRow);
  }
  if (gcHasRejectedRow($rows)) {
    return ['ok' => true, 'status' => 'refused', 'reason' => 'rejected-row', 'keyHasRow' => $keyHasRow];
  }
  return gcExistsAnswer($atKey[0] ?? $live[0], false, $keyHasRow, $keyHasRow);
}

/** The upload_attempts row a free file needs (DeleteMedia finds it through
 *  it). Throws on failure, unlike UploadAttempts::recordUpload. */
function gcRecordAttempt(mysqli $link, string $filename, string $usernpub): void
{
  $hash = substr($filename, 0, 64);
  $q = $link->prepare(
    'INSERT INTO upload_attempts (filename, usernpub, attempt_timestamp) VALUES (?, ?, NOW())
     ON DUPLICATE KEY UPDATE attempt_timestamp = NOW()'
  );
  $q->bind_param('ss', $hash, $usernpub);
  $q->execute();
  $q->close();
}

$app->group('/internal/gif-contributions', function (RouteCollectorProxy $group) {
  // One page of the account's library files that may be contributed (gif/webp
  // by stored mime; the Worker checks the bytes). Keyset by id. For a file with
  // a known original hash, `inPool` / `rejected` let the Worker skip it without
  // reading its bytes.
  $group->post('/candidates', function (Request $request, Response $response) {
    global $link;
    try {
      $data = gcBody($request);
      $uuid = trim((string)($data['uuid'] ?? ''));
      $target = is_array($data['target'] ?? null) ? $data['target'] : [];
      $afterId = max(0, (int)($data['afterId'] ?? 0));
      $limit = min(GC_PAGE_MAX, max(1, (int)($data['limit'] ?? GC_PAGE_MAX)));
      $kind = (string)($target['kind'] ?? '');
      if ($uuid === '' || !in_array($kind, ['ids', 'folder', 'all'], true)) {
        return gcJson($response, 400, ['ok' => false, 'error' => 'bad-input']);
      }
      [$account, $refusal] = gcAccount($link, $uuid);
      if ($refusal !== null) return gcJson($response, $refusal[0], ['ok' => false, 'error' => $refusal[1]]);

      $sql = "SELECT id, image, mime_type, file_size, media_width, media_height, blurhash, sha256_hash
              FROM users_images
              WHERE user_uuid = ? AND mime_type IN ('image/gif', 'image/webp') AND id > ?";
      $types = 'si';
      $params = [$uuid, $afterId];
      if ($kind === 'ids') {
        $ids = array_values(array_unique(array_filter(
          array_map('intval', is_array($target['ids'] ?? null) ? $target['ids'] : []),
          fn($id) => $id > 0
        )));
        if (count($ids) === 0 || count($ids) > GC_PAGE_MAX) {
          return gcJson($response, 400, ['ok' => false, 'error' => 'bad-input']);
        }
        $sql .= ' AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $types .= str_repeat('i', count($ids));
        array_push($params, ...$ids);
      } elseif ($kind === 'folder') {
        $folderId = (int)($target['folderId'] ?? -1);
        if ($folderId < 0) return gcJson($response, 400, ['ok' => false, 'error' => 'bad-input']);
        if ($folderId === 0) {
          $sql .= ' AND folder_id IS NULL'; // Home
        } else {
          $sql .= ' AND folder_id = ?';
          $types .= 'i';
          $params[] = $folderId;
        }
      }
      $sql .= ' ORDER BY id LIMIT ?';
      $types .= 'i';
      $params[] = $limit;

      $stmt = $link->prepare($sql);
      $stmt->bind_param($types, ...$params);
      $stmt->execute();
      $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      // One prefix lookup per table for every known original hash on the page.
      $hashes = [];
      foreach ($rows as $r) {
        $h = strtolower((string)($r['sha256_hash'] ?? ''));
        if (gcIsHash($h)) $hashes[$h] = true;
      }
      $inPool = gcPrefixHits($link, 'uploads_data', GC_ROW_COLS, array_keys($hashes));
      $rejected = gcPrefixHits($link, 'rejected_files', 'filename', array_keys($hashes));

      $out = [];
      foreach ($rows as $r) {
        $h = strtolower((string)($r['sha256_hash'] ?? ''));
        $hash = gcIsHash($h) ? $h : null;
        // This account already contributed the file: /register answers with
        // its own row (even if a same-hash copy elsewhere was rejected).
        $own = $hash !== null && gcHasOwnContribution($inPool[$hash] ?? [], (int)$r['id'], $uuid);
        $out[] = [
          'id' => (int)$r['id'],
          'image' => (string)$r['image'],
          'mimeType' => (string)$r['mime_type'],
          'fileSize' => (int)$r['file_size'],
          'width' => $r['media_width'] !== null ? (int)$r['media_width'] : null,
          'height' => $r['media_height'] !== null ? (int)$r['media_height'] : null,
          'blurhash' => $r['blurhash'] !== null ? (string)$r['blurhash'] : null,
          'sha256Hash' => $hash,
          // A file this account already contributed goes to /register (which
          // answers with its own row); anyone else's copy is in the pool; a
          // copy moderation rejected, or a rejected hash, is refused.
          // Live rows only, the same rule /register uses (a legacy rejected
          // row alone means rejected, not in the pool).
          'inPool' => $hash !== null && !$own
            && count(array_filter($inPool[$hash] ?? [], 'gcIsLive')) > 0,
          'rejected' => $hash !== null && !$own
            && (isset($rejected[$hash]) || gcHasRejectedRow($inPool[$hash] ?? [])),
        ];
      }
      $next = count($rows) === $limit ? (int)end($rows)['id'] : null;
      return gcJson($response, 200, ['ok' => true, 'rows' => $out, 'nextAfterId' => $next]);
    } catch (\Throwable $e) {
      error_log('internal/gif-contributions/candidates error: ' . $e->getMessage());
      return gcJson($response, 500, ['ok' => false, 'error' => 'candidates failed']);
    }
  });

  // Record the free uploads_data row for an object the Worker already wrote to
  // the free bucket at <stem>.<ext>. Insert-if-absent: a raw INSERT, never
  // DatabaseTable::insert (an upsert that would reset approval_status to
  // pending and overwrite the owner). Every answer carries keyHasRow: whether
  // a live row is named exactly <stem>.<ext> (the only fact the Worker deletes
  // its object on).
  $group->post('/register', function (Request $request, Response $response) {
    global $link;
    try {
      $data = gcBody($request);
      $uuid = trim((string)($data['uuid'] ?? ''));
      $imageId = (int)($data['imageId'] ?? 0);
      $contributionId = (string)($data['contributionId'] ?? '');
      $stem = (string)($data['stem'] ?? '');
      $current = (string)($data['currentSha256'] ?? '');
      $ext = (string)($data['ext'] ?? '');
      $fileSize = (int)($data['fileSize'] ?? -1);
      if (
        $uuid === '' || $imageId <= 0 || !gcIsHash($stem) || !gcIsHash($current)
        || !isset(GC_EXT_MIME[$ext]) || $fileSize < 0
        || preg_match('/^gc-[A-Za-z0-9-]{1,125}$/', $contributionId) !== 1
      ) {
        return gcJson($response, 400, ['ok' => false, 'error' => 'bad-input']);
      }
      [$account, $refusal] = gcAccount($link, $uuid);
      if ($refusal !== null) return gcJson($response, $refusal[0], ['ok' => false, 'error' => $refusal[1]]);
      $npub = $account->getNpub();

      // Ownership, and the display facts the free row copies from the library row.
      $stmt = $link->prepare('SELECT media_width, media_height, blurhash FROM users_images WHERE id = ? AND user_uuid = ?');
      $stmt->bind_param('is', $imageId, $uuid);
      $stmt->execute();
      $src = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if (!is_array($src)) return gcJson($response, 404, ['ok' => false, 'error' => 'no-such-media']);

      $uploads = new UploadsData($link);
      $filename = $stem . '.' . $ext;
      $hashes = array_values(array_unique([$stem, $current]));

      $answer = gcRegisterAnswer($link, $hashes, $filename, $contributionId, $imageId, $uuid);
      if ($answer !== null) return gcJson($response, 200, $answer);
      // 3. Nothing in the pool: refuse a rejected hash. No row exists, so the
      //    Worker may remove the object it wrote.
      foreach ($hashes as $hash) {
        if ($uploads->checkRejected($hash)) {
          return gcJson($response, 200, ['ok' => true, 'status' => 'refused', 'reason' => 'rejected-hash', 'keyHasRow' => false]);
        }
      }

      $metadata = json_encode([
        'source' => 'account-contribution',
        'contributionId' => $contributionId,
        'sourceImageId' => $imageId,
      ]);
      $mime = GC_EXT_MIME[$ext];
      $width = $src['media_width'] !== null ? (int)$src['media_width'] : 0;
      $height = $src['media_height'] !== null ? (int)$src['media_height'] : 0;
      $blurhash = $src['blurhash'] !== null ? (string)$src['blurhash'] : null;
      try {
        // file_extension comes from the table's BEFORE INSERT trigger;
        // user_uuid is written here so ownership checks never depend on it.
        $ins = $link->prepare(
          "INSERT INTO uploads_data
             (filename, metadata, file_size, media_width, media_height, blurhash, usernpub, user_uuid, mime, type, approval_status)
           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'picture', 'pending')"
        );
        $ins->bind_param('ssiiissss', $filename, $metadata, $fileSize, $width, $height, $blurhash, $npub, $uuid, $mime);
        $ins->execute();
        $id = (int)$link->insert_id;
        $ins->close();
      } catch (\mysqli_sql_exception $e) {
        if ($e->getCode() !== 1062) throw $e;
        // Lost a race to a concurrent insert of the same name.
        $answer = gcRegisterAnswer($link, $hashes, $filename, $contributionId, $imageId, $uuid);
        if ($answer === null) throw $e;
        return gcJson($response, 200, $answer);
      }
      // What every free upload records (DuplicateDetector::recordUploadAttempt).
      gcRecordAttempt($link, $filename, $npub);
      return gcJson($response, 200, [
        'ok' => true,
        'status' => 'inserted',
        'id' => $id,
        'filename' => $filename,
        'approvalStatus' => 'pending',
        'mine' => true,
        'atKey' => true,
        'keyHasRow' => true,
      ]);
    } catch (\Throwable $e) {
      error_log('internal/gif-contributions/register error: ' . $e->getMessage());
      return gcJson($response, 500, ['ok' => false, 'error' => 'register failed']);
    }
  });

  // Take a contribution back. Only a row this account contributed is touched. DeleteMedia deletes an unprotected
  // file (bucket, CDN, Blossom link, row) and only unlinks a protected one (the
  // GIF library keeps it). The row's own usernpub is passed, because an npub
  // rotation leaves the old one on the row.
  $group->post('/withdraw', function (Request $request, Response $response) {
    global $link, $awsConfig;
    try {
      $data = gcBody($request);
      $uuid = trim((string)($data['uuid'] ?? ''));
      $freeFilename = (string)($data['freeFilename'] ?? '');
      $contributionId = (string)($data['contributionId'] ?? '');
      $imageId = (int)($data['imageId'] ?? 0);
      if (
        $uuid === '' || $imageId <= 0 || preg_match('/^([0-9a-f]{64})\.(gif|webp)$/', $freeFilename, $m) !== 1
        || preg_match('/^gc-[A-Za-z0-9-]{1,125}$/', $contributionId) !== 1
      ) {
        return gcJson($response, 400, ['ok' => false, 'error' => 'bad-input']);
      }
      [$account, $refusal] = gcAccount($link, $uuid);
      if ($refusal !== null) return gcJson($response, $refusal[0], ['ok' => false, 'error' => $refusal[1]]);

      $stmt = $link->prepare('SELECT id, usernpub, user_uuid, metadata, protected FROM uploads_data WHERE filename = ?');
      $stmt->bind_param('s', $freeFilename);
      $stmt->execute();
      $row = $stmt->get_result()->fetch_assoc();
      $stmt->close();
      if (!is_array($row)) return gcJson($response, 200, ['ok' => true, 'status' => 'gone']);

      if (!gcMine($row, $contributionId, $imageId, $uuid)) {
        return gcJson($response, 403, ['ok' => false, 'error' => 'not-a-contribution']);
      }
      // DeleteMedia picks the owner's row by filename PREFIX (LIMIT 1): with a
      // second same-hash row of that owner it could act on the wrong one.
      $c = $link->prepare("SELECT COUNT(*) AS n FROM uploads_data WHERE usernpub = ? AND filename LIKE ?");
      $owner = (string)$row['usernpub'];
      $prefix = $m[1] . '%';
      $c->bind_param('ss', $owner, $prefix);
      $c->execute();
      $n = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
      $c->close();
      if ($n > 1) return gcJson($response, 409, ['ok' => false, 'error' => 'ambiguous']);
      $protected = (int)($row['protected'] ?? 0) === 1;
      // DeleteMedia finds a free file through its upload_attempts row; write it
      // here (throwing) so a withdraw never depends on register having done so.
      gcRecordAttempt($link, $freeFilename, (string)$row['usernpub']);
      $deleted = (new DeleteMedia((string)$row['usernpub'], $m[1], $link, new S3Service($awsConfig)))->deleteMedia();
      if ($deleted !== true) {
        return gcJson($response, 500, ['ok' => false, 'error' => 'withdraw failed']);
      }
      return gcJson($response, 200, ['ok' => true, 'status' => $protected ? 'unlinked' : 'deleted']);
    } catch (\Throwable $e) {
      error_log('internal/gif-contributions/withdraw error: ' . $e->getMessage());
      return gcJson($response, 500, ['ok' => false, 'error' => 'withdraw failed']);
    }
  });
})->add(new HmacAuthMiddleware());
