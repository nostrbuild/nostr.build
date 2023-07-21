<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\Webhook as ResultWebhook;
use BTCPayServer\Result\WebhookCreated;
use BTCPayServer\Result\WebhookDelivery;
use BTCPayServer\Result\WebhookDeliveryList;
use BTCPayServer\Result\WebhookList;

class Webhook extends AbstractClient
{
    /**
     * @param string $storeId
     * @return WebhookList
     */
    public function getStoreWebhooks(string $storeId): WebhookList
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new WebhookList(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getWebhook(string $storeId, string $webhookId): ResultWebhook
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks/' . urlencode($webhookId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return new ResultWebhook($data);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getLatestDeliveries(string $storeId, string $webhookId, string $count): WebhookDeliveryList
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks/' . urlencode($webhookId) . '/deliveries?count=' . $count;
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return new WebhookDeliveryList($data);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getDelivery(string $storeId, string $webhookId, string $deliveryId): WebhookDelivery
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks/' . urlencode($webhookId) . '/deliveries/' . urlencode($deliveryId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return new WebhookDelivery($data);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * Get the delivery's request.
     *
     * The delivery's JSON request sent to the endpoint.
     *
     * @param string $storeId
     * @param string $webhookId
     * @param string $deliveryId
     * @return string JSON request
     */
    public function getDeliveryRequest(string $storeId, string $webhookId, string $deliveryId): string
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks/' . urlencode($webhookId) . '/deliveries/' . urlencode($deliveryId);
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return $response->getBody();
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * Redeliver the delivery.
     *
     * @param string $storeId
     * @param string $webhookId
     * @param string $deliveryId
     * @return string The new delivery id being broadcasted. (Broadcast happen asynchronously with this call)
     */
    public function redeliverDelivery(string $storeId, string $webhookId, string $deliveryId): string
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks/' .
                        urlencode($webhookId) . '/deliveries/' . urlencode($deliveryId) . '/redeliver';
        $headers = $this->getRequestHeaders();
        $method = 'POST';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function createWebhook(
        string $storeId,
        string $url,
        ?array $specificEvents,
        ?string $secret,
        bool $enabled = true,
        bool $automaticRedelivery = true
    ): WebhookCreated {
        $data = [
            'enabled' => $enabled,
            'automaticRedelivery' => $automaticRedelivery,
            'url' => $url
        ];

        if ($specificEvents === null) {
            $data['authorizedEvents'] = [
                'everything' => true
            ];
        } elseif (count($specificEvents) === 0) {
            throw new \InvalidArgumentException('Argument $specificEvents should be NULL or contains at least 1 item.');
        } else {
            $data['authorizedEvents'] = [
                'everything' => false,
                'specificEvents' => $specificEvents
            ];
        }

        if ($secret === '') {
            throw new \InvalidArgumentException('Argument $secret should be NULL (let BTCPay Server auto-generate a secret) or you should provide a long and safe secret string.');
        } elseif ($secret !== null) {
            $data['secret'] = $secret;
        }

        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks';
        $headers = $this->getRequestHeaders();
        $method = 'POST';
        $response = $this->getHttpClient()->request($method, $url, $headers, json_encode($data, JSON_THROW_ON_ERROR));

        if ($response->getStatus() === 200) {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return new WebhookCreated($data);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * Updates an existing webhook.
     *
     * Important: due to a bug in BTCPay Server versions <= 1.6.3.0 you need
     * to pass the $secret explicitly as it would overwrite your existing secret
     * otherwise. On newer versions BTCPay Server >= 1.6.4.0, if you do NOT set
     * a secret it won't change it and everything will continue to work.
     *
     * @see https://github.com/btcpayserver/btcpayserver/issues/4010
     *
     * @return ResultWebhook
     * @throws \JsonException
     */
    public function updateWebhook(
        string $storeId,
        string $url,
        string $webhookId,
        ?array $specificEvents,
        bool $enabled = true,
        bool $automaticRedelivery = true,
        ?string $secret = null
    ): ResultWebhook {
        $data = [
          'enabled' => $enabled,
          'automaticRedelivery' => $automaticRedelivery,
          'url' => $url,
          'secret' => $secret
        ];

        // Specific events or all.
        if ($specificEvents === null) {
            $data['authorizedEvents'] = [
              'everything' => true
            ];
        } elseif (count($specificEvents) === 0) {
            throw new \InvalidArgumentException('Argument $specificEvents should be NULL or contains at least 1 item.');
        } else {
            $data['authorizedEvents'] = [
              'everything' => false,
              'specificEvents' => $specificEvents
            ];
        }

        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks/' . urlencode($webhookId);
        $headers = $this->getRequestHeaders();
        $method = 'PUT';
        $response = $this->getHttpClient()->request($method, $url, $headers, json_encode($data, JSON_THROW_ON_ERROR));

        if ($response->getStatus() === 200) {
            $data = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            return new ResultWebhook($data);
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    /**
     * Check if the request your received from a webhook is authentic and can be trusted.
     * @param string $requestBody Most likely you will use `$requestBody = file_get_contents('php://input');`
     * @param string $btcpaySigHeader Most likely you will use `$_SERVER['HTTP_BTCPay-Sig']` for this.
     * @param string $secret The secret that's registered with the webhook in BTCPay Server as a security precaution.
     * @return bool
     */
    public static function isIncomingWebhookRequestValid(string $requestBody, string $btcpaySigHeader, string $secret): bool
    {
        if ($requestBody && $btcpaySigHeader) {
            $expectedHeader = 'sha256=' . hash_hmac('sha256', $requestBody, $secret);

            if ($expectedHeader === $btcpaySigHeader) {
                return true;
            }
        }
        return false;
    }

    public function deleteWebhook(string $storeId, string $webhookId): void
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/webhooks/' . urlencode($webhookId);
        $headers = $this->getRequestHeaders();
        $method = 'DELETE';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() !== 200) {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
