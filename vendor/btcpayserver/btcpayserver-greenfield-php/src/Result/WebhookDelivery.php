<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class WebhookDelivery extends AbstractResult
{
    public function getId(): string
    {
        $data = $this->getData();
        return $data['id'];
    }

    public function getTimestamp(): int
    {
        $data = $this->getData();
        return $data['timestamp'];
    }

    public function getHttpCode(): int
    {
        $data = $this->getData();
        return $data['httpCode'];
    }

    public function getErrorMessage(): string
    {
        $data = $this->getData();
        return $data['errorMessage'];
    }

    public function getStatus(): string
    {
        $data = $this->getData();
        return $data['status'];
    }
}
