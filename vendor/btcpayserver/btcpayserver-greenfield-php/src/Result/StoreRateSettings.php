<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

use BTCPayServer\Util\PreciseNumber;

class StoreRateSettings extends AbstractResult
{
    public function getSpread(): PreciseNumber
    {
        return PreciseNumber::parseFloat($this->getData()['spread']);
    }

    public function hasCustomScript(): bool
    {
        return $this->getData()['isCustomScript'];
    }

    public function getEffectiveScript(): string
    {
        return $this->getData()['effectiveScript'];
    }

    public function preferredSource(): string
    {
        return $this->getData()['preferredSource'];
    }
}
