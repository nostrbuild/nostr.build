<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class RateSource extends AbstractResult
{
    public function getId(): string
    {
        return $this->getData()['id'];
    }

    public function getName(): string
    {
        return $this->getData()['name'];
    }
}
