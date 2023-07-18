<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\Support;

use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Model\EndpointInterface;

class CloseTicket implements EndpointInterface
{
    public function getMethod(): Method
    {
        return Method::POST;
    }

    public function getPath(): string
    {
        return 'support/ticket/%d/close';
    }

    public function getHeaders(): array
    {
        return [];
    }
}
