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

// Get 2 invoices, skip 2
try {
    echo 'Get invoices:' . PHP_EOL;
    $client = new Invoice($host, $apiKey);
    var_dump($client->getAllInvoices($storeId, 2, 2));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

// Get newer/equal than 2024-10-20
try {
    echo 'Get invoices newer/equal than 2024-10-20:' . PHP_EOL;
    $date = new DateTime('2024-10-20');
    $client = new Invoice($host, $apiKey);
    var_dump($client->getAllInvoicesWithFilter($storeId, null, null, null, $date->getTimestamp()));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
