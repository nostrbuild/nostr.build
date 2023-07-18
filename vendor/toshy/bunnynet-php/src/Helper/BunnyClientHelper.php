<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Helper;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use ToshY\BunnyNet\Enum\MimeType;
use ToshY\BunnyNet\Exception\BunnyClientResponseException;
use ToshY\BunnyNet\Exception\JSONException;
use ToshY\BunnyNet\Model\Client\BunnyClientResponse;

use ToshY\BunnyNet\Model\Client\Interface\BunnyClientResponseInterface;

use const JSON_BIGINT_AS_STRING;
use const JSON_THROW_ON_ERROR;

class BunnyClientHelper
{
    /**
     * @param string $template
     * @param array<int,mixed> $pathCollection
     * @return string
     */
    public static function createUrlPath(
        string $template,
        array $pathCollection,
    ): string {
        return sprintf(
            sprintf(
                '/%s',
                $template,
            ),
            ...$pathCollection,
        );
    }

    /**
     * @param array<string,mixed> $query
     * @return string|null
     */
    public static function createQuery(array $query): string|null
    {
        if (true === empty($query)) {
            return null;
        }

        foreach ($query as $key => $value) {
            if (false === is_bool($value)) {
                continue;
            }

            $query[$key] = $value ? 'true' : 'false';
        }

        return sprintf(
            '?%s',
            http_build_query(
                data: $query,
                arg_separator: '&',
                encoding_type: PHP_QUERY_RFC3986,
            ),
        );
    }

    /**
     * @throws BunnyClientResponseException
     * @throws JSONException
     * @return BunnyClientResponseInterface
     * @param RequestInterface $request
     * @param ResponseInterface $response
     */
    public static function parseResponse(
        RequestInterface $request,
        ResponseInterface $response,
    ): BunnyClientResponseInterface {
        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 400) {
            $body = (string) $response->getBody();

            throw new BunnyClientResponseException($body, $statusCode);
        }

        try {
            $contents = $response->getBody()->getContents();

            // For non-json contents, e.g. binary for video download, return early without even validating.
            if ($request->getHeaderLine('Accept') === MimeType::ALL) {
                return new BunnyClientResponse(
                    response: $response,
                    contents: $contents,
                );
            }

            if (self::isJson($contents) === true) {
                $contents = json_decode(
                    json: $contents,
                    associative: true,
                    flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
                );
            }
        } catch (Throwable $e) {
            throw new JSONException($e->getMessage().sprintf(' for "%s".', $request->getUri()), $e->getCode());
        }

        return new BunnyClientResponse(
            response: $response,
            contents: $contents,
        );
    }

    /**
     * @note Replace with json_validate in 8.3
     */
    private static function isJson(mixed $string): bool
    {
        json_decode($string);
        if (json_last_error() === JSON_ERROR_NONE) {
            return true;
        }

        return false;
    }
}
