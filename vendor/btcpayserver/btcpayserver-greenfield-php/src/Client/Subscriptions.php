<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\Credit;
use BTCPayServer\Result\Offering;
use BTCPayServer\Result\OfferingList;
use BTCPayServer\Result\OfferingPlan;
use BTCPayServer\Result\PlanCheckout;
use BTCPayServer\Result\PortalSession;
use BTCPayServer\Result\Subscriber;

/**
 * Handles subscriptions operations.
 *
 * @see https://docs.btcpayserver.org/API/Greenfield/v1/#tag/Subscriptions
 */
class Subscriptions extends AbstractClient
{
    // Offering endpoints

    public function getOffering(string $storeId, string $offeringId): Offering
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new Offering(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getOfferings(string $storeId): OfferingList
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new OfferingList(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createOffering(
        string $storeId,
        ?string $appName = null,
        ?string $successRedirectUrl = null,
        ?array $metadata = null,
        ?array $features = null
    ): Offering {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'appName' => $appName,
                'successRedirectUrl' => $successRedirectUrl,
                'metadata' => $metadata,
                'features' => $features
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200 || $response->getStatus() === 201) {
            return new Offering(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    // Plan endpoints

    public function createOfferingPlan(
        string $storeId,
        string $offeringId,
        ?string $description = null,
        ?string $currency = null,
        ?int $gracePeriodDays = null,
        ?string $name = null,
        ?bool $optimisticActivation = null,
        ?string $price = null,
        ?bool $renewable = null,
        ?int $trialDays = null,
        ?array $metadata = null,
        ?string $recurringType = null,
        ?array $features = null
    ): OfferingPlan {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId) . '/plans';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'description' => $description,
                'currency' => $currency,
                'gracePeriodDays' => $gracePeriodDays,
                'name' => $name,
                'optimisticActivation' => $optimisticActivation,
                'price' => $price,
                'renewable' => $renewable,
                'trialDays' => $trialDays,
                'metadata' => $metadata,
                'recurringType' => $recurringType,
                'features' => $features
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200 || $response->getStatus() === 201) {
            return new OfferingPlan(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getOfferingPlan(string $storeId, string $offeringId, string $planId): OfferingPlan
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId) . '/plans/' . urlencode($planId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new OfferingPlan(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    // Subscriber endpoints

    public function getSubscriber(string $storeId, string $offeringId, string $customerSelector): Subscriber
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId) . '/subscribers/' . urlencode($customerSelector);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new Subscriber(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function suspendSubscriber(string $storeId, string $offeringId, string $customerSelector, string $reason): Subscriber
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId) . '/subscribers/' . urlencode($customerSelector) . '/suspend';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'reason' => $reason
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new Subscriber(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function unsuspendSubscriber(string $storeId, string $offeringId, string $customerSelector): Subscriber
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId) . '/subscribers/' . urlencode($customerSelector) . '/unsuspend';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new Subscriber(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    // Credit endpoints

    public function getCredit(string $storeId, string $offeringId, string $customerSelector, string $currency): Credit
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId) . '/subscribers/' . urlencode($customerSelector) . '/credits/' . urlencode($currency);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new Credit(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function updateCredit(
        string $storeId,
        string $offeringId,
        string $customerSelector,
        string $currency,
        ?string $credit = null,
        ?string $charge = null,
        ?string $description = null,
        ?bool $allowOverdraft = null
    ): Credit {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/offerings/' . urlencode($offeringId) . '/subscribers/' . urlencode($customerSelector) . '/credits/' . urlencode($currency);
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'credit' => $credit,
                'charge' => $charge,
                'description' => $description,
                'allowOverdraft' => $allowOverdraft
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new Credit(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    // Plan checkout endpoints

    public function getPlanCheckout(string $checkoutId): PlanCheckout
    {
        $url = $this->getApiUrl() . 'plan-checkout/' . urlencode($checkoutId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new PlanCheckout(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function proceedPlanCheckout(string $checkoutId, ?string $email = null): PlanCheckout
    {
        $url = $this->getApiUrl() . 'plan-checkout/' . urlencode($checkoutId);
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $params = [];
        if ($email !== null) {
            $params['email'] = $email;
        }

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new PlanCheckout(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createPlanCheckout(
        string $storeId,
        string $offeringId,
        string $planId,
        ?string $customerSelector = null,
        ?int $durationMinutes = null,
        ?string $onPayBehavior = null,
        ?array $newSubscriberMetadata = null,
        ?array $invoiceMetadata = null,
        ?array $metadata = null,
        ?bool $isTrial = null,
        ?string $creditPurchase = null,
        ?string $successRedirectLink = null,
        ?string $newSubscriberEmail = null
    ): PlanCheckout {
        $url = $this->getApiUrl() . 'plan-checkout';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'storeId' => $storeId,
                'offeringId' => $offeringId,
                'planId' => $planId,
                'customerSelector' => $customerSelector,
                'durationMinutes' => $durationMinutes,
                'onPayBehavior' => $onPayBehavior,
                'newSubscriberMetadata' => $newSubscriberMetadata,
                'invoiceMetadata' => $invoiceMetadata,
                'metadata' => $metadata,
                'isTrial' => $isTrial,
                'creditPurchase' => $creditPurchase,
                'successRedirectLink' => $successRedirectLink,
                'newSubscriberEmail' => $newSubscriberEmail
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new PlanCheckout(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    // Portal session endpoints

    public function createPortalSession(
        string $storeId,
        string $offeringId,
        string $customerSelector,
        ?int $durationMinutes = null
    ): PortalSession {
        $url = $this->getApiUrl() . 'subscriber-portal';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'storeId' => $storeId,
                'offeringId' => $offeringId,
                'customerSelector' => $customerSelector,
                'durationMinutes' => $durationMinutes
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new PortalSession(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getPortalSession(string $portalSessionId): PortalSession
    {
        $url = $this->getApiUrl() . 'subscriber-portal/' . urlencode($portalSessionId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new PortalSession(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
