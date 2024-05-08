<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\StoreUser;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld
$storeId = '';
$userId = '';

// List all store users.
echo "\n List all store users \n";
try {
    $client = new StoreUser($host, $apiKey);
    var_dump($client->getUsers($storeId));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

// Add a user to a store.
echo "\n Add user to a store \n";
try {
    $client = new StoreUser($host, $apiKey);
    var_dump($client->addUser($storeId, $userId, 'Owner'));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

// Delete a user from store.
echo "\n Delete user form store \n:";
try {
    $client = new StoreUser($host, $apiKey);
    var_dump($client->deleteUser($storeId, $userId));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
