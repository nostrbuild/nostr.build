<?php
// NIP-57 zap transport for the creator page. The browser signs the kind-9734 zap
// request; every HTTP round-trip to the recipient's Lightning provider happens
// here because LNURL endpoints do not ship CORS headers.
//
// Ported from globe-rr `zap.server.ts` (LUD-06 / LUD-16 / NIP-57). No payment confirmation here on purpose.
// The recipient is always resolved from our own DB by creator id, so this can
// not be used as an open LNURL proxy.
//
// POST JSON { action: 'meta' | 'invoice', user: <creator id>, ... }
//   meta    -> { ok, nym, pubkey, lud16, minSats, maxSats, commentAllowed, allowsNostr }
//   invoice -> { sats, comment?, nostrEvent? } -> { ok, pr, zapped }
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/libs/Bech32.class.php';

const ZAP_FETCH_TIMEOUT_S = 8;
const ZAP_MAX_BODY_BYTES = 64 * 1024;
const ZAP_MAX_EVENT_JSON_BYTES = 8 * 1024;
const ZAP_MAX_COMMENT_LEN = 280;
const ZAP_MIN_SATS = 1;
const ZAP_MAX_SATS = 1000000;

class ZapError extends Exception {}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function zap_respond(array $body, int $status = 200): never
{
	http_response_code($status);
	echo json_encode($body, JSON_UNESCAPED_SLASHES);
	exit;
}

// Same rules as lnurl.ts parseLightningAddress: lowercase, one '@', safe charsets only.
function zap_parse_lud16(string $lud16): array
{
	$t = strtolower(trim($lud16));
	$at = strpos($t, '@');
	if ($at === false || $at < 1 || $at !== strrpos($t, '@')) throw new ZapError('Lightning Address must be user@domain.');
	$user = substr($t, 0, $at);
	$domain = substr($t, $at + 1);
	if (!preg_match('/^[a-z0-9._+-]+$/', $user)) throw new ZapError('Lightning Address local part has invalid characters.');
	if (!preg_match('/^[a-z0-9.-]+$/', $domain) || $domain[0] === '.' || str_ends_with($domain, '.')) throw new ZapError('Lightning Address domain looks malformed.');
	return [$user, $domain];
}

// Resolve a hostname and refuse anything that lands on a private, loopback or link-local address.
// Returns the public IPs so the caller can pin them (no DNS rebinding between check and connect).
function zap_public_ips(string $host): array
{
	if ($host === '' || filter_var($host, FILTER_VALIDATE_IP)) throw new ZapError('Lightning provider must be a hostname.');
	$ips = [];
	foreach (dns_get_record($host, DNS_A + DNS_AAAA) ?: [] as $rec) {
		$ip = $rec['ip'] ?? $rec['ipv6'] ?? null;
		if ($ip) $ips[] = $ip;
	}
	if (!$ips) throw new ZapError('Could not resolve the Lightning provider.');
	foreach ($ips as $ip) {
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_GLOBAL_RANGE) === false) {
			throw new ZapError('Lightning provider resolves to a non-public address.');
		}
	}
	return $ips;
}

