<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class LanguageCode extends AbstractResult
{
    public function getCode(): string
    {
        $data = $this->getData();
        return $data['code'];
    }

    public function getCurrentLanguage(): string
    {
        $data = $this->getData();
        return $data['currentLanguage'];
    }
}
