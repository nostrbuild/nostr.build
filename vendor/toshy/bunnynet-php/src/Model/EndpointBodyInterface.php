<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model;

interface EndpointBodyInterface
{
    /**
     * @return array<AbstractParameter>
     */
    public function getBody(): array;
}
