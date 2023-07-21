<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class StoreOnChainWalletTransactionDestination extends AbstractResult
{
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

    public function subtractFromAmount(): bool
    {
        $data = $this->getData();
        return $data['subtractFromAmount'];
    }
}
