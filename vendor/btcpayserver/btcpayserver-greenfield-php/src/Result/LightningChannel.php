<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class LightningChannel extends AbstractResult
{
    public function getRemoteNode(): string
    {
        $data = $this->getData();
        return $data['remoteNode'];
    }

    public function isPublic(): bool
    {
        $data = $this->getData();
        return $data['isPublic'];
    }

    public function isActive(): bool
    {
        $data = $this->getData();
        return $data['isActive'];
    }

    public function getCapacity(): string
    {
        $data = $this->getData();
        return $data['capacity'];
    }

    public function getLocalBalance(): string
    {
        $data = $this->getData();
        return $data['localBalance'];
    }

    public function getChannelPoint(): string
    {
        $data = $this->getData();
        return $data['channelPoint'];
    }
}
