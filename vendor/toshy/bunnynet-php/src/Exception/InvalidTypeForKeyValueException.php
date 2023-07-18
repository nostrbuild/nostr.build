<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Exception;

use Exception;
use ToshY\BunnyNet\Enum\Type;

class InvalidTypeForKeyValueException extends Exception
{
    public const MESSAGE = 'Key `%s` expected value of type `%s` got `%s` (%s).';

    public static function withKeyValueType(
        string $key,
        Type $expectedValueType,
        mixed $actualValue,
    ): self {
        return new self(
            sprintf(
                self::MESSAGE,
                $key,
                $expectedValueType->value,
                gettype($actualValue),
                is_array($actualValue) === true ? json_encode($actualValue) : $actualValue,
            ),
        );
    }
}
