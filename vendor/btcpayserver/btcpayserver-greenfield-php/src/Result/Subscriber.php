<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class Subscriber extends AbstractResult
{
    public function getCreated(): int
    {
        return $this->getData()['created'];
    }

    public function getCustomer(): Customer
    {
        return new Customer($this->getData()['customer']);
    }

    public function getOffering(): Offering
    {
        return new Offering($this->getData()['offering']);
    }

    public function getPlan(): OfferingPlan
    {
        return new OfferingPlan($this->getData()['plan']);
    }

    public function getPeriodEnd(): ?int
    {
        return $this->getData()['periodEnd'] ?? null;
    }

    public function getTrialEnd(): ?int
    {
        return $this->getData()['trialEnd'] ?? null;
    }

    public function getGracePeriodEnd(): ?int
    {
        return $this->getData()['gracePeriodEnd'] ?? null;
    }

    public function isActive(): bool
    {
        return $this->getData()['isActive'];
    }

    public function isSuspended(): bool
    {
        return $this->getData()['isSuspended'];
    }

    public function getSuspensionReason(): ?string
    {
        return $this->getData()['suspensionReason'] ?? null;
    }

    public function isAutoRenew(): bool
    {
        return $this->getData()['autoRenew'];
    }

    public function getMetadata(): ?array
    {
        return $this->getData()['metadata'] ?? null;
    }

    public function getProcessingInvoiceId(): ?string
    {
        return $this->getData()['processingInvoiceId'] ?? null;
    }

    public function getNextPlan(): ?OfferingPlan
    {
        return isset($this->getData()['nextPlan']) ? new OfferingPlan($this->getData()['nextPlan']) : null;
    }

    public function getPhase(): string
    {
        return $this->getData()['phase'];
    }
}
