<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreOnChainWalletFeeRate extends AbstractResult
{
    public function getFeeRate(): float
    {
        $data = $this->getData();
        return $data['feeRate'];
    }
}
