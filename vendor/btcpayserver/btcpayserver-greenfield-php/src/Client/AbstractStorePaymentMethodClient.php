<?php

declare(strict_types=1);

namespace BTCPayServer\Client;

abstract class AbstractStorePaymentMethodClient extends AbstractClient
{
    public const PAYMENT_TYPE_ONCHAIN = 'OnChain';
    public const PAYMENT_TYPE_LIGHTNING = 'LightningNetwork';

    abstract public function getPaymentMethods(string $storeId): array;
    abstract public function getPaymentMethod(string $storeId, string $cryptoCode);
    abstract public function updatePaymentMethod(string $storeId, string $cryptoCode, array $settings);
    abstract public function removePaymentMethod(string $storeId, string $cryptoCode): bool;
}
