<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class RateSourceList extends AbstractListResult
{
    /**
     * @return RateSource[]
     */
    public function all(): array
    {
        $rateSources = [];
        foreach ($this->getData() as $source) {
            $rateSources[] = new RateSource($source);
        }
        return $rateSources;
    }
}
