<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Helper;

use Throwable;
use ToshY\BunnyNet\Exception\FileDoesNotExistException;
use ToshY\BunnyNet\Exception\JSONException;

use const JSON_THROW_ON_ERROR;

class BodyContentHelper
{
    /**
     * @throws FileDoesNotExistException
     * @return false|resource
     */
    public static function openFileStream(string $filePath)
    {
        $fileRealPath = realpath($filePath);
        if (false === $fileRealPath) {
            throw FileDoesNotExistException::withFileName(
                fileName: $filePath,
            );
        }

        return fopen($fileRealPath, 'r');
    }

    /**
     * @throws JSONException
     * @return mixed
     * @param mixed $body
     */
    public static function getBody(mixed $body): mixed
    {
        if (false === is_array($body)) {
            return $body;
        }

        try {
            $jsonBody = json_encode(
                value: $body,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (Throwable $e) {
            throw new JSONException(
                $e->getMessage(),
            );
        }

        return $jsonBody;
    }
}
