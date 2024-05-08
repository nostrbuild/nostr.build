<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\StoreEmailSettings;

class StoreEmail extends AbstractClient
{
    public function getSettings(string $storeId): StoreEmailSettings
    {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/email';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new StoreEmailSettings(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }

    public function updateSettings(
        string $storeId,
        string $server,
        int $port,
        string $username,
        string $password,
        string $fromEmail,
        string $fromName,
        bool $disableCertificateCheck = false
    ): StoreEmailSettings {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/email';
        $headers = $this->getRequestHeaders();
        $method = 'PUT';

        $body = json_encode(
            [
                'server' => $server,
                'port' => $port,
                'login' => $username,
                'password' => $password,
                'from' => $fromEmail,
                'fromDisplay' => $fromName,
                'disableCertificateCheck' => $disableCertificateCheck
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return new StoreEmailSettings(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }


    public function sendMail(
        string $storeId,
        string $email,
        string $subject,
        string $body
    ): bool {
        $url = $this->getApiUrl() . 'stores/' . urlencode($storeId) . '/email/send';
        $headers = $this->getRequestHeaders();
        $method = 'POST';

        $body = json_encode(
            [
                'email' => $email,
                'subject' => $subject,
                'body' => $body
            ],
            JSON_THROW_ON_ERROR
        );

        $response = $this->getHttpClient()->request($method, $url, $headers, $body);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