// HTTPS-only GET with a hard timeout, a bounded body and no redirects; returns decoded JSON object.
function zap_get_json(string $url): array
{
	if (!str_starts_with($url, 'https://')) throw new ZapError('Refusing a non-HTTPS Lightning endpoint.');
	$host = strtolower((string) parse_url($url, PHP_URL_HOST));
	$port = (int) (parse_url($url, PHP_URL_PORT) ?: 443);
	$ips = zap_public_ips($host);
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => false,
		CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
		CURLOPT_RESOLVE        => ["{$host}:{$port}:" . implode(',', $ips)],
		CURLOPT_CONNECTTIMEOUT => ZAP_FETCH_TIMEOUT_S,
		CURLOPT_TIMEOUT        => ZAP_FETCH_TIMEOUT_S,
		CURLOPT_MAXFILESIZE    => ZAP_MAX_BODY_BYTES,
		CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: nostr.build creators zap'],
	]);
	$raw = curl_exec($ch);
	$err = curl_error($ch);
	$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	curl_close($ch);
	if ($raw === false) throw new ZapError('Could not reach the Lightning provider: ' . ($err ?: 'network error'));
	if (strlen($raw) > ZAP_MAX_BODY_BYTES) throw new ZapError('Lightning provider response is too large.');
	$body = json_decode($raw, true);
	if (!is_array($body)) {
		throw new ZapError($status >= 400 ? "Lightning provider returned HTTP {$status}." : 'Lightning provider returned non-JSON.');
	}
	if (($body['status'] ?? '') === 'ERROR') {
		throw new ZapError('Lightning provider error: ' . (is_string($body['reason'] ?? null) ? $body['reason'] : 'unknown'));
	}
	if ($status >= 400) throw new ZapError("Lightning provider returned HTTP {$status}.");
	return $body;
}

// LUD-06 payRequest discovery for a Lightning Address.
function zap_lookup(string $lud16): array
{
	[$user, $domain] = zap_parse_lud16($lud16);
	$body = zap_get_json("https://{$domain}/.well-known/lnurlp/{$user}");
	if (($body['tag'] ?? null) !== 'payRequest') throw new ZapError('Lightning Address does not point at a payRequest endpoint.');
	$callback = $body['callback'] ?? null;
	if (!is_string($callback) || !str_starts_with($callback, 'https://') || !filter_var($callback, FILTER_VALIDATE_URL)) {
		throw new ZapError('Provider callback URL is missing or insecure.');
	}
	$min = $body['minSendable'] ?? null;
	$max = $body['maxSendable'] ?? null;
	if (!is_numeric($min) || !is_numeric($max) || $min < 1 || $max < $min) throw new ZapError('Provider min/maxSendable look malformed.');
	$allowsNostr = ($body['allowsNostr'] ?? false) === true;
	$nostrPubkey = is_string($body['nostrPubkey'] ?? null) ? strtolower($body['nostrPubkey']) : '';
	if ($allowsNostr && !preg_match('/^[0-9a-f]{64}$/', $nostrPubkey)) $allowsNostr = false;
	return [
		'callback'       => $callback,
		'minSendable'    => (int) $min,
		'maxSendable'    => (int) $max,
		'allowsNostr'    => $allowsNostr,
		'nostrPubkey'    => $allowsNostr ? $nostrPubkey : '',
		'commentAllowed' => max(0, (int) ($body['commentAllowed'] ?? 0)),
	];
}

