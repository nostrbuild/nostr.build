<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\Search;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class GlobalSearch implements EndpointInterface, EndpointQueryInterface
{
    public function getMethod(): Method
    {
        return Method::GET;
    }

    public function getPath(): string
    {
        return 'search';
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
            new AbstractParameter(name: 'search', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'from', type: Type::INT_TYPE),
            new AbstractParameter(name: 'size', type: Type::INT_TYPE),
        ];
    }
}
