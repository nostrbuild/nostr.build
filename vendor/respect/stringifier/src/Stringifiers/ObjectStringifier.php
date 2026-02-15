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

use function get_object_vars;
use function is_object;
use function sprintf;

final class ObjectStringifier implements Stringifier
{
    public function __construct(
        private readonly Stringifier $stringifier,
        private readonly Quoter $quoter
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!is_object($raw)) {
            return null;
        }

        return $this->quoter->quote(
            sprintf(
                '[object] (%s: %s)',
                $raw::class,
                $this->stringifier->stringify(get_object_vars($raw), $depth + 1)
            ),
            $depth
        );
    }
}
