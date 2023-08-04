<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\LightningInternalNode;

class LightningInternalNodes
{
    public $apiKey;
    public $host;
    public $cryptoCode;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
        $this->cryptoCode = 'BTC';
    }

    public function getNodeInformation()
    {
        try {
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->getNodeInformation(
                $this->cryptoCode
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function connectToLightningNode()
    {
        $nodeURI = null;

        try {
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->connectToLightningNode(
                $this->cryptoCode,
                $nodeURI
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getChannels()
    {
        try {
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->getChannels(
                $this->cryptoCode
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
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->openChannel(
                $this->cryptoCode,
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
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->getDepositAddress(
                $this->cryptoCode
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getInvoice()
    {
        $id = '';

        try {
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->getInvoice(
                $this->cryptoCode,
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
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->payLightningInvoice(
                $this->cryptoCode,
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
            $client = new LightningInternalNode($this->host, $this->apiKey);
            var_dump($client->createLightningInvoice(
                $this->cryptoCode,
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

$node = new LightningInternalNodes();
//$node->getNodeInformation();
//$node->connectToLightningNode();
//$node->getChannels();
//$node->openChannel();
//$node->getDepositAddress();
//$node->getInvoice();
//$node->payLightningInvoice();
//$node->createLightningInvoice();
