<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class OfferingList extends AbstractListResult
{
    /**
     * @return Offering[]
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->getData() as $item) {
            $result[] = new Offering($item);
        }
        return $result;
    }
}
