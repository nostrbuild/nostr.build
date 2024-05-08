<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreUserList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\StoreUser[]
     */
    public function all(): array
    {
        $storeUsers = [];
        foreach ($this->getData() as $userData) {
            $storeUsers[] = new StoreUser($userData);
        }
        return $storeUsers;
    }
}
