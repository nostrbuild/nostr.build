<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\Store;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld
$storeId = '';
$invoiceId = '';

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
    var_dump($client->createStore('my new store'));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
