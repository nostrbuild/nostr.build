<?php

require __DIR__ . '/../vendor/autoload.php';

use BTCPayServer\Client\StoreOnChainWallet;

class StoreOnChainWallets
{
    public $apiKey;
    public $host;
    public $storeId;

    public function __construct()
    {
        $this->apiKey = '';
        $this->host = '';
        $this->storeId = '';
    }

    public function getStoreOnChainWalletOverview()
    {
        $cryptoCode = 'BTC';

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->getStoreOnChainWalletOverview(
                $this->storeId,
                $cryptoCode
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function createStoreOnChainWallet()
    {
        $cryptoCode = 'BTC';

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->createStoreOnchainWallet(
                $this->storeId,
                $cryptoCode
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getStoreOnChainWalletFeeRate()
    {
        $cryptoCode = 'BTC';
        $blockTarget = 2;

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->getStoreOnChainWalletFeeRate(
                $this->storeId,
                $cryptoCode,
                $blockTarget
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getStoreOnChainWalletAddress()
    {
        $cryptoCode = 'BTC';
        $forceGenerate = 'true';

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->getStoreOnChainWalletAddress(
                $this->storeId,
                $cryptoCode,
                $forceGenerate
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function unReserveLastStoreOnChainWalletAddress()
    {
        $cryptoCode = 'BTC';

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->unReserveLastStoreOnChainWalletAddress(
                $this->storeId,
                $cryptoCode
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getStoreOnChainWalletTransactions()
    {
        $cryptoCode = 'BTC';
        $statusFilter = ['Unconfirmed','Confirmed','Replaced'];
        $skip = 0;
        $limit = 100;

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->getStoreOnChainWalletTransactions(
                $this->storeId,
                $cryptoCode,
                $statusFilter,
                $skip,
                $limit
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function createStoreOnChainWalletTransaction()
    {
        $cryptoCode = 'BTC';
        $destinations = [];
        $feeRate = 0;
        $proceedWithPayjoin = false;
        $proceedWithBroadcast = true;
        $noChange = false;
        $rbf = null;
        $selectedInupts = [];

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->createStoreOnChainWalletTransaction(
                $this->storeId,
                $cryptoCode,
                $destinations,
                $feeRate,
                $proceedWithPayjoin,
                $proceedWithBroadcast,
                $noChange,
                $rbf,
                $selectedInupts
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getStoreOnChainWalletTransaction()
    {
        $cryptoCode = 'BTC';
        $transactionId = 'c7f4571bd21c119ca1f151ccfc82c30c6d849e157edb073f5cd6af52fd379886';

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->getStoreOnChainWalletTransaction(
                $this->storeId,
                $cryptoCode,
                $transactionId
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getStoreOnChainWalletUTXOs()
    {
        $cryptoCode = 'BTC';

        try {
            $client = new StoreOnChainWallet($this->host, $this->apiKey);
            var_dump($client->getStoreOnChainWalletUTXOs(
                $this->storeId,
                $cryptoCode
            ));
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}

$store = new StoreOnChainWallets();
//$store->getStoreOnChainWalletAddress();
//$store->getStoreOnChainWalletFeeRate();
//$store->getStoreOnChainWalletOverview();
$store->getStoreOnChainWalletTransactions();
//$store->getStoreOnChainWalletTransaction();
//$store->getStoreOnChainWalletUTXOs();
//$store->createStoreOnChainWallet();
