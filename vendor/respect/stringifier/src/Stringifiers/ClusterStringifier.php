<?php

/*
 * This file is part of Respect/Stringifier.
 * Copyright (c) Henrique Moody <henriquemoody@gmail.com>
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Respect\Stringifier\Stringifiers;

use Respect\Stringifier\Quoters\CodeQuoter;
use Respect\Stringifier\Stringifier;

final class ClusterStringifier implements Stringifier
{
    /**
     * @var Stringifier[]
     */
    private array $stringifiers = [];

    /**
     * @param Stringifier[] ...$stringifiers
     */
    public function __construct(Stringifier ...$stringifiers)
    {
        $this->setStringifiers($stringifiers);
    }

    public static function createDefault(): self
    {
        $quoter = new CodeQuoter();

        $stringifier = new self();
        $stringifier->setStringifiers([
            new TraversableStringifier($stringifier, $quoter),
            new DateTimeStringifier($stringifier, $quoter, 'c'),
            new ThrowableStringifier($stringifier, $quoter),
            new StringableObjectStringifier($stringifier),
            new JsonSerializableStringifier($stringifier, $quoter),
            new ObjectStringifier($stringifier, $quoter),
            new ArrayStringifier($stringifier, $quoter, 3, 5),
            new InfiniteStringifier($quoter),
            new NanStringifier($quoter),
            new ResourceStringifier($quoter),
            new BoolStringifier($quoter),
            new NullStringifier($quoter),
            new JsonParsableStringifier(),
        ]);

        return $stringifier;
    }

    /**
     * @param Stringifier[] $stringifiers
     */
    public function setStringifiers(array $stringifiers): void
    {
        $this->stringifiers = [];

        foreach ($stringifiers as $stringifier) {
            $this->addStringifier($stringifier);
        }
    }

    public function addStringifier(Stringifier $stringifier): void
    {
        $this->stringifiers[] = $stringifier;
    }

    public function stringify(mixed $raw, int $depth): ?string
    {
        foreach ($this->stringifiers as $stringifier) {
            $string = $stringifier->stringify($raw, $depth);
            if ($string === null) {
                continue;
            }

            return $string;
        }

        return null;
    }
}
