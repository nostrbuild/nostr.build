<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class PlanCheckout extends AbstractResult
{
    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getSubscriber(): ?Subscriber
    {
        return isset($this->getData()['subscriber']) ? new Subscriber($this->getData()['subscriber']) : null;
    }

    public function getPlan(): OfferingPlan
    {
        return new OfferingPlan($this->getData()['plan']);
    }

    public function getBaseUrl(): string
    {
        return $this->getData()['baseUrl'];
    }

    public function getInvoiceId(): ?string
    {
        return $this->getData()['invoiceId'] ?? null;
    }

    public function getSuccessRedirectUrl(): ?string
    {
        return $this->getData()['successRedirectUrl'] ?? null;
    }

    public function getExpiration(): int
    {
        return $this->getData()['expiration'];
    }

    public function getRedirectUrl(): string
    {
        return $this->getData()['redirectUrl'];
    }

    public function getInvoiceMetadata(): ?array
    {
        return $this->getData()['invoiceMetadata'] ?? null;
    }

    public function getMetadata(): ?array
    {
        return $this->getData()['metadata'] ?? null;
    }

    public function isNewSubscriber(): bool
    {
        return $this->getData()['newSubscriber'];
    }

    public function isTrial(): bool
    {
        return $this->getData()['isTrial'];
    }

    public function getCreated(): int
    {
        return $this->getData()['created'];
    }

    public function isPlanStarted(): bool
    {
        return $this->getData()['planStarted'];
    }

    public function getNewSubscriberMetadata(): ?array
    {
        return $this->getData()['newSubscriberMetadata'] ?? null;
    }

    public function getRefundAmount(): ?string
    {
        return $this->getData()['refundAmount'] ?? null;
    }

    public function getCreditedByInvoice(): ?string
    {
        return $this->getData()['creditedByInvoice'] ?? null;
    }

    public function getOnPayBehavior(): ?string
    {
        return $this->getData()['onPayBehavior'] ?? null;
    }

    public function isExpired(): bool
    {
        return $this->getData()['isExpired'];
    }

    public function getUrl(): string
    {
        return $this->getData()['url'];
    }

    public function getCreditPurchase(): ?string
    {
        return $this->getData()['creditPurchase'] ?? null;
    }
}
