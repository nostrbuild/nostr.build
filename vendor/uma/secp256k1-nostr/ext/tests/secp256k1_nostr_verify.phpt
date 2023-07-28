--TEST--
secp256k1_nostr_verify happy path
--EXTENSIONS--
secp256k1_nostr
--FILE--
<?php

$publicKey = '1f00befecb50dc441204a6208b80924985b4965563b26845a6e2c12a3b6e37c5';
$message = '89eab265a7e520b070ee2e9a56b1ae532fac0bd911d090867943b64f32b6396e';
$sig_ok = 'bbfec4d5f7c7a1a3d91d69e5020b71fcf7ca427cf7060efac84fbb2c38f10242c6af334c35ee0fd52ac306683821dcad9582c398bbe7f96b2fd5e49267654d9f';
$sig_bad = 'abfec4d5f7c7a1a3d91d69e5020b71fcf7ca427cf7060efac84fbb2c38f10242c6af334c35ee0fd52ac306683821dcad9582c398bbe7f96b2fd5e49267654d9f';

var_dump(secp256k1_nostr_verify($publicKey, $message, $sig_ok));
var_dump(secp256k1_nostr_verify($publicKey, $message, $sig_bad));
?>
--EXPECT--
bool(true)
bool(false)
