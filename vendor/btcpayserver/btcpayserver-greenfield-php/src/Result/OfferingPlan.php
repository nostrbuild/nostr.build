<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class OfferingPlan extends AbstractResult
{
    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getName(): string
    {
        return $this->getData()['name'];
    }

    public function getStatus(): string
    {
        return $this->getData()['status'];
    }

    public function getPrice(): string
    {
        return $this->getData()['price'];
    }

    public function getCurrency(): string
    {
        return $this->getData()['currency'];
    }

    public function getRecurringType(): string
    {
        return $this->getData()['recurringType'];
    }

    public function getGracePeriodDays(): int
    {
        return $this->getData()['gracePeriodDays'];
    }

    public function getTrialDays(): int
    {
        return $this->getData()['trialDays'];
    }

    public function getDescription(): string
    {
        return $this->getData()['description'];
    }

    public function getMemberCount(): int
    {
        return $this->getData()['memberCount'];
    }

    public function isOptimisticActivation(): bool
    {
        return $this->getData()['optimisticActivation'];
    }

    public function isRenewable(): bool
    {
        return $this->getData()['renewable'];
    }

    /**
     * @return string[]
     */
    public function getFeatures(): array
    {
        return $this->getData()['features'] ?? [];
    }

    public function getMetadata(): ?array
    {
        return $this->getData()['metadata'] ?? null;
    }
}
