<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class Invoice extends AbstractResult
{
    public const STATUS_NEW = 'New';

    public const STATUS_INVALID = 'Invalid';

    public const STATUS_SETTLED = 'Settled';

    public const STATUS_EXPIRED = 'Expired';

    public const STATUS_PROCESSING = 'Processing';

    public const ADDITIONAL_STATUS_PAID_PARTIAL = 'PaidPartial';

    public const ADDITIONAL_STATUS_PAID_OVER = 'PaidOver';

    public const ADDITIONAL_STATUS_MARKED = 'Marked';

    public const ADDITIONAL_STATUS_PAID_LATE = 'PaidLate';

    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getAmount(): PreciseNumber
    {
        return PreciseNumber::parseString($this->getData()['amount']);
    }

    public function getCurrency(): string
    {
        return $this->getData()['currency'];
    }

    public function getType(): string
    {
        return $this->getData()['type'];
    }

    public function getCheckoutLink(): string
    {
        return $this->getData()['checkoutLink'];
    }

    public function getCreatedTime(): int
    {
        return $this->getData()['createdTime'];
    }

    public function getExpirationTime(): int
    {
        return $this->getData()['expirationTime'];
    }

    public function getMonitoringTime(): int
    {
        return $this->getData()['monitoringTime'];
    }

    public function isArchived(): bool
    {
        return $this->getData()['archived'];
    }

    public function isNew(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_NEW;
    }

    public function isSettled(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_SETTLED || $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_LATE;
    }

    public function getStatus(): string
    {
        return $this->getData()['status'];
    }

    public function isExpired(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_EXPIRED;
    }

    public function isProcessing(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_PROCESSING;
    }

    public function isInvalid(): bool
    {
        $data = $this->getData();
        return $data['status'] === self::STATUS_INVALID;
    }

    public function isOverpaid(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_OVER;
    }

    public function isPartiallyPaid(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_PARTIAL;
    }

    public function isMarked(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_MARKED;
    }

    public function isPaidLate(): bool
    {
        $data = $this->getData();
        return $data['additionalStatus'] === self::ADDITIONAL_STATUS_PAID_LATE;
    }

    /**
     * Get the statuses you can use to manually mark this invoice.
     * Available since BTCPay Server version x.x.x
     * @return string[] Example: ["Settled", "Invalid"]
     */
    public function getAvailableStatusesForManualMarking(): array
    {
        return $this->getData()['availableStatusesForManualMarking'];
    }
}
