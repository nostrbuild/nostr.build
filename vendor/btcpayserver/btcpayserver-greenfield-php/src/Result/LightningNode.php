<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class LightningNode extends AbstractResult
{
    /**
     * @return array strings
     */
    public function getNodeURIs(): array
    {
        return $this->getData()['nodeURIs'];
    }

    public function getBlockHeight(): int
    {
        return $this->getData()['blockHeight'];
    }

    public function getAlias(): string
    {
        return $this->getData()['alias'];
    }

    public function getColor(): string
    {
        return $this->getData()['color'];
    }

    public function getVersion(): string
    {
        return $this->getData()['version'];
    }

    public function getPeersCount(): int
    {
        return $this->getData()['peersCount'];
    }

    public function getActiveChannelsCount(): int
    {
        return $this->getData()['activeChannelsCount'];
    }

    public function getInactiveChannelsCount(): int
    {
        return $this->getData()['inactiveChannelsCount'];
    }

    public function getPendingChannelsCount(): int
    {
        return $this->getData()['pendingChannelsCount'];
    }
}
