<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreRate extends AbstractResult
{
    public function getCurrencyPair(): string
    {
        return $this->getData()['currencyPair'];
    }

    public function getRate(): string
    {
        return $this->getData()['rate'];
    }

    public function getErrors(): ?array
    {
        return $this->getData()['errors'];
    }

    public function hasRate(): bool
    {
        return !empty($this->getData()['rate']) && empty($this->getData()['errors']);
    }
}
