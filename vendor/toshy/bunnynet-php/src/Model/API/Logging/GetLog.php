<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Logging;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class GetLog implements EndpointInterface, EndpointQueryInterface
{
    public function getMethod(): Method
    {
        return Method::GET;
    }

    public function getPath(): string
    {
        return '%s/%d.log';
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
            new AbstractParameter(name: 'start', type: Type::INT_TYPE),
            new AbstractParameter(name: 'end', type: Type::INT_TYPE),
            new AbstractParameter(name: 'order', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'status', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'search', type: Type::STRING_TYPE),
        ];
    }
}
