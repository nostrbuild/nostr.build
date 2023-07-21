<?php

declare(strict_types=1);

namespace BTCPayServer\Http;

use BTCPayServer\Exception\ConnectException;
use BTCPayServer\Exception\RequestException;

interface ClientInterface
{
    /**
     * Sends the HTTP request to API server.
     *
     * @param string $method
     * @param string $url
     * @param array  $headers
     * @param string $body
     *
     * @throws ConnectException
     * @throws RequestException
     *
     * @return ResponseInterface
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): ResponseInterface;
}
