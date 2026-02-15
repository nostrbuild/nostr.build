<?php

/*
 * This file is part of Respect/Stringifier.
 * Copyright (c) Henrique Moody <henriquemoody@gmail.com>
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Respect\Stringifier\Quoters;

use Respect\Stringifier\Quoter;

use function sprintf;

final class CodeQuoter implements Quoter
{
    public function quote(string $string, int $depth): string
    {
        if ($depth === 0) {
            return sprintf('`%s`', $string);
        }

        return $string;
    }
}
