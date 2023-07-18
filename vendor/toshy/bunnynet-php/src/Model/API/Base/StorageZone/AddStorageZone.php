<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\StorageZone;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;

class AddStorageZone implements EndpointInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'storagezone';
    }

    public function getHeaders(): array
    {
        return [
            Header::ACCEPT_JSON,
            Header::CONTENT_TYPE_JSON,
        ];
    }

    public function getBody(): array
    {
        return [
            new AbstractParameter(name: 'OriginUrl', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Name', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'Region', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'ReplicationRegions', type: Type::ARRAY_TYPE, children: [
                new AbstractParameter(name: null, type: Type::STRING_TYPE),
            ]),
            new AbstractParameter(name: 'Region', type: Type::INT_TYPE, required: true),
        ];
    }
}
