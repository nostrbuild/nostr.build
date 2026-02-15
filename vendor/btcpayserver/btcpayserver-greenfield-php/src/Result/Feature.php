<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class Feature extends AbstractResult
{
    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getDescription(): string
    {
        return $this->getData()['description'];
    }
}
