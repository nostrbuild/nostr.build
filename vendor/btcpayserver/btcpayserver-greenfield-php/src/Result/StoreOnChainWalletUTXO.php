<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class StoreOnChainWalletUTXO extends AbstractResult
{
    public function getComment(): string
    {
        $data = $this->getData();
        return $data['comment'];
    }

    public function getAmount(): PreciseNumber
    {
        $data = $this->getData();
        return PreciseNumber::parseString($data['amount']);
    }

    public function getLink(): string
    {
        $data = $this->getData();
        return $data['link'];
    }

    public function getOutpoint(): string
    {
        $data = $this->getData();
        return $data['outpoint'];
    }

    public function getTimestamp(): int
    {
        $data = $this->getData();
        return $data['timestamp'];
    }

    public function getKeyPath(): string
    {
        $data = $this->getData();
        return $data['keyPath'];
    }

    public function getAddress(): string
    {
        $data = $this->getData();
        return $data['address'];
    }

    public function getConfirmations(): int
    {
        $data = $this->getData();
        return $data['confirmations'];
    }

    public function getLabels(): array
    {
        $data = $this->getData();
        return $data['labels'];
    }
}
