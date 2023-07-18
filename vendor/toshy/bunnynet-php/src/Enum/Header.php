<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Enum;

final class Header
{
    public const ACCEPT_JSON = [
        'Accept' => MimeType::JSON,
    ];

    public const ACCEPT_ALL = [
        'Accept' => MimeType::ALL,
    ];

    public const CONTENT_TYPE_JSON = [
        'Content-Type' => MimeType::JSON,
    ];

    public const CONTENT_TYPE_JSON_ALL = [
        'Content-Type' => MimeType::JSON_ALL,
    ];

    public const CONTENT_TYPE_OCTET_STREAM = [
        'Content-Type' => MimeType::OCTET_STREAM,
    ];
}
