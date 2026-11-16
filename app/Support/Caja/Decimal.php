<?php

namespace App\Support\Caja;

use Brick\Math\BigDecimal;

final class Decimal
{
    public static function value(mixed $value, int $scale = 8): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale($scale);
    }

    public static function add(mixed $a, mixed $b): string
    {
        return (string) BigDecimal::of((string) ($a ?? '0'))->plus((string) ($b ?? '0'))->toScale(8);
    }

    public static function subtract(mixed $a, mixed $b): string
    {
        return (string) BigDecimal::of((string) ($a ?? '0'))->minus((string) ($b ?? '0'))->toScale(8);
    }

    public static function multiply(mixed $a, mixed $b): string
    {
        return (string) BigDecimal::of((string) $a)->multipliedBy((string) $b)->toScale(8);
    }

    public static function compare(mixed $a, mixed $b): int
    {
        return BigDecimal::of((string) ($a ?? '0'))->compareTo((string) ($b ?? '0'));
    }
}
