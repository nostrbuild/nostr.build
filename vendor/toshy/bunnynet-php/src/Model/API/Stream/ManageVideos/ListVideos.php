<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Stream\ManageVideos;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Enum\Type;
use ToshY\BunnyNet\Model\AbstractParameter;
use ToshY\BunnyNet\Model\EndpointInterface;
use ToshY\BunnyNet\Model\EndpointQueryInterface;

class ListVideos implements EndpointInterface, EndpointQueryInterface
{
    public function getMethod(): Method
    {
        return Method::GET;
    }

    public function getPath(): string
    {
        return 'library/%d/videos';
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
            new AbstractParameter(name: 'itemsPerPage', type: Type::INT_TYPE),
            new AbstractParameter(name: 'search', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'collection', type: Type::STRING_TYPE),
            new AbstractParameter(name: 'orderBy', type: Type::STRING_TYPE),
        ];
    }
}
