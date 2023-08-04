<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\PullPayment as ResultPullPayment;
use BTCPayServer\Result\PullPaymentList;
use BTCPayServer\Result\PullPaymentPayout;
use BTCPayServer\Result\PullPaymentPayoutList;
use BTCPayServer\Util\PreciseNumber;

class PullPayment extends AbstractClient
{
    public function getStorePullPayments(
        string $storeId,
        bool $includeArchived
    ): PullPaymentList {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/pull-payments';

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $body = json_encode(
            [
                'includeArchived' => $includeArchived,
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new PullPaymentList(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createPullPayment(
        string $storeId,
        ?string $name = null,
        PreciseNumber $amount,
        string $currency,
        ?int $period,
        ?int $BOLT11Expiration,
        ?bool $autoApproveClaims = false,
        ?int $startsAt,
        ?int $expiresAt,
        array $paymentMethods
    ): ResultPullPayment {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/pull-payments';

        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'name' => $name,
                'amount' => $amount->__toString(),
                'currency' => $currency,
                'period' => $period,
                'BOLT11Expiration' => $BOLT11Expiration,
                'autoApproveClaims' => $autoApproveClaims,
                'startsAt' => $startsAt,
                'expiresAt' => $expiresAt,
                'paymentMethods' => $paymentMethods
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new ResultPullPayment(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function archivePullPayment(
        string $storeId,
        string $pullPaymentId
    ): bool {
        $url = $this->getApiUrl() . 'stores/' .
        urlencode($storeId) . '/' . 'pull-payments/' .
        urlencode($pullPaymentId);

        $headers = $this->getRequestHeaders();
        $method = 'DELETE';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function approvePayout(
        string $storeId,
        string $payoutId,
        int $revision,
        ?string $rateRule
    ): PullPaymentPayout {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/' . 'payouts/' .
                    urlencode($payoutId);

        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'revision' => $revision,
                'rateRule' => $rateRule,
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new PullPaymentPayout(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function cancelPayout(
        string $storeId,
        string $payoutId
    ): bool {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/' . 'payouts/' .
                    urlencode($payoutId);

        $headers = $this->getRequestHeaders();
        $method = 'DELETE';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function markPayoutAsPaid(
        string $storeId,
        string $payoutId
    ): bool {
        $url = $this->getApiUrl() . 'stores/' .
                    urlencode($storeId) . '/' . 'payouts/' .
                    urlencode($payoutId) . '/mark-paid';

        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getPullPayment(
        string $pullPaymentId
    ): ResultPullPayment {
        $url = $this->getApiUrl() . 'pull-payments/' .
                    urlencode($pullPaymentId);

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ResultPullPayment(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getPayouts(
        string $pullPaymentId,
        bool $includeCancelled
    ): PullPaymentPayoutList {
        $url = $this->getApiUrl() . 'pull-payments/' .
                    urlencode($pullPaymentId) . '/payouts';

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $body = json_encode(
            [
                'includeCancelled' => $includeCancelled,
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new PullPaymentPayoutList(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createPayout(
        string $pullPaymentId,
        string $destination,
        PreciseNumber $amount,
        string $paymentMethod
    ): PullPaymentPayout {
        $url = $this->getApiUrl() . 'pull-payments/' .
                     urlencode($pullPaymentId) . '/payouts';

        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'destination' => $destination,
                'amount' => $amount->__toString(),
                'paymentMethod' => $paymentMethod,
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new PullPaymentPayout(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getPayout(
        string $pullPaymentId,
        string $payoutId
    ): PullPaymentPayout {
        $url = $this->getApiUrl() . 'pull-payments/' .
                    urlencode($pullPaymentId) . '/payouts' . '/' .
                    urlencode($payoutId);

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new PullPaymentPayout(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
