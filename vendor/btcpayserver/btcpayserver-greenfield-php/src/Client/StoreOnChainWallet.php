<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\StoreOnChainWallet as ResultStoreOnChainWallet;
use BTCPayServer\Result\StoreOnChainWalletAddress;
use BTCPayServer\Result\StoreOnChainWalletFeeRate;
use BTCPayServer\Result\StoreOnChainWalletTransaction;
use BTCPayServer\Result\StoreOnChainWalletTransactionList;
use BTCPayServer\Result\StoreOnChainWalletUTXOList;

class StoreOnChainWallet extends AbstractClient
{
    public function getStoreOnChainWalletOverview(
        string $storeId,
        string $cryptoCode
    ): ResultStoreOnChainWallet {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet';

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ResultStoreOnChainWallet(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createStoreOnChainWallet(
        string $storeId,
        string $cryptoCode,
        ?string $existingMnemonic = null,
        ?string $passphrase = null,
        int $accountNumber = 0,
        bool $savePrivateKeys = false,
        bool $importKeysToRPC = false,
        string $wordList = 'English',
        int $wordCount = 12,
        string $scriptPubKeyType = 'Segwit'
    ): ResultStoreOnChainWallet {
        $url = $this->getApiUrl() . 'stores/' .
          urlencode($storeId) . '/payment-methods/onchain/' .
          urlencode($cryptoCode) . '/generate';

        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
              'existingMnemonic' => $existingMnemonic,
              'passphrase' => $passphrase,
              'accountNumber' => $accountNumber,
              'savePrivateKeys' => $savePrivateKeys,
              'importKeysToRPC' => $importKeysToRPC,
              'wordList' => $wordList,
              'wordCount' => $wordCount,
              'scriptPubKeyType' => $scriptPubKeyType
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new ResultStoreOnChainWallet(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getStoreOnChainWalletFeeRate(
        string $storeId,
        string $cryptoCode,
        ?int $blockTarget = null
    ): StoreOnChainWalletFeeRate {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet' . '/feeRate';

        if (isset($blockTarget)) {
            $url .= '/?blockTarget=' . $blockTarget;
        }

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreOnChainWalletFeeRate(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getStoreOnChainWalletAddress(
        string $storeId,
        string $cryptoCode,
        ?string $forceGenerate = 'false'
    ): StoreOnChainWalletAddress {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet' . '/address';

        if (isset($forceGenerate)) {
            $url .= '/?forceGenerate=' . $forceGenerate;
        }

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreOnChainWalletAddress(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function unReserveLastStoreOnChainWalletAddress(
        string $storeId,
        string $cryptoCode
    ): bool {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet' . '/address';

        $headers = $this->getRequestHeaders();
        $method = 'DELETE';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getStoreOnChainWalletTransactions(
        string $storeId,
        string $cryptoCode,
        ?array $statusFilters = null,
        ?int $skip = null,
        ?int $limit = null
    ): StoreOnChainWalletTransactionList {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet' . '/transactions/?';

        $queryParameters = [
            'skip' => $skip,
            'limit' => $limit
        ];

        $url .= http_build_query($queryParameters);

        // Add each statusFilter to the query if one or more are set.
        if (isset($statusFilters)) {
            foreach ($statusFilters as $statusFilter) {
                $url .= '&statusFilter=' . $statusFilter;
            }
        }

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreOnChainWalletTransactionList(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createStoreOnChainWalletTransaction(
        string $storeId,
        string $cryptoCode,
        array $destinations,
        ?float $feeRate,
        ?bool $proceedWithPayjoin = true,
        ?bool $proceedWithBroadcast = true,
        ?bool $noChange = false,
        ?bool $rbf = null,
        ?array $selectedInputs = null
    ): StoreOnChainWalletTransaction {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet' . '/transactions';

        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'destinations' => $destinations,
                'feeRate' => $feeRate,
                'proceedWithPayjoin' => $proceedWithPayjoin,
                'proceedWithBroadcast' => $proceedWithBroadcast,
                'noChange' => $noChange,
                'rbf' => $rbf,
                'selectedInputs' => $selectedInputs
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new StoreOnChainWalletTransaction(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getStoreOnChainWalletTransaction(
        string $storeId,
        string $cryptoCode,
        string $transactionId
    ): StoreOnChainWalletTransaction {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet' . '/transactions' . '/' .
                    urlencode($transactionId);

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreOnChainWalletTransaction(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function updateStoreOnChainWalletTransaction(
        string $storeId,
        string $cryptoCode,
        string $transactionId,
        ?string $comment
    ): StoreOnChainWalletTransaction {
        $url = $this->getApiUrl() . 'stores/' .
            urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
            urlencode($cryptoCode) . '/wallet' . '/transactions' . '/' .
            urlencode($transactionId);

        $headers = $this->getRequestHeaders();
        $method = 'PATCH';

        $body = json_encode(['comment' => $comment], JSON_THROW_ON_ERROR);

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new StoreOnChainWalletTransaction(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getStoreOnChainWalletUTXOs(
        string $storeId,
        string $cryptoCode
    ): StoreOnChainWalletUTXOList {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/payment-methods' . '/OnChain' . '/' .
                    urlencode($cryptoCode) . '/wallet' . '/utxos';

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreOnChainWalletUTXOList(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
