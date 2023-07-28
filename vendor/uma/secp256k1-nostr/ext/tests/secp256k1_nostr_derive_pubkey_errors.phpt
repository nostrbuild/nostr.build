--TEST--
secp256k1_nostr_derive_pubkey invalid usages
--EXTENSIONS--
secp256k1_nostr
--FILE--
<?php
try {
    secp256k1_nostr_derive_pubkey('deadbeef');
} catch (\InvalidArgumentException $e) {
    var_dump($e->getMessage());
}

try {
    secp256k1_nostr_derive_pubkey('0000000000000000000000000000000000000000000000000000000000000000');
} catch (\InvalidArgumentException $e) {
    var_dump($e->getMessage());
}
?>
--EXPECT--
string(80) "secp256k1_nostr_derive_pubkey(): Parameter 1 is not a hex-encoded 32 byte string"
string(71) "secp256k1_nostr_derive_pubkey(): Parameter 1 is not a valid private key"
