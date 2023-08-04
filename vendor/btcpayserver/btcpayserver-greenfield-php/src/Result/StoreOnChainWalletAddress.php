<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreOnChainWalletAddress extends AbstractResult
{
    public function getAddress(): string
    {
        $data = $this->getData();
        return $data['address'];
    }

    public function getKeyPath(): string
    {
        $data = $this->getData();
        return $data['keyPath'];
    }

    public function getPaymentLink(): string
    {
        $data = $this->getData();
        return $data['paymentLink'];
    }
}
