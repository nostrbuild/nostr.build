<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\Statistics;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class GetStatistics implements EndpointInterface, EndpointQueryInterface
{
    public function getMethod(): Method
    {
        return Method::GET;
    }

    public function getPath(): string
    {
        return 'statistics';
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
            new AbstractParameter(name: 'pullZone', type: Type::INT_TYPE),
            new AbstractParameter(name: 'serverZoneId', type: Type::INT_TYPE),
            new AbstractParameter(name: 'loadErrors', type: Type::BOOLEAN_TYPE),
            new AbstractParameter(name: 'hourly', type: Type::BOOLEAN_TYPE),
        ];
    }
}
