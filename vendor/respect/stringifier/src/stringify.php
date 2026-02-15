<?php

/*
 * This file is part of Respect/Stringifier.
 * Copyright (c) Henrique Moody <henriquemoody@gmail.com>
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Respect\Stringifier;

use Respect\Stringifier\Stringifiers\ClusterStringifier;

function stringify(mixed $value): string
{
    static $stringifier;

    if ($stringifier === null) {
        $stringifier = ClusterStringifier::createDefault();
    }

    return $stringifier->stringify($value, 0) ?? '#ERROR#';
}
