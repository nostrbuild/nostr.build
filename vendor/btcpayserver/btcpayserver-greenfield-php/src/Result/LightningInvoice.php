<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class LightningInvoice extends AbstractResult
{
    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getStatus(): string
    {
        return $this->getData()['status'];
    }

    public function getBolt11(): string
    {
        return $this->getData()['BOLT11'];
    }

    public function getPaidAt(): ?int
    {
        return $this->getData()['paidAt'];
    }

    public function getExpiresAt(): int
    {
        return $this->getData()['expiresAt'];
    }

    public function getAmount(): PreciseNumber
    {
        return PreciseNumber::parseString($this->getData()['amount']);
    }

    public function getAmountReceived(): ?PreciseNumber
    {
        return ($this->getData()['amountReceived'] === null) ? null : PreciseNumber::parseString($this->getData()['amountReceived']);
    }
}
