<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model;

use ToshY\BunnyNet\Enum\Method;

interface EndpointInterface
{
    /**
     * @return Method
     */
    public function getMethod(): Method;

    /**
     * @return string
     */
    public function getPath(): string;

    /**
     * @return array<array<string,string>>
     */
    public function getHeaders(): array;
}
