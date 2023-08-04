<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class WebhookDeliveryList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\WebhookDelivery[]
     */
    public function all(): array
    {
        $webhookDeliveries = [];
        foreach ($this->getData() as $webhookDelivery) {
            $webhookDeliveries[] = new \BTCPayServer\Result\WebhookDelivery($webhookDelivery);
        }
        return $webhookDeliveries;
    }
}
