<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Client;

use Nyholm\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use ToshY\BunnyNet\Exception\BunnyClientResponseException;
use ToshY\BunnyNet\Exception\JSONException;
use ToshY\BunnyNet\Helper\BunnyClientHelper;
use ToshY\BunnyNet\Model\Client\Interface\BunnyClientResponseInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class BunnyClient
{
    protected const SCHEME = 'https';

    /**
     * @param ClientInterface $client
     * @param string|null $apiKey
     * @param string|null $baseUrl
     */
    public function __construct(
        protected readonly ClientInterface $client,
        protected string|null $apiKey = null,
        protected string|null $baseUrl = null,
    ) {
    }

    /**
     * @param string $apiKey
     * @return $this
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    /**
     * @param string $baseUrl
     * @return $this
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    /**
     * @throws BunnyClientResponseException
     * @throws ClientExceptionInterface
     * @throws JSONException
     * @param mixed|null $body
     * @param array<string,mixed> $headers
     * @return BunnyClientResponseInterface
     * @param EndpointInterface $endpoint
     * @param array<int,mixed> $parameters
     * @param array<string,mixed> $query
     */
    public function request(
        EndpointInterface $endpoint,
        array $parameters = [],
        array $query = [],
        mixed $body = null,
        array $headers = [],
    ): BunnyClientResponseInterface {
        $headers = array_filter(
            [
                ...array_merge(
                    $headers,
                    array_merge(...$endpoint->getHeaders()),
                    $this->getAccessKeyHeader(),
                ),
            ],
            fn ($value) => empty($value) === false,
        );

        $path = BunnyClientHelper::createUrlPath(
            template: $endpoint->getPath(),
            pathCollection: $parameters,
        );
        $query = BunnyClientHelper::createQuery(
            query: $query,
        );

        $url = sprintf(
            '%s://%s%s%s',
            self::SCHEME,
            $this->baseUrl,
            $path,
            $query,
        );

        $request = new Request(
            method: $endpoint->getMethod()->value,
            uri: $url,
            headers: $headers,
            body: $body,
        );

        $response = $this->client->sendRequest(
            request: $request,
        );

        return BunnyClientHelper::parseResponse(
            request: $request,
            response: $response,
        );
    }

    /**
     * @return string[]
     */
    private function getAccessKeyHeader(): array
    {
        return [
            'AccessKey' => $this->apiKey,
        ];
    }
}
