<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model;

interface EndpointQueryInterface
{
    /**
     * @return array<AbstractParameter>
     */
    public function getQuery(): array;
}
