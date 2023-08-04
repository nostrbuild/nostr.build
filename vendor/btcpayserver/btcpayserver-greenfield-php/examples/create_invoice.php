<?php

// Include autoload file.
require __DIR__ . '/../vendor/autoload.php';

// Import Invoice client class.
use BTCPayServer\Client\Invoice;
use BTCPayServer\Client\InvoiceCheckoutOptions;
use BTCPayServer\Util\PreciseNumber;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = '';
$storeId = '';
$amount = 5.15 + mt_rand(0, 20);
$currency = 'USD';
$orderId = 'Test39939' . mt_rand(0, 1000);
$buyerEmail = 'john@example.com';

// Create a basic invoice.
try {
    $client = new Invoice($host, $apiKey);
    var_dump(
        $client->createInvoice(
            $storeId,
            $currency,
            PreciseNumber::parseString($amount),
            $orderId,
            $buyerEmail
        )
    );
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

// Create a more complex invoice with redirect url and metadata.
try {
    $client = new Invoice($host, $apiKey);

    // Setup custom metadata. This will be visible in the invoice and can include
    // arbitrary data. Example below will show up on the invoice details page on
    // BTCPay Server.
    $metaData = [
      'buyerName' => 'John Doe',
      'buyerAddress1' => '43 South Beech Rd.',
      'buyerAddress2' => 'Door 3',
      'buyerCity' => 'Mount Prospect',
      'buyerState' => 'IL',
      'buyerZip' => '60056',
      'buyerCountry' => 'USA',
      'buyerPhone' => '001555664123456',
      'posData' => 'Data shown on the invoice details go here. Can be JSON encoded string',
      'itemDesc' => 'Can be a description of the purchased item.',
      'itemCode' => 'Can be SKU or item number',
      'physical' => false, // indicates if physical product
      'taxIncluded' => 2.15, // tax amount (included in the total amount).
    ];

    // Setup custom checkout options, defaults get picked from store config.
    $checkoutOptions = new InvoiceCheckoutOptions();
    $checkoutOptions
      ->setSpeedPolicy($checkoutOptions::SPEED_HIGH)
      ->setPaymentMethods(['BTC'])
      ->setRedirectURL('https://shop.yourdomain.tld?order=38338');

    var_dump(
        $client->createInvoice(
            $storeId,
            $currency,
            PreciseNumber::parseString($amount),
            $orderId,
            $buyerEmail,
            $metaData,
            $checkoutOptions
        )
    );
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}

// Create a top-up (0 initial amount) invoice.
try {
    $client = new Invoice($host, $apiKey);
    var_dump(
        $client->createInvoice(
            $storeId,
            $currency,
            null,
            $orderId,
            $buyerEmail
        )
    );
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
