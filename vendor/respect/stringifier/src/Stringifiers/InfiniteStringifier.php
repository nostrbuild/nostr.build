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

use function is_float;
use function is_infinite;

final class InfiniteStringifier implements Stringifier
{
    public function __construct(
        private readonly Quoter $quoter
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!is_float($raw)) {
            return null;
        }

        if (!is_infinite($raw)) {
            return null;
        }

        return $this->quoter->quote(($raw > 0 ? '' : '-') . 'INF', $depth);
    }
}
