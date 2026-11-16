<?php

namespace App\Support\Caja;

final class ConteoEfectivo
{
    public const DENOMINACIONES = ['mil' => 1000, 'quinientos' => 500, 'doscientos' => 200,
        'cien' => 100, 'cincuenta' => 50, 'veinte' => 20, 'diez' => 10, 'cinco' => 5, 'dos' => 2, 'uno' => 1];

    public static function total(string $currency, array $counts): string
    {
        Access::check(in_array($currency, ['NIO', 'USD'], true), 'moneda', 'Moneda invalida');
        $total = '0';
        foreach (self::DENOMINACIONES as $field => $denomination) {
            $quantity = $counts[$field] ?? 0;
            Access::check(preg_match('/^\d+$/D', (string) $quantity) === 1
                && Decimal::compare($quantity, '4294967295') <= 0, $field, 'La cantidad de piezas debe ser un entero no negativo');
            $allowed = $currency === 'NIO' ? $field !== 'dos' : $denomination <= 100;
            Access::check($allowed || Decimal::compare($quantity, 0) === 0, $field, 'Denominacion no utilizada para esa moneda en el Excel');
            $total = Decimal::add($total, Decimal::multiply($quantity, $denomination));
        }

        return Decimal::value($total, 2);
    }
}
