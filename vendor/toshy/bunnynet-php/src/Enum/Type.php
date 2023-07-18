<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Enum;

enum Type: string
{
    case BOOLEAN_TYPE = 'bool';
    case INT_TYPE = 'int';
    case NUMERIC_TYPE = 'numeric';
    case STRING_TYPE = 'string';
    case ARRAY_TYPE = 'array';
}
