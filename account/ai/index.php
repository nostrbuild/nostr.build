<?php
// The AI tools now live in the account.nostr.build app. Point straight at it
// (previously this bounced through /account).
http_response_code(301);
header('Location: https://account.nostr.build/');
exit;
