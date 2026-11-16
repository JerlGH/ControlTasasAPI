<?php

namespace App\Support\Administracion;

use App\Models\Administracion\CierreFinanciero;
use App\Models\Administracion\HistorialFinanciero;
use App\Support\Caja\Access;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class Finanzas
{
    public static function model(string $recurso): string
    {
        abort_unless(isset(Recursos::DEFINICIONES[$recurso]), 404);

        return 'App\\Models\\Administracion\\'.Recursos::DEFINICIONES[$recurso]['model'];
    }

    public static function transaction(Request $request, callable $operation): mixed
    {
        Access::requireAdmin($request);
        try {
            return DB::transaction(function () use ($operation) {
                // Serializa cierres, aplicaciones y sus comprobaciones de saldo.
                DB::table('bloqueos_financieros')->insertOrIgnore(['id' => 1]);
                DB::table('bloqueos_financieros')->where('id', 1)->lockForUpdate()->first();

                return $operation();
            }, 3);
        } catch (QueryException $e) {
            if (str_starts_with((string) $e->getCode(), '23') || str_contains($e->getMessage(), 'constraint')) {
                throw new HttpException(409, 'El registro esta duplicado o conserva dependencias financieras');
            }
            throw $e;
        }
    }

    public static function check(bool $condition, string $field, string $message): void
    {
        Access::check($condition, $field, $message);
    }

    public static function amount(mixed $value = 0, int $scale = 8): string
    {
        return (string) BigDecimal::of((string) ($value ?? 0))->toScale($scale, RoundingMode::HALF_UP);
    }

    public static function add(mixed $a, mixed $b): string
    {
        return self::amount(BigDecimal::of((string) ($a ?? 0))->plus((string) ($b ?? 0)));
    }

    public static function sub(mixed $a, mixed $b): string
    {
        return self::amount(BigDecimal::of((string) ($a ?? 0))->minus((string) ($b ?? 0)));
    }

    public static function mul(mixed $a, mixed $b): string
    {
        return self::amount(BigDecimal::of((string) $a)->multipliedBy((string) $b));
    }

    public static function div(mixed $a, mixed $b): string
    {
        self::check(self::cmp($b, 0) > 0, 'tasa_cambio', 'La tasa debe ser positiva');

        return (string) BigDecimal::of((string) $a)->dividedBy((string) $b, 8, RoundingMode::HALF_UP);
    }

    public static function cmp(mixed $a, mixed $b): int
    {
        return BigDecimal::of((string) ($a ?? 0))->compareTo((string) ($b ?? 0));
    }

    public static function convert(mixed $amount, string $from, string $to, mixed $rate): string
    {
        if ($from === $to) {
            return self::amount($amount);
        }
        self::check($rate !== null && self::cmp($rate, 0) > 0, 'tasa_cambio', 'Indique la tasa NIO por USD de la aplicacion');

        return $from === 'USD' ? self::mul($amount, $rate) : self::div($amount, $rate);
    }

    public static function sum(iterable $rows, string $field): string
    {
        $total = self::amount();
        foreach ($rows as $row) {
            $total = self::add($total, is_array($row) ? ($row[$field] ?? 0) : $row->$field);
        }

        return $total;
    }

    public static function date(mixed $value): string
    {
        return Carbon::parse($value)->toDateString();
    }

    public static function openDate(mixed $value): void
    {
        $last = CierreFinanciero::orderByDesc('fecha')->first();
        self::check(! $last || self::date($value) > $last->fecha->toDateString(), 'fecha',
            'La fecha pertenece a un cierre financiero; registre un ajuste en un periodo abierto');
    }

    public static function active(Model $record, string $field): void
    {
        self::check(! in_array($record->estado, ['anulado', 'inactivo'], true), $field, 'El registro de referencia no esta vigente');
    }

    public static function audit(Request $request, Model $record, string $action, ?array $before = null): void
    {
        HistorialFinanciero::create(['entidad' => $record->getTable(), 'id_registro' => $record->id,
            'accion' => $action, 'antes' => $before, 'despues' => $record->fresh()->getAttributes(),
            'motivo' => $request->input('motivo'), 'ip' => $request->ip(),
            'id_usuario_registro' => Access::user($request)->id]);
    }

    public static function unknown(array $payload, array $allowed): void
    {
        $unknown = array_diff(array_keys($payload), $allowed);
        if ($unknown !== []) {
            throw ValidationException::withMessages(array_fill_keys($unknown, 'Campo no admitido para esta operacion'));
        }
    }
}
