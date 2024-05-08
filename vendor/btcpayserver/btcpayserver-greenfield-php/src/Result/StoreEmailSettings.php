<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class StoreEmailSettings extends AbstractResult
{
    public function getServer(): string
    {
        $data = $this->getData();
        return $data['server'];
    }

    public function getPort(): string
    {
        $data = $this->getData();
        return $data['port'];
    }

    public function getUsername(): string
    {
        $data = $this->getData();
        return $data['login'];
    }

    public function getPassword(): string
    {
        $data = $this->getData();
        return $data['password'];
    }

    public function getFromEmail(): string
    {
        $data = $this->getData();
        return $data['from'];
    }

    public function getFromName(): string
    {
        $data = $this->getData();
        return $data['fromDisplay'];
    }
}
