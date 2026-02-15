<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class Customer extends AbstractResult
{
    public function getStoreId(): string
    {
        return $this->getData()['storeId'];
    }

    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getExternalId(): ?string
    {
        return $this->getData()['externalId'] ?? null;
    }

    public function getIdentities(): ?array
    {
        return $this->getData()['identities'] ?? null;
    }

    public function getMetadata(): ?array
    {
        return $this->getData()['metadata'] ?? null;
    }
}
