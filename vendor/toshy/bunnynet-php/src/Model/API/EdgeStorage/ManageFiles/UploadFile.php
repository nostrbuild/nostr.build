<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\EdgeStorage\ManageFiles;

use ToshY\BunnyNet\Enum\Header;
use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Model\EndpointInterface;

class UploadFile implements EndpointInterface
{
    public function getMethod(): Method
    {
        return Method::PUT;
    }

    public function getPath(): string
    {
        return '%s/%s/%s';
    }

    public function getHeaders(): array
    {
        return [
            Header::CONTENT_TYPE_OCTET_STREAM,
        ];
    }
}
