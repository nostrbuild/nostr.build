<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model\API\Base\PullZone;

use ToshY\BunnyNet\Enum\Method;
use ToshY\BunnyNet\Model\EndpointInterface;

class DeleteEdgeRule implements EndpointInterface
{
    public function getMethod(): Method
    {
        return Method::DELETE;
    }

    public function getPath(): string
    {
        return 'pullzone/%d/edgerules/%d';
    }

    public function getHeaders(): array
    {
        return [];
    }
}
