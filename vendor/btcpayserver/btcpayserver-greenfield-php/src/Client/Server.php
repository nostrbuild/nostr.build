<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

use BTCPayServer\Result\ServerInfo;

class Server extends AbstractClient
{
    public function getInfo(): ServerInfo
    {
        $url = $this->getApiUrl() . 'server/info';
        $headers = $this->getRequestHeaders();
        $method = 'GET';
        $response = $this->getHttpClient()->request($method, $url, $headers);

        if ($response->getStatus() === 200) {
            return new ServerInfo(json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR));
        } else {
            throw $this->getExceptionByStatusCode($method, $url, $response);
        }
    }
}
