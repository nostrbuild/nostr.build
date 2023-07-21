<?php

// Require the autoload file.
require __DIR__ . '/../src/autoload.php';

// Example to get all stores.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld

try {
    $client = new \BTCPayServer\Client\Store($host, $apiKey);
    var_dump($client->getStores());
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
