<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreRateList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\StoreRate[]
     */
    public function all(): array
    {
        $storeRates = [];
        foreach ($this->getData() as $rate) {
            $storeRates[] = new StoreRate($rate);
        }
        return $storeRates;
    }
}
