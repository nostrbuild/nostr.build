<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\LightningStore;

class LightningStores
{
    public $apiKey;
    public $host;
    public $storeId;
    public $cryptoCode;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
        $this->storeId = '';
        $this->cryptoCode = 'BTC';
    }

    public function getNodeInformation()
    {
        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->getNodeInformation(
                $this->cryptoCode,
                $this->storeId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function connectToLightningNode()
    {
        $nodeURI = null;

        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->connectToLightningNode(
                $this->cryptoCode,
                $this->storeId,
                $nodeURI
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getChannels()
    {
        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->getChannels(
                $this->cryptoCode,
                $this->storeId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function openChannel()
    {
        $nodeURI = '';
        $channelAmount = '100';
        $feeRate = '1';

        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->openChannel(
                $this->cryptoCode,
                $this->storeId,
                $nodeURI,
                $channelAmount,
                $feeRate
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getDepositAddress()
    {
        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->getDepositAddress(
                $this->cryptoCode,
                $this->storeId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getInvoice()
    {
        $id = '';

        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->getInvoice(
                $this->cryptoCode,
                $this->storeId,
                $id
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function payLightningInvoice()
    {
        $BOLT11 = '';

        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->payLightningInvoice(
                $this->cryptoCode,
                $this->storeId,
                $BOLT11
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function createLightningInvoice()
    {
        $amount = '1000000';
        $description = 'test';
        $expiry = 600;
        $privateRouteHints = false;

        try {
            $client = new LightningStore($this->host, $this->apiKey);
            var_dump($client->createLightningInvoice(
                $this->cryptoCode,
                $this->storeId,
                $amount,
                $expiry,
                $description,
                $privateRouteHints
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$node = new LightningStores();
//$node->getNodeInformation();
//$node->connectToLightningNode();
//$node->getChannels();
//$node->openChannel();
//$node->getDepositAddress();
//$node->getInvoice();
//$node->payLightningInvoice();
//$node->createLightningInvoice();
