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

use function get_resource_type;
use function is_resource;
use function sprintf;

final class ResourceStringifier implements Stringifier
{
    public function __construct(
        private readonly Quoter $quoter
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!is_resource($raw)) {
            return null;
        }

        return $this->quoter->quote(
            sprintf(
                '[resource] (%s)',
                get_resource_type($raw)
            ),
            $depth
        );
    }
}
