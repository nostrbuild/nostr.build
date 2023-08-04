<?php

// Include autoload file.
require __DIR__ . '/../vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld
$storeId = '';
$invoiceId = '';

// Get information about a specific invoice.
try {
    echo 'Get invoice data:' . PHP_EOL;
    $client = new Invoice($host, $apiKey);
    var_dump($client->getInvoice($storeId, $invoiceId));
    echo 'Get invoice payment methods:' . PHP_EOL;
    var_dump($client->getPaymentMethods($storeId, $invoiceId));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
