<?php

declare(strict_types=1);

namespace BTCPayServer\Exception;

class ConnectException extends BTCPayException
{
    public function __construct(string $curlErrorMessage, int $curlErrorCode)
    {
        parent::__construct($curlErrorMessage, $curlErrorCode);
    }
}
