<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\Store as ResultStore;

class Store extends AbstractClient
{
    public function createStore(
        string $name,
        ?string $website = null,
        string $defaultCurrency = 'USD',
        int $invoiceExpiration = 900,
        int $displayExpirationTimer = 300,
        int $monitoringExpiration = 3600,
        string $speedPolicy = 'MediumSpeed',
        ?string $lightningDescriptionTemplate = null,
        int $paymentTolerance = 0,
        bool $anyoneCanCreateInvoice = false,
        bool $requiresRefundEmail = false,
        ?string $checkoutType = 'V1',
        ?array $receipt = null,
        bool $lightningAmountInSatoshi = false,
        bool $lightningPrivateRouteHints = false,
        bool $onChainWithLnInvoiceFallback = false,
        bool $redirectAutomatically = false,
        bool $showRecommendedFee = true,
        int $recommendedFeeBlockTarget = 1,
        string $defaultLang = 'en',
        ?string $customLogo = null,
        ?string $customCSS = null,
        ?string $htmlTitle = null,
        string $networkFeeMode = 'MultiplePaymentsOnly',
        bool $payJoinEnabled = false,
        bool $lazyPaymentMethods = false,
        string $defaultPaymentMethod = 'BTC'
    ): ResultStore {
        $url = $this->getApiUrl() . 'stores';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                "name" => $name,
                "website" => $website,
                "defaultCurrency" => $defaultCurrency,
                "invoiceExpiration" => $invoiceExpiration,
                "displayExpirationTimer" => $displayExpirationTimer,
                "monitoringExpiration" => $monitoringExpiration,
                "speedPolicy" => $speedPolicy,
                "lightningDescriptionTemplate" => $lightningDescriptionTemplate,
                "paymentTolerance" => $paymentTolerance,
                "anyoneCanCreateInvoice" => $anyoneCanCreateInvoice,
                "requiresRefundEmail" => $requiresRefundEmail,
                "checkoutType" => $checkoutType,
                "receipt" => $receipt,
                "lightningAmountInSatoshi" => $lightningAmountInSatoshi,
                "lightningPrivateRouteHints" => $lightningPrivateRouteHints,
                "onChainWithLnInvoiceFallback" => $onChainWithLnInvoiceFallback,
                "redirectAutomatically" => $redirectAutomatically,
                "showRecommendedFee" => $showRecommendedFee,
                "recommendedFeeBlockTarget" => $recommendedFeeBlockTarget,
                "defaultLang" => $defaultLang,
                "customLogo" => $customLogo,
                "customCSS" => $customCSS,
                "htmlTitle" => $htmlTitle,
                "networkFeeMode" => $networkFeeMode,
                "payJoinEnabled" => $payJoinEnabled,
                "lazyPaymentMethods" => $lazyPaymentMethods,
                "defaultPaymentMethod" => $defaultPaymentMethod
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new ResultStore(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getStore(string $storeId): ResultStore
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ResultStore(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * @return \BTCPayServer\Result\Store[]
     */
    public function getStores(): array
    {
        $url = $this->getApiUrl() . 'stores';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            $r = [];
            $data = json_decode($response->getBody(), true);
            foreach ($data as $item) {
                $item = new ResultStore($item);
                $r[] = $item;
            }
            return $r;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
