<?php

declare(strict_types=1);

namespace BTCPayServer\Util;

class PreciseNumber
{
    /** @var string */
    private $value;

    public static function parseFloat(float $value, int $precision = 8): PreciseNumber
    {
        return self::parseString(number_format($value, $precision, '.', ''));
    }

    public static function parseInt(int $value): PreciseNumber
    {
        return self::parseString((string)$value);
    }

    public static function parseString(string $value): PreciseNumber
    {
        if (is_numeric($value)) {
            return new self((string)$value);
        } else {
            throw new \InvalidArgumentException('Argument should be numeric.');
        }
    }

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
