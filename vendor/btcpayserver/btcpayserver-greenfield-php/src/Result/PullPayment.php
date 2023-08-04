<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class PullPayment extends AbstractResult
{
    public function getId(): string
    {
        $data = $this->getData();
        return $data['id'];
    }

    public function getName(): string
    {
        $data = $this->getData();
        return $data['name'];
    }

    public function getCurrency(): string
    {
        $data = $this->getData();
        return $data['currency'];
    }

    public function getAmount(): PreciseNumber
    {
        $data = $this->getData();
        return PreciseNumber::parseString($data['amount']);
    }

    public function getPeriod(): int
    {
        $data = $this->getData();
        return $data['period'];
    }

    public function getBOLT11Expiration(): int
    {
        $data = $this->getData();
        return $data['BOLT11Expiration'];
    }

    public function isArchived(): bool
    {
        $data = $this->getData();
        return $data['archived'];
    }

    public function getViewLink(): string
    {
        $data = $this->getData();
        return $data['viewLink'];
    }
}
