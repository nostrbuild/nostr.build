<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class PullPaymentPayout extends AbstractResult
{
    public function getId(): string
    {
        $data = $this->getData();
        return $data['id'];
    }

    public function getRevision(): int
    {
        $data = $this->getData();
        return $data['revision'];
    }

    public function getPullPaymentId(): string
    {
        $data = $this->getData();
        return $data['pullPaymentId'];
    }

    public function getDate(): string
    {
        $data = $this->getData();
        return $data['date'];
    }

    public function getDestination(): string
    {
        $data = $this->getData();
        return $data['destination'];
    }

    public function getAmount(): PreciseNumber
    {
        $data = $this->getData();
        return PreciseNumber::parseString($data['amount']);
    }

    public function getPaymentMethod(): string
    {
        $data = $this->getData();
        return $data['paymentMethod'];
    }

    public function getCryptoCode(): string
    {
        $data = $this->getData();
        return $data['cryptoCode'];
    }

    public function getPaymentMethodAmount(): PreciseNumber
    {
        $data = $this->getData();
        return PreciseNumber::parseString($data['paymentMethodAmount']);
    }

    public function getState(): string
    {
        $data = $this->getData();
        return $data['state'];
    }
}
