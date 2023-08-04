<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class PullPaymentList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\PullPayment[]
     */
    public function all(): array
    {
        $pullPayments = [];
        foreach ($this->getData() as $pullPaymentData) {
            $pullPayments[] = new \BTCPayServer\Result\PullPayment($pullPaymentData);
        }
        return $pullPayments;
    }
}
