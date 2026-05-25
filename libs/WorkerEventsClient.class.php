<?php
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

/**
 * One-way client that POSTs HMAC-signed `EventEnvelope`s to the
 * account.nostr.build Worker (POST /api/internal/events).
 *
 * The Worker validates the HMAC, then dispatches the envelope to the
 * addressed user's SessionDO, which broadcasts a WebSocket frame to
 * every connected device for that user. This is how cross-device sync
 * stays live for mutations that originate outside the Worker proxy —
 * NIP-96/Blossom uploads, BTCPay subscription renewals, ban tooling,
 * etc. (Mutations that go through the Worker proxy already emit
 * in-process via `notifyEvent` and don't need this client.)
 *
 * Config (read from $_SERVER, names match the Worker side):
 *   NB_HMAC_SECRETS         Comma-separated. First non-empty entry signs;
 *                           the Worker verifier accepts any entry, so adding
 *                           a new key + redeploying both sides without
 *                           coordination is a safe rotation.
 *   NB_ACCOUNT_WORKER_URL   Optional base URL override (default:
 *                           https://account.nostr.build). The signed payload
 *                           includes the full URL — it must match what the
 *                           Worker observes in `request.url`, so we always
 *                           sign the same absolute URL we POST to.
 *
 * Failure handling: every emit is best-effort. cURL errors and non-2xx
 * responses are written to `error_log` but never thrown. A missed event
 * degrades the UX to "the other device refetches on its next visibility
 * flip" (the WS reconnect handler invalidates everything), not to a
 * broken request — so we prefer letting the upload return success over
 * propagating a webhook hiccup back to the API caller.
 */
class WorkerEventsClient
{
  private string $url;
  private string $signingSecret;
  private int $timeoutMs;

  public function __construct(
    ?string $baseUrl = null,
    ?string $secrets = null,
    int $timeoutMs = 1500
  ) {
    $base = rtrim(
      $baseUrl ?? ($_SERVER['NB_ACCOUNT_WORKER_URL'] ?? 'https://account.nostr.build'),
      '/'
    );
    $this->url = $base . '/api/internal/events';

    $secretsList = $secrets ?? ($_SERVER['NB_HMAC_SECRETS'] ?? '');
    $first = '';
    foreach (explode(',', $secretsList) as $candidate) {
      $candidate = trim($candidate);
      if ($candidate !== '') {
        $first = $candidate;
        break;
      }
    }
    if ($first === '') {
      // Not throwing: a missing secret would otherwise hard-fail every
      // upload. emit() short-circuits on empty secret and logs once per call.
      error_log('WorkerEventsClient: NB_HMAC_SECRETS missing — events disabled');
    }
    $this->signingSecret = $first;
    $this->timeoutMs = $timeoutMs;
  }

  /**
   * Files added/removed/moved/edited under a user's account.
   *
   * @param int           $userId   Target user (`Account::getAccountNumericId()`).
   * @param string[]|null $folders  Folder names hint — narrows client-side
   *                                invalidation. Null/empty ⇒ "I don't know,
   *                                invalidate everything."
   * @param int           $added    Count carriers — currently unused by the UI
   * @param int           $removed  but propagated for future deltas (toast UX,
   * @param int           $moved    optimistic counters). Zero ⇒ omit from the
   * @param int           $edited   wire envelope.
   */
  public function emitFilesChanged(
    int $userId,
    ?array $folders = null,
    int $added = 0,
    int $removed = 0,
    int $moved = 0,
    int $edited = 0
  ): void {
    $envelope = ['type' => 'files-changed', 'userId' => $userId];
    if ($folders !== null && count($folders) > 0) {
      $envelope['folders'] = array_values($folders);
    }
    if ($added > 0)   $envelope['added']   = $added;
    if ($removed > 0) $envelope['removed'] = $removed;
    if ($moved > 0)   $envelope['moved']   = $moved;
    if ($edited > 0)  $envelope['edited']  = $edited;
    $this->emit($envelope);
  }

  /**
   * Profile fields changed (plan, credits, pfp, storage usage, ...).
   *
   * Pass `$fields` when you know the new values and want to push them
   * into the DO snapshot directly (avoids a round-trip refetch on each
   * connected device). Pass `$changed` to name the field(s) that moved
   * without supplying values — the DO will mark them stale and clients
   * will refetch. Both null ⇒ bare bump ("something profile-shaped
   * changed, refetch").
   *
   * @param int                $userId
   * @param array<string,mixed>|null $fields   Partial ProfileSnapshot.
   * @param string[]|null      $changed  Field names that changed.
   */
  public function emitProfileChanged(
    int $userId,
    ?array $fields = null,
    ?array $changed = null
  ): void {
    $envelope = ['type' => 'profile-changed', 'userId' => $userId];
    if ($fields !== null  && count($fields) > 0)  $envelope['fields']  = $fields;
    if ($changed !== null && count($changed) > 0) $envelope['changed'] = array_values($changed);
    $this->emit($envelope);
  }

  /** Folder list mutated. Pass at most the deltas you know. */
  public function emitFoldersChanged(
    int $userId,
    ?array $added = null,
    ?array $removed = null,
    ?array $renamed = null
  ): void {
    $envelope = ['type' => 'folders-changed', 'userId' => $userId];
    if ($added !== null   && count($added) > 0)   $envelope['added']   = array_values($added);
    if ($removed !== null && count($removed) > 0) $envelope['removed'] = array_values($removed);
    if ($renamed !== null && count($renamed) > 0) $envelope['renamed'] = array_values($renamed);
    $this->emit($envelope);
  }

  /** Force-logout: every device for $userId clears its cache and bounces to /login. */
  public function emitBanned(int $userId): void
  {
    $this->emit(['type' => 'banned', 'userId' => $userId]);
  }

  private function emit(array $envelope): void
  {
    if ($this->signingSecret === '') return;

    $body = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
      error_log('WorkerEventsClient: json_encode failed: ' . json_last_error_msg());
      return;
    }

    // Sign with a single timestamp value: the Worker hashes against the `ts`
    // it reads out of the bearer, so signing-payload and bearer MUST agree.
    // (signApiRequest in Credits.class.php calls time() twice for historical
    // reasons; sub-second cross-boundary would make it flake intermittently.)
    $ts = time();
    $bodyHash = hash('sha256', $body);
    $payload = "POST|{$this->url}|{$bodyHash}|{$ts}";
    $mac = base64_encode(hash_hmac('sha256', $payload, $this->signingSecret, true));
    $authHeader = "Bearer HMAC|SHA256|{$ts}|{$mac}";

    $ch = curl_init($this->url);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $body,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Authorization: ' . $authHeader,
        'Content-Type: application/json',
      ],
      CURLOPT_CONNECTTIMEOUT_MS => 1000,
      CURLOPT_TIMEOUT_MS => $this->timeoutMs,
    ]);
    $resp = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
      error_log('WorkerEventsClient: curl error (' . $envelope['type'] . '): ' . $err);
      return;
    }
    if ($status < 200 || $status >= 300) {
      error_log(
        "WorkerEventsClient: webhook HTTP {$status} for type={$envelope['type']} userId={$envelope['userId']}: " .
        (is_string($resp) ? $resp : '')
      );
    }
  }
}
