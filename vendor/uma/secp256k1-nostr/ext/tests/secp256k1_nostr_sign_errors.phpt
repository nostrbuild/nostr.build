--TEST--
secp256k1_nostr_sign invalid usages
--EXTENSIONS--
secp256k1_nostr
--FILE--
<?php

try {
    secp256k1_nostr_sign(
        'deadbeef',
        '89eab265a7e520b070ee2e9a56b1ae532fac0bd911d090867943b64f32b6396e'
    );
} catch (\InvalidArgumentException $e) {
    var_dump($e->getMessage());
}

try {
    secp256k1_nostr_sign(
        '0000000000000000000000000000000000000000000000000000000000000000',
        '89eab265a7e520b070ee2e9a56b1ae532fac0bd911d090867943b64f32b6396e'
    );
} catch (\InvalidArgumentException $e) {
    var_dump($e->getMessage());
}

try {
    secp256k1_nostr_sign(
        'cb6bb4551955d8b5ad3ebc3b3a764601ed4e373f54dd195a8721e7bec24ee42b',
        'deadbeef'
    );
} catch (\InvalidArgumentException $e) {
    var_dump($e->getMessage());
}

?>
--EXPECT--
string(71) "secp256k1_nostr_sign(): Parameter 1 is not a hex-encoded 32 byte string"
string(62) "secp256k1_nostr_sign(): Parameter 1 is not a valid private key"
string(71) "secp256k1_nostr_sign(): Parameter 2 is not a hex-encoded 32 byte string"
