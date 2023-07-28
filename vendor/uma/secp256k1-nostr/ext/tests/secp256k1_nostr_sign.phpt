--TEST--
secp256k1_nostr_sign happy path
--EXTENSIONS--
secp256k1_nostr
--FILE--
<?php

$privateKey = 'cb6bb4551955d8b5ad3ebc3b3a764601ed4e373f54dd195a8721e7bec24ee42b';
$publicKey = '1f00befecb50dc441204a6208b80924985b4965563b26845a6e2c12a3b6e37c5';
$message = '89eab265a7e520b070ee2e9a56b1ae532fac0bd911d090867943b64f32b6396e';

$signature = secp256k1_nostr_sign($privateKey, $message);
var_dump(strlen(hex2bin($signature)));
var_dump(secp256k1_nostr_verify($publicKey, $message, $signature));

$signature2 = secp256k1_nostr_sign($privateKey, $message);
var_dump(strlen(hex2bin($signature2)));
var_dump(secp256k1_nostr_verify($publicKey, $message, $signature2));

// Signatures are not deterministic
var_dump($signature !== $signature2);
?>
--EXPECT--
int(64)
bool(true)
int(64)
bool(true)
bool(true)
