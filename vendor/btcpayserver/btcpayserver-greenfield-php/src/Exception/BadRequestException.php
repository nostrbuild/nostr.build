<?php

declare(strict_types=1);

namespace BTCPayServer\Exception;

class BadRequestException extends RequestException
{
    public const STATUS = 400;
}
