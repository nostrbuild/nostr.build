<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\StoreRateList;
use BTCPayServer\Result\StoreRateSettings;
use BTCPayServer\Util\PreciseNumber;

class StoreRate extends AbstractClient
{
    public function getSettings(string $storeId): StoreRateSettings
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/rates/configuration';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreRateSettings(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function updateSettings(
        string $storeId,
        PreciseNumber $spread,
        bool $isCustomScript,
        string $preferredSource,
        ?string $effectiveScript = null
    ): StoreRateSettings {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/rates/configuration';
        $headers = $this->getRequestHeaders();
        $method = 'PUT';

        $body = json_encode(
            [
                'spread' => $spread->__toString(),
                'isCustomScript' => $isCustomScript,
                'preferredSource' => $preferredSource,
                'effectiveScript' => $effectiveScript
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new StoreRateSettings(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function previewRateRules(
        string $storeId,
        ?array $currencyPairs,
        PreciseNumber $spread,
        bool $isCustomScript,
        ?string $preferredSource = null,
        ?string $effectiveScript = null
    ): StoreRateList {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/rates/configuration/preview?';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        if ($currencyPairs !== null) {
            foreach ($currencyPairs as $pair) {
                $url .= 'currencyPair=' . urlencode(strtoupper($pair)) . '&';
            }
        }

        // Clean URL.
        $url = rtrim($url, '&');
        $url = rtrim($url, '?');

        $body = json_encode(
            [
                'spread' => $spread->__toString(),
                'isCustomScript' => $isCustomScript,
                'preferredSource' => $preferredSource,
                'effectiveScript' => $effectiveScript
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new StoreRateList(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getRates(
        string $storeId,
        array $currencyPairs = null
    ): StoreRateList {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/rates?';
        $headers = $this->getRequestHeaders();
        $method = 'GET';

        if ($currencyPairs !== null) {
            foreach ($currencyPairs as $pair) {
                $url .= 'currencyPair=' . urlencode(strtoupper($pair)) . '&';
            }
        }

        // Clean URL.
        $url = rtrim($url, '&');
        $url = rtrim($url, '?');

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreRateList(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
