<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model;

use ToshY\BunnyNet\Enum\Type;

interface ParameterInterface
{
    /**
     * @return string|null
     */
    public function getName(): string|null;

    /**
     * @return Type
     */
    public function getType(): Type;

    /**
     * @return bool
     */
    public function isRequired(): bool;
}
