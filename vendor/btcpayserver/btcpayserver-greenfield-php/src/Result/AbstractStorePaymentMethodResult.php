<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

abstract class AbstractStorePaymentMethodResult extends AbstractResult
{
    public function __construct(array $data, string $paymentMethod = null)
    {
        // Temporary workaround until the api provides paymentMethod.
        if (!isset($data['paymentMethod'])) {
            $data['paymentMethod'] = $paymentMethod;
        }

        parent::__construct($data);
    }

    public function isEnabled(): bool
    {
        $data = $this->getData();
        return $data['enabled'];
    }
}
