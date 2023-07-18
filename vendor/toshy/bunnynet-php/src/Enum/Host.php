<?php

declare(strict_types=1);

namespace ToshY\BunnyNet\Enum;

final class Host
{
    /** @var string API endpoint */
    public const API_ENDPOINT = 'api.bunny.net';

    /** @var string Storage endpoint */
    public const STORAGE_ENDPOINT = 'storage.bunnycdn.com';

    /** @var string Video stream endpoint */
    public const STREAM_ENDPOINT = 'video.bunnycdn.com';

    /** @var string Logging endpoint */
    public const LOGGING_ENDPOINT = 'logging.bunnycdn.com';
}
