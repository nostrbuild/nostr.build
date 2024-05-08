<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\Server;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld

// Get information about store on BTCPay Server.
try {
    $client = new Server($host, $apiKey);
    var_dump($client->getInfo());
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
