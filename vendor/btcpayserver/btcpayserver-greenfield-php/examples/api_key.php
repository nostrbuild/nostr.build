<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\ApiKey;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld
$userEmail = '';
$userId = '';

// Get information about store on BTCPay Server.
try {
    $client = new ApiKey($host, $apiKey);
    var_dump($client->getCurrent());
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
/*
print("\nCreate a new api key (needs server modify permission of used api).\n");
try {
    $client = new Apikey($host, $apiKey);
    var_dump($client->createApiKey('api generated', ['btcpay.store.canmodifystoresettings']));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
*/
print("\nCreate a new api key for different user. Needs unrestricted access\n");

try {
    $client = new Apikey($host, $apiKey);
    $uKey = $client->createApiKeyForUser($userEmail, 'api generated to be deleted', ['btcpay.store.canmodifystoresettings']);
    var_dump($uKey);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}


print("\nRevoke api key for different user.\n");

try {
    $client = new Apikey($host, $apiKey);
    $uKey = $client->revokeApiKeyForUser($userEmail, $uKey->getData()['apiKey']);
    var_dump($uKey);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
