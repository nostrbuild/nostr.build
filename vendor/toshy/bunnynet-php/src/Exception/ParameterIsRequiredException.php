<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Exception;

use Exception;

class ParameterIsRequiredException extends Exception
{
    public const MESSAGE = 'The parameter key `%s` is required but not provided.';

    public static function withKey(
        string $key,
    ): self {
        return new self(
            sprintf(
                self::MESSAGE,
                $key,
            ),
        );
    }
}
