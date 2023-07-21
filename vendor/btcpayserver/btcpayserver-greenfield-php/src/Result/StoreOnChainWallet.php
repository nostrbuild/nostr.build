<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class StoreOnChainWallet extends AbstractResult
{
    public function getBalance(): PreciseNumber
    {
        $data = $this->getData();
        return PreciseNumber::parseString($data['balance']);
    }

    public function getUnconfirmedBalance(): PreciseNumber
    {
        $data = $this->getData();
        return PreciseNumber::parseString($data['unconfirmedBalance']);
    }

    public function getConfirmedBalance(): PreciseNumber
    {
        $data = $this->getData();
        return PreciseNumber::parseString($data['confirmedBalance']);
    }
}
