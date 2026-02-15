<?php

/*
 * This file is part of Respect/Stringifier.
 * Copyright (c) Henrique Moody <henriquemoody@gmail.com>
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Respect\Stringifier\Stringifiers;

use Respect\Stringifier\Quoter;
use Respect\Stringifier\Stringifier;

use function array_keys;
use function count;
use function implode;
use function is_array;
use function range;
use function sprintf;

final class ArrayStringifier implements Stringifier
{
    public function __construct(
        private readonly Stringifier $stringifier,
        private readonly Quoter $quoter,
        private readonly int $maximumDepth,
        private readonly int $itemsLimit
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!is_array($raw)) {
            return null;
        }

        if (empty($raw)) {
            return $this->quoter->quote('{ }', $depth);
        }

        if ($depth >= $this->maximumDepth) {
            return '...';
        }

        $items = [];
        $itemsCount = 0;
        $isSequential = $this->isSequential($raw);
        foreach ($raw as $key => $value) {
            if (++$itemsCount > $this->itemsLimit) {
                $items[$itemsCount] = '...';
                break;
            }

            $items[$itemsCount] = '';
            if ($isSequential === false) {
                $items[$itemsCount] .= sprintf('%s: ', $this->stringifier->stringify($key, $depth + 1));
            }
            $items[$itemsCount] .= $this->stringifier->stringify($value, $depth + 1);
        }

        return $this->quoter->quote(sprintf('{ %s }', implode(', ', $items)), $depth);
    }

    /**
     * @param mixed[] $array
     */
    private function isSequential(array $array): bool
    {
        return array_keys($array) === range(0, count($array) - 1);
    }
}
