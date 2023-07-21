<?php

declare(strict_types=1);

namespace BTCPayServer\Result;

class LanguageCodeList extends AbstractListResult
{
    /**
     * @return \BTCPayServer\Result\LanguageCode[]
     */
    public function all(): array
    {
        $languageCodes = [];
        foreach ($this->getData() as $languageCode) {
            $languageCodes[] = new \BTCPayServer\Result\LanguageCode($languageCode);
        }
        return $languageCodes;
    }
}
