<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class LightningChannelList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\LightningChannel[]
     */
    public function all(): array
    {
        $channels = [];
        foreach ($this->getData() as $channel) {
            $channels[] = new \BTCPayServer\Result\LightningChannel($channel);
        }
        return $channels;
    }
}
