<?php
// Browser-based uploads have been retired. There is no longer a free (or paid)
// allowance for uploading media through the website. Free uploads now happen
// through the nostr.build API and the Nostr clients that integrate it.
//
// The API upload endpoints live at /api/v2/upload/* and are unaffected.
// Account management, plans, and features are at https://account.nostr.build/.

// Be friendly to anyone who reaches this URL via a stale link in their browser.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
	header('Location: /', true, 302);
	exit;
}

// Any upload attempt (e.g. a legacy form POST) is gone for good.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo "Browser uploads have been retired. Free uploads are now available through the nostr.build API. Manage your account at https://account.nostr.build/.";
exit;
