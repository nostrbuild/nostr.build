<?php

/*
 * This file is part of Respect/Stringifier.
 * Copyright (c) Henrique Moody <henriquemoody@gmail.com>
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Respect\Stringifier\Stringifiers;

use Respect\Stringifier\Stringifier;

use function is_object;
use function method_exists;

final class StringableObjectStringifier implements Stringifier
{
    public function __construct(
        private readonly Stringifier $stringifier
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!is_object($raw)) {
            return null;
        }

        if (!method_exists($raw, '__toString')) {
            return null;
        }

        return $this->stringifier->stringify($raw->__toString(), $depth);
    }
}
