<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Model;

use ToshY\BunnyNet\Enum\Type;

class AbstractParameter implements ParameterInterface
{
    /**
     * @param string|null $name
     * @param Type $type
     * @param bool $required
     * @param array<AbstractParameter>|null $children
     */
    public function __construct(
        private readonly string|null $name,
        private readonly Type $type,
        private readonly bool $required = false,
        private readonly array|null $children = null,
    ) {
    }

    public function getName(): string|null
    {
        return $this->name;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array<AbstractParameter>|null
     */
    public function getChildren(): array|null
    {
        return $this->children;
    }
}
