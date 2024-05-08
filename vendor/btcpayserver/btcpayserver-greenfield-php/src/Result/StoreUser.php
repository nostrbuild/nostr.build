<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreUser extends AbstractResult
{
    public function getUserId(): string
    {
        $data = $this->getData();
        return $data['userId'];
    }

    public function getRole(): string
    {
        $data = $this->getData();
        return $data['role'];
    }
}
