<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\StreamVideoLibrary;

use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Model\EndpointInterface;

class ResetPasswordByPathParameter implements EndpointInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'videolibrary/%d/resetApiKey';
    }

    public function getHeaders(): array
    {
        return [];
    }
}
