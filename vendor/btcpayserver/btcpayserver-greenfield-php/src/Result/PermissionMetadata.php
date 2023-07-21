<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class PermissionMetadata extends AbstractResult
{
    public function getName(): string
    {
        $data = $this->getData();
        return $data['name'];
    }

    /**
     * @return array strings
     */
    public function getIncluded(): array
    {
        $data = $this->getData();
        return $data['included'];
    }
}
