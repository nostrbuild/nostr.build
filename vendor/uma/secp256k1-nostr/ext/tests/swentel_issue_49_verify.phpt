--TEST--
Signature verification of a case that throws an exception on public-square/phpecc v0.1.2 and should not
--EXTENSIONS--
secp256k1_nostr
--FILE--
<?php

// Further context: https://github.com/swentel/nostr-php/issues/49

$publicKey = '07adfda9c5adc80881bb2a5220f6e3181e0c043b90fa115c4f183464022968e6';
$message = 'd677b5efa1484e3461884d6ba01e78b7ced36ccfc4b5b873c0b4142ea574938f';
$sig_ok = '49352dbe20322a9cc40433537a147805e2541846c006a3e06d9f90faadb89c83ee6da24807fb9eddc6ed9a1d3c15cd5438df07ec6149d6bf48fe1312c9593567';

var_dump(secp256k1_nostr_verify($publicKey, $message, $sig_ok));
?>
--EXPECT--
bool(true)
