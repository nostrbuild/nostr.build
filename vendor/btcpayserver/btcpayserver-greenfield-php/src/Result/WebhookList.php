<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class WebhookList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\Webhook[]
     */
    public function all(): array
    {
        $webhooks = [];
        foreach ($this->getData() as $webhook) {
            $webhooks[] = new \BTCPayServer\Result\Webhook($webhook);
        }
        return $webhooks;
    }
}
