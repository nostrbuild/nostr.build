<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\Miscellaneous;

class Misc
{
    public $apiKey;
    public $host;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
    }

    public function getPermissionMetadata()
    {
        try {
            $client = new Miscellaneous($this->host, $this->apiKey);
            var_dump($client->getPermissionMetadata());
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getLanguageCodes()
    {
        try {
            $client = new Miscellaneous($this->host, $this->apiKey);
            var_dump($client->getLanguageCodes());
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getInvoiceCheckout()
    {
        $invoiceId = 'invoiceidstring';
        $lang = 'en';

        try {
            $client = new Miscellaneous($this->host, $this->apiKey);
            var_dump($client->getInvoiceCheckout($invoiceId, $lang));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$misc = new Misc();
//$misc->getPermissionMetadata();
//$misc->getLanguageCodes();
//$misc->getInvoiceCheckout();
