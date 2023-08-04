<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\StorePaymentMethod;
use BTCPayServer\Client\StorePaymentMethodLightningNetwork;
use BTCPayServer\Client\StorePaymentMethodOnChain;

// Fill in with your BTCPay Server data.
$apiKey = '';
$host = ''; // e.g. https://your.btcpay-server.tld
$storeId = '';
$cryptoCode = 'BTC';

$updatePayload = [
    'enabled' => false,
    // Needs fixing see, https://github.com/btcpayserver/btcpayserver/issues/2860
    'connectionString' => 'Internal Node' // external would be 'type=clightning;server=tcp://1.1.1.1:27743/',
];

// Payment methods examples.
try {
    echo 'Fetch all OnChain + LightningNetwork payment methods:' . PHP_EOL;
    $clientGlobal = new StorePaymentMethod($host, $apiKey);
    var_dump($clientGlobal->getPaymentMethods($storeId));
    echo PHP_EOL . 'Fetch all OnChain payment methods:' . PHP_EOL;
    $clientOC = new StorePaymentMethodOnChain($host, $apiKey);
    var_dump($clientOC->getPaymentMethods($storeId));
    echo PHP_EOL. 'Preview OnChain addresses for cryptoCode:' . PHP_EOL;
    var_dump($clientOC->previewPaymentMethodAddresses($storeId, $cryptoCode));
    echo PHP_EOL. 'Fetch single OnChain payment method:' . PHP_EOL;
    var_dump($clientOC->getPaymentMethod($storeId, $cryptoCode));
    echo PHP_EOL . 'Fetch all LightningNetwork methods:' . PHP_EOL;
    $clientLN = new StorePaymentMethodLightningNetwork($host, $apiKey);
    var_dump($clientLN->getPaymentMethods($storeId));
    echo PHP_EOL . 'Fetch single LN payment method:' . PHP_EOL;
    var_dump($clientLN->getPaymentMethods($storeId, $cryptoCode));
    echo PHP_EOL . 'Disable BTC LN payment method:' . PHP_EOL;
    var_dump($clientLN->updatePaymentMethod($storeId, $cryptoCode, $updatePayload));
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage();
}
