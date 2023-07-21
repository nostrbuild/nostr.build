<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreOnChainWalletTransaction extends AbstractResult
{
    public function getDestinations(): StoreOnChainWalletTransactionDestinationList
    {
        $data = $this->getData();
        return new StoreOnChainWalletTransactionDestinationList($data['destinations']);
    }

    public function getFeeRate(): StoreOnChainWalletFeeRate
    {
        $data = $this->getData();
        return new StoreOnChainWalletFeeRate($data['feeRate']);
    }

    public function proceedWithPayjoin(): bool
    {
        $data = $this->getData();
        return $data['proceedWithPayjoin'];
    }

    public function proceedWithBroadcast(): bool
    {
        $data = $this->getData();
        return $data['proceedWithBroadcast'];
    }

    public function noChange(): bool
    {
        $data = $this->getData();
        return $data['noChange'];
    }

    public function rbf(): bool
    {
        $data = $this->getData();
        return $data['rbf'];
    }

    /**
     * @return array strings
     */
    public function selectedInputs(): array
    {
        $data = $this->getData();
        return $data['selectedInputs'];
    }
}
