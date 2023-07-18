<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\Purge;

use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class PurgeURL implements EndpointInterface, EndpointQueryInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'purge';
    }

    public function getHeaders(): array
    {
        return [];
    }

    public function getQuery(): array
    {
        return [
            new AbstractParameter(name: 'url', type: Type::STRING_TYPE, required: true),
            new AbstractParameter(name: 'async', type: Type::BOOLEAN_TYPE),
        ];
    }
}
