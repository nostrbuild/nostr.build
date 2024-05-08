<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\Store;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld
$storeId = '';
$updateStoreId = '';

// Get information about store on BTCPay Server.

try {
    $client = new Store($host, $apiKey);
    var_dump($client->getStore($storeId));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

// Create a new store.
try {
    $client = new Store($host, $apiKey);
    $newStore = $client->createStore('New store', null, 'EUR');
    var_dump($newStore);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

// Update a store.
// You need to pass all variables to make sure it does not get reset to defaults if you want to preserve them.
try {
    $client = new Store($host, $apiKey);
    var_dump($client->updateStore($updateStoreId, 'Store name CHANGED'));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
