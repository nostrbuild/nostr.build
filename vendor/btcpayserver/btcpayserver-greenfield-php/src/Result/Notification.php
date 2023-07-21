<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class Notification extends AbstractResult
{
    public function getId(): string
    {
        $data = $this->getData();
        return $data['id'];
    }

    public function getBody(): string
    {
        $data = $this->getData();
        return $data['body'];
    }

    public function getLink(): string
    {
        $data = $this->getData();
        return $data['link'];
    }

    public function getCreatedTime(): int
    {
        $data = $this->getData();
        return $data['createdTime'];
    }

    public function isSeen(): bool
    {
        $data = $this->getData();
        return $data['seen'];
    }
}
