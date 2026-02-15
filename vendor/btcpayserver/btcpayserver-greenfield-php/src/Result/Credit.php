<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class Credit extends AbstractResult
{
    public function getCurrency(): string
    {
        return $this->getData()['currency'];
    }

    public function getValue(): string
    {
        return $this->getData()['value'];
    }
}
