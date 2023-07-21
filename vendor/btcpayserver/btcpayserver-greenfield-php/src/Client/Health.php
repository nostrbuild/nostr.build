<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

class Health extends AbstractClient
{
    public function getHealthStatus(): bool
    {
        $url = $this->getApiUrl() . 'health';
        $headers = $this->getRequestHeaders();
        $method = 'GET';

        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return true;
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
