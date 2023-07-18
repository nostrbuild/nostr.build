<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\PullZone;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class GetOriginShieldQueueStatistics implements EndpointInterface, EndpointQueryInterface
{
    public function getMethod(): Method
    {
        return Method::GET;
    }

    public function getPath(): string
    {
        return 'pullzone/%d/originshield/queuestatistics';
    }

    public function getHeaders(): array
    {
        return [
            Header::ACCEPT_JSON,
        ];
    }

    public function getQuery(): array
    {
        return [
            new AbstractParameter(name: 'dateFrom', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'dateTo', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'hourly', type: Type::BOOLEAN_TYPE),
        ];
    }
}
