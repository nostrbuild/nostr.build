<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\Compute;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointBodyInterface;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class PublishComputeScript implements EndpointInterface, EndpointQueryInterface, EndpointBodyInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'compute/script/%d/publish';
    }

    public function getHeaders(): array
    {
        return [
            Header::ACCEPT_JSON,
            Header::CONTENT_TYPE_JSON,
        ];
    }

    public function getQuery(): array
    {
        return [
            new AbstractParameter(name: 'uuid', type: Type::STRING_TYPE, required: true),
        ];
    }

    public function getBody(): array
    {
        return [
            new AbstractParameter(name: 'Note', type: Type::STRING_TYPE),
        ];
    }
}