// Structural sanity of the signed kind-9734 before we forward it (the provider verifies the signature).
function zap_check_event(array $ev, int $amountMsats, string $recipientHex): void
{
	if (($ev['kind'] ?? null) !== 9734) throw new ZapError('Zap request must be kind 9734.');
	foreach (['id', 'pubkey', 'sig', 'content'] as $k) {
		if (!is_string($ev[$k] ?? null)) throw new ZapError("Zap request is missing {$k}.");
	}
	if (!preg_match('/^[0-9a-f]{64}$/', $ev['id']) || !preg_match('/^[0-9a-f]{64}$/', $ev['pubkey']) || !preg_match('/^[0-9a-f]{128}$/', $ev['sig'])) {
		throw new ZapError('Zap request has malformed id/pubkey/sig.');
	}
	if (!is_array($ev['tags'] ?? null)) throw new ZapError('Zap request has no tags.');
	$p = null;
	$amount = null;
	foreach ($ev['tags'] as $tag) {
		if (!is_array($tag) || !isset($tag[0])) continue;
		if ($tag[0] === 'p') $p = strtolower((string) ($tag[1] ?? ''));
		if ($tag[0] === 'amount') $amount = (string) ($tag[1] ?? '');
	}
	if ($p !== $recipientHex) throw new ZapError('Zap request is addressed to a different pubkey.');
	if ($amount !== (string) $amountMsats) throw new ZapError('Zap request amount does not match.');
	if (mb_strlen($ev['content']) > ZAP_MAX_COMMENT_LEN) throw new ZapError('Zap comment is too long.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') zap_respond(['ok' => false, 'error' => 'POST only.'], 405);
$in = json_decode((string) file_get_contents('php://input', false, null, 0, 32 * 1024), true);
if (!is_array($in)) zap_respond(['ok' => false, 'error' => 'Body must be JSON.'], 400);

try {
	$userId = (int) ($in['user'] ?? 0);
	$action = (string) ($in['action'] ?? '');

	$stmt = $link->prepare("SELECT nym, wallet, usernpub FROM users WHERE id = ? AND plan_until_date > NOW() AND acctlevel IN (1, 10, 99) LIMIT 1");
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$creator = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	$link->close();
	if (!$creator) throw new ZapError('Creator not found.');
	$lud16 = trim((string) $creator['wallet']);
	if ($lud16 === '') throw new ZapError('This creator has no Lightning Address.');
	$bech32 = new Bech32();
	if (!$bech32->isValidNpub1Address($creator['usernpub'])) throw new ZapError('Creator has no valid npub.');
	$recipientHex = strtolower($bech32->convertBech32ToHex($creator['usernpub']));

	switch ($action) {
		case 'meta': {
			$meta = zap_lookup($lud16);
			zap_respond([
				'ok'             => true,
				'nym'            => (string) $creator['nym'],
				'pubkey'         => $recipientHex,
				'lud16'          => $lud16,
				'minSats'        => max(ZAP_MIN_SATS, (int) ceil($meta['minSendable'] / 1000)),
				'maxSats'        => min(ZAP_MAX_SATS, (int) floor($meta['maxSendable'] / 1000)),
				'commentAllowed' => min(ZAP_MAX_COMMENT_LEN, $meta['commentAllowed']),
				'allowsNostr'    => $meta['allowsNostr'],
			]);
		}
		case 'invoice': {
			$sats = (int) ($in['sats'] ?? 0);
			if ($sats < ZAP_MIN_SATS || $sats > ZAP_MAX_SATS) throw new ZapError('Zap amount is out of range.');
			$amountMsats = $sats * 1000;
			$meta = zap_lookup($lud16);
			if ($amountMsats < $meta['minSendable'] || $amountMsats > $meta['maxSendable']) {
				throw new ZapError(sprintf('This wallet accepts between %d and %d sats.', ceil($meta['minSendable'] / 1000), floor($meta['maxSendable'] / 1000)));
			}
			$url = $meta['callback'] . (str_contains($meta['callback'], '?') ? '&' : '?') . 'amount=' . $amountMsats;
			$comment = trim((string) ($in['comment'] ?? ''));
			$event = $in['nostrEvent'] ?? null;
			if (is_array($event) && $meta['allowsNostr']) {
				zap_check_event($event, $amountMsats, $recipientHex);
				$json = json_encode($event, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
				if (strlen($json) > ZAP_MAX_EVENT_JSON_BYTES) throw new ZapError('Zap request is too large.');
				$url .= '&nostr=' . rawurlencode($json);
			} elseif ($comment !== '' && $meta['commentAllowed'] > 0) {
				// Plain LNURL-pay comment (LUD-12) when the provider does not take zap requests
				$url .= '&comment=' . rawurlencode(mb_substr($comment, 0, $meta['commentAllowed']));
			}
			$body = zap_get_json($url);
			$pr = $body['pr'] ?? null;
			if (!is_string($pr) || !preg_match('/^ln[a-z]/i', $pr)) throw new ZapError('Provider did not return a BOLT11 invoice.');
			zap_respond(['ok' => true, 'pr' => $pr, 'zapped' => is_array($event) && $meta['allowsNostr']]);
		}
		default:
			throw new ZapError('Unknown action.');
	}
} catch (ZapError $e) {
	zap_respond(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
	error_log('creators zap: ' . $e->getMessage());
	zap_respond(['ok' => false, 'error' => 'Zap failed.'], 500);
}
