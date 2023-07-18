<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Exception;

use Exception;
use ToshY\BunnyNet\Enum\Type;

class InvalidTypeForListValueException extends Exception
{
    public const MESSAGE = 'Key `%s` expected list value of type `%s` got `%s` (%s).';

    public static function withParentKeyValueType(
        string $parentKey,
        Type $expectedValueType,
        mixed $actualValue,
    ): self {
        return new self(
            sprintf(
                self::MESSAGE,
                $parentKey,
                $expectedValueType->value,
                gettype($actualValue),
                is_array($actualValue) === true ? json_encode($actualValue) : $actualValue,
            ),
        );
    }
}
