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
use Throwable;

use function getcwd;
use function sprintf;
use function str_replace;

final class ThrowableStringifier implements Stringifier
{
    public function __construct(
        private readonly Stringifier $stringifier,
        private readonly Quoter $quoter
    ) {
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        if (!$raw instanceof Throwable) {
            return null;
        }

        return $this->quoter->quote(
            sprintf(
                '[throwable] (%s: %s)',
                $raw::class,
                $this->stringifier->stringify($this->getData($raw), $depth + 1)
            ),
            $depth
        );
    }

    /**
     * @return mixed[]
     */
    private function getData(Throwable $throwable): array
    {
        return [
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => sprintf(
                '%s:%d',
                str_replace(getcwd() . '/', '', $throwable->getFile()),
                $throwable->getLine()
            ),
        ];
    }
}
