<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\Notification as ResultNotification;
use BTCPayServer\Result\NotificationList;

class Notification extends AbstractClient
{
    public function getNotifications(
        ?string $seen = null,
        ?int $skip = null,
        ?int $take = null
    ): NotificationList {
        $url = $this->getApiUrl() . 'users/me/notifications/?';

        $queryParameters = [
            'seen' => $seen,
            'skip' => $skip,
            'take' => $take
        ];

        $url .= http_build_query($queryParameters);

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new NotificationList(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function getNotification(string $id): ResultNotification
    {
        $url = $this->getApiUrl() . 'users/me/notifications/' . urlencode($id);

        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ResultNotification(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function updateNotification(string $id, ?bool $seen): ResultNotification
    {
        $url = $this->getApiUrl() . 'users/me/notifications/' . urlencode($id);

        $headers = $this->getRequestHeaders();
        $method = 'PUT';

        $body = json_encode(
            [
                'seen' => $seen,
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new ResultNotification(
                json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR)
            );
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function removeNotification(string $id): bool
    {
        $url = $this->getApiUrl() . 'users/me/notifications/' . urlencode($id);

        $headers = $this->getRequestHeaders();
        $method = 'DELETE';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
