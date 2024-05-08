<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class ApiKey extends AbstractResult
{
    public function getApiKey(): string
    {
        return $this->getData()['apiKey'];
    }

    public function getLabel(): string
    {
        return $this->getData()['label'];
    }

    public function getPermissions(): array
    {
        return $this->getData()['permissions'];
    }
}
