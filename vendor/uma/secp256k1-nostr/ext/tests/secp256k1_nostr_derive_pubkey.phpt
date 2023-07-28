--TEST--
secp256k1_nostr_derive_pubkey happy path
--EXTENSIONS--
secp256k1_nostr
--FILE--
<?php
var_dump(secp256k1_nostr_derive_pubkey('cb6bb4551955d8b5ad3ebc3b3a764601ed4e373f54dd195a8721e7bec24ee42b'));
?>
--EXPECT--
string(64) "1f00befecb50dc441204a6208b80924985b4965563b26845a6e2c12a3b6e37c5"
