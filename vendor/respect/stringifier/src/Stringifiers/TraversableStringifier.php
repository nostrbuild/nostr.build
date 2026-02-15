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
use Traversable;

use function iterator_to_array;
use function sprintf;

final class TraversableStringifier implements Stringifier
{
    public function __construct(
        private readonly Stringifier $stringifier,
        private readonly Quoter $quoter
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!$raw instanceof Traversable) {
            return null;
        }

        return $this->quoter->quote(
            sprintf(
                '[traversable] (%s: %s)',
                $raw::class,
                $this->stringifier->stringify(iterator_to_array($raw), $depth + 1)
            ),
            $depth
        );
    }
}
