<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreOnChainWalletUTXOList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\StoreOnChainWalletUTXO[]
     */
    public function all(): array
    {
        $storeWalletUTXOs = [];
        foreach ($this->getData() as $storeWalletUTXO) {
            $storeWalletUTXOs[] = new \BTCPayServer\Result\StoreOnChainWalletUTXO($storeWalletUTXO);
        }
        return $storeWalletUTXOs;
    }
}
