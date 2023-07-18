<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Exception;

use Exception;

class FileDoesNotExistException extends Exception
{
    public const MESSAGE = 'The specified file `%s` does not exist.';

    public static function withFileName(string $fileName): self
    {
        return new self(
            sprintf(
                self::MESSAGE,
                $fileName,
            ),
        );
    }
}
