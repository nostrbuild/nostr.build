<?php

declare(strict_types=1);

namespace ToshY\BunnyNet;

class ImageProcessor
{
    /**
     * @param string $url
     * @param array<string,mixed> $optimization
     * @return string
     */
    public function generate(
        string $url,
        array $optimization = [],
    ): string {
        if (true === empty($optimization)) {
            return $url;
        }

        foreach ($optimization as $key => $value) {
            if (false === is_bool($value)) {
                continue;
            }

            $optimization[$key] = $value ? 'true' : 'false';
        }

        $query = http_build_query(
            data: $optimization,
            arg_separator: '&',
            encoding_type: PHP_QUERY_RFC3986,
        );

        return sprintf('%s?%s', $url, $query);
    }
}
