<?php

declare(strict_types=1);

namespace ToshY\BunnyNet;

use DateTimeInterface;
use Psr\Http\Client\ClientExceptionInterface;
use ToshY\BunnyNet\Client\BunnyClient;
use ToshY\BunnyNet\Enum\Host;
use ToshY\BunnyNet\Model\API\Logging\GetLog;
use ToshY\BunnyNet\Model\Client\Interface\BunnyClientResponseInterface;
use ToshY\BunnyNet\Validator\ParameterValidator;

class LoggingAPI
{
    /**
     * @param string $apiKey
     * @param BunnyClient $client
     */
    public function __construct(
        protected readonly string $apiKey,
        protected readonly BunnyClient $client,
    ) {
        $this->client
            ->setApiKey($this->apiKey)
            ->setBaseUrl(Host::LOGGING_ENDPOINT);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws Exception\BunnyClientResponseException
     * @throws Exception\JSONException
     * @throws Exception\InvalidTypeForKeyValueException
     * @throws Exception\InvalidTypeForListValueException
     * @throws Exception\ParameterIsRequiredException
     * @param int $pullZoneId
     * @param DateTimeInterface $dateTime
     * @param array<string,mixed> $query
     * @return BunnyClientResponseInterface
     */
    public function getLog(
        int $pullZoneId,
        DateTimeInterface $dateTime,
        array $query = [],
    ): BunnyClientResponseInterface {
        $endpoint = new GetLog();
        $dateTimeFormat = $dateTime->format('m-d-y');

        ParameterValidator::validate($query, $endpoint->getQuery());

        return $this->client->request(
            endpoint: $endpoint,
            parameters: [$dateTimeFormat, $pullZoneId],
            query: $query,
        );
    }
}
