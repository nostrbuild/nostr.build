<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\StorageZone;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class ListStorageZones implements EndpointInterface, EndpointQueryInterface
{
    public function getMethod(): Method
    {
        return Method::GET;
    }

    public function getPath(): string
    {
        return 'storagezone';
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
            new AbstractParameter(name: 'page', type: Type::INT_TYPE),
            new AbstractParameter(name: 'perPage', type: Type::INT_TYPE),
            new AbstractParameter(name: 'search', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'includeDeleted', type: Type::BOOLEAN_TYPE),
        ];
    }
}
