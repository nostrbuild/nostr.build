<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class LightningPayment extends AbstractResult
{
    public function getId(): ?string
    {
        return $this->getData()['id'];
    }

    public function getStatus(): string
    {
        return $this->getData()['status'];
    }

    public function getBolt11(): ?string
    {
        return $this->getData()['BOLT11'];
    }

    public function getPaymentHash(): ?string
    {
        return $this->getData()['paymentHash'];
    }

    public function getPreimage(): ?string
    {
        return $this->getData()['preimage'];
    }

    public function getCreatedAt(): ?int
    {
        return $this->getData()['createdAt'];
    }

    public function getTotalAmount(): PreciseNumber
    {
        return PreciseNumber::parseString($this->getData()['totalAmount']);
    }

    public function getFeeAmount(): PreciseNumber
    {
        return PreciseNumber::parseString($this->getData()['feeAmount']);
    }
}
