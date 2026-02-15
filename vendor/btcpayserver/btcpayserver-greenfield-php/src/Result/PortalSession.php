<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class PortalSession extends AbstractResult
{
    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getBaseUrl(): string
    {
        return $this->getData()['baseUrl'];
    }

    public function getSubscriber(): Subscriber
    {
        return new Subscriber($this->getData()['subscriber']);
    }

    public function getExpiration(): ?int
    {
        return $this->getData()['expiration'] ?? null;
    }

    public function isExpired(): bool
    {
        return $this->getData()['isExpired'];
    }

    public function getUrl(): string
    {
        return $this->getData()['url'];
    }
}
