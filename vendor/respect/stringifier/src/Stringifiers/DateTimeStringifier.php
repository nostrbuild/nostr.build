<?php

/*
 * This file is part of Respect/Stringifier.
 * Copyright (c) Henrique Moody <henriquemoody@gmail.com>
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Respect\Stringifier\Stringifiers;

use DateTimeInterface;
use Respect\Stringifier\Quoter;
use Respect\Stringifier\Stringifier;

use function sprintf;

final class DateTimeStringifier implements Stringifier
{
    public function __construct(
        private readonly Stringifier $stringifier,
        private readonly Quoter $quoter,
        private readonly string $format
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!$raw instanceof DateTimeInterface) {
            return null;
        }

        return $this->quoter->quote(
            sprintf(
                '[date-time] (%s: %s)',
                $raw::class,
                $this->stringifier->stringify($raw->format($this->format), $depth + 1)
            ),
            $depth
        );
    }
}
