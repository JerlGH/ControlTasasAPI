<?php

namespace App\Services\Administracion;

use App\Models\Administracion\AplicacionCuenta;
use App\Models\Administracion\AplicacionFinanciera;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\DepositoLiquidacion;
use App\Models\Administracion\Financiamiento;
use App\Models\Administracion\MovimientoCuenta;
use App\Models\Administracion\MovimientoPersonal;
use App\Models\Administracion\ObligacionLaboral;
use App\Models\Administracion\OperacionFinanciera;
use App\Models\Arqueo;
use App\Models\Entradas_Y_Salidas;
use App\Models\OperacionCambioCaja;
use App\Models\Pases;
use App\Models\RemesaBancaria;
use App\Support\Administracion\Finanzas as F;
use App\Support\Caja\Access;
use Illuminate\Http\Request;

final class LedgerService
{
    public function fingerprintRows(iterable $rows): string
    {
        $facts = [];
        foreach ($rows as $row) {
            $facts[] = ['id' => (int) $row['id'], 'fecha' => F::date($row['fecha']), 'tipo' => $row['tipo'],
                'monto' => F::amount($row['monto']), 'id_operacion' => isset($row['id_operacion']) ? (int) $row['id_operacion'] : null];
        }
        usort($facts, fn ($a, $b) => [$a['fecha'], $a['id']] <=> [$b['fecha'], $b['id']]);

        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR));
    }

    public function fingerprint(array $balance): string
    {
        $account = $balance['Cuenta'];
        $facts = ['id' => (int) $account->id, 'tipo' => $account->tipo, 'moneda' => $account->moneda,
            'fecha_apertura' => F::date($account->fecha_apertura), 'saldo_inicial' => F::amount($account->saldo_inicial),
            'movimientos' => $this->fingerprintRows(array_column($balance['movimientos'], 'movimiento'))];

        // Solo hechos financieros normalizados, sin depender del orden JSON de MySQL ni de notas/timestamps.
        return hash('sha256', json_encode($facts, JSON_THROW_ON_ERROR));
    }

    public function balance(CuentaFinanciera $account, string $date, bool $includePending = true): array
    {
        $rows = MovimientoCuenta::where('id_cuenta', $account->id)->where('estado', 'registrado')->whereDate('fecha', '<=', $date)->orderBy('fecha')->orderBy('id')->get();
        $balance = $account->fecha_apertura->toDateString() <= $date ? $account->saldo_inicial : F::amount();
        $items = [];
        foreach ($rows as $row) {
            $balance = $row->tipo === 'cargo' ? F::add($balance, $row->monto) : F::sub($balance, $row->monto);
            $items[] = ['movimiento' => $row, 'saldo' => $balance, 'pendiente_aplicacion' => $includePending ? $this->pending($row, $date) : null];
        }

        $result = ['Cuenta' => $account, 'fecha_corte' => $date, 'saldo' => F::amount($balance), 'movimientos' => $items];
        if ($account->tipo === 'fondo') {
            $result['posicion_personal'] = [
                'fondos_del_titular' => F::cmp($balance, 0) > 0 ? F::amount($balance) : F::amount(),
                'deuda_del_titular' => F::cmp($balance, 0) < 0 ? F::sub(0, $balance) : F::amount(),
            ];
        }

        return $result;
    }

    public function operationAvailable(OperacionFinanciera $operation): string
    {
        $reserved = Financiamiento::where('id_operacion_desembolso', $operation->id)->where('estado', 'registrado')->exists()
            || ObligacionLaboral::where('id_operacion_anticipo', $operation->id)->where('estado', 'registrado')->exists()
            || DepositoLiquidacion::where('id_operacion', $operation->id)->where('estado', '!=', 'anulado')->exists();
        F::check(! $reserved, 'id_operacion', 'La operacion esta reservada para otro documento financiero');
        $applied = F::sum(AplicacionFinanciera::where('id_operacion', $operation->id)->where('estado', 'registrado')->get(), 'monto');
        $personal = F::sum(MovimientoPersonal::where('id_operacion', $operation->id)->where('estado', 'registrado')->get(), 'monto');

        return F::sub(F::sub($operation->monto, $applied), $personal);
    }

    public function assertPostingDate(CuentaFinanciera $account, string $date): void
    {
        F::check(! MovimientoPersonal::where('id_cuenta', $account->id)->where('estado', 'registrado')->whereDate('fecha', '>', $date)->exists(),
            'fecha', 'Un movimiento personal posterior conserva su saldo anterior; registre un ajuste posterior');
    }

    public function assertEntryMutation(MovimientoCuenta $entry): void
    {
        F::check(! MovimientoPersonal::where('id_cuenta', $entry->id_cuenta)->where('estado', 'registrado')
            ->where(function ($query) use ($entry) {
                $query->whereDate('fecha', '>', $entry->fecha)->orWhere(function ($sameDay) use ($entry) {
                    $sameDay->whereDate('fecha', $entry->fecha)->where('id_movimiento_generado', '>', $entry->id);
                });
            })->exists(), 'registro', 'Corrija primero los movimientos personales posteriores de esta cuenta');
    }

    public function isPersonalLoan(MovimientoCuenta $entry): bool
    {
        return MovimientoPersonal::where('id_movimiento_generado', $entry->id)->where('tipo', 'prestamo_entregado')->where('estado', 'registrado')->exists();
    }

    public function pending(MovimientoCuenta $movement, ?string $date = null): string
    {
        $field = $movement->tipo === 'cargo' ? 'id_cargo' : 'id_abono';
        $query = AplicacionCuenta::where($field, $movement->id)->where('estado', 'registrado');
        if ($date) {
            $query->whereDate('fecha', '<=', $date);
        }

        return F::sub($movement->monto, F::sum($query->get(), 'monto'));
    }

    public function entry(Request $request, CuentaFinanciera $account, string $date, string $type, string $amount, string $concept, string $origin, ?int $operation = null): MovimientoCuenta
    {
        F::active($account, 'id_cuenta');
        F::check($account->fecha_apertura->toDateString() <= $date, 'fecha', 'La cuenta no existia en esa fecha');
        $this->assertPostingDate($account, $date);
        $entry = MovimientoCuenta::create(['id_cuenta' => $account->id, 'fecha' => $date, 'tipo' => $type,
            'monto' => $amount, 'concepto' => $concept, 'origen_automatico' => $origin,
            'id_operacion' => $operation, 'clave' => $origin.':'.$account->id.':'.$type,
            'id_usuario_registro' => Access::user($request)->id]);
        F::audit($request, $entry, 'contabilizar');

        return $entry;
    }

    public function apply(Request $request, MovimientoCuenta $charge, MovimientoCuenta $credit, string $date, string $amount): AplicacionCuenta
    {
        F::active($charge, 'id_cargo');
        F::active($credit, 'id_abono');
        F::check($charge->id_cuenta === $credit->id_cuenta && $charge->tipo === 'cargo' && $credit->tipo === 'abono',
            'id_abono', 'La aplicacion requiere cargo y abono de la misma cuenta');
        F::check($charge->fecha->toDateString() <= $date && $credit->fecha->toDateString() <= $date,
            'fecha', 'La aplicacion no puede preceder a sus movimientos');
        F::check(F::cmp($amount, $this->pending($charge)) <= 0 && F::cmp($amount, $this->pending($credit)) <= 0,
            'monto', 'La aplicacion excede el saldo pendiente de cargo o abono');
        $application = AplicacionCuenta::create(['id_cargo' => $charge->id, 'id_abono' => $credit->id,
            'fecha' => $date, 'monto' => $amount, 'id_usuario_registro' => Access::user($request)->id]);
        F::audit($request, $application, 'aplicar');

        return $application;
    }

    public function prepareOperation(array $data): array
    {
        $hasSource = ! empty($data['origen_tipo']) || ! empty($data['id_origen']);
        if ($hasSource) {
            F::check(! empty($data['origen_tipo']) && ! empty($data['id_origen']), 'origen_tipo', 'Indique tipo e ID de origen');
            $map = ['entrada_salida' => [Entradas_Y_Salidas::class, 'id_entrada_salida'], 'pase' => [Pases::class, 'id_pase'],
                'remesa' => [RemesaBancaria::class, 'id_remesa'], 'cambio_entrada' => [OperacionCambioCaja::class, 'id_operacion_cambio'],
                'cambio_salida' => [OperacionCambioCaja::class, 'id_operacion_cambio']];
            [$model, $field] = $map[$data['origen_tipo']];
            $source = $model::findOrFail($data['id_origen']);
            $day = Arqueo::whereKey($source->id_arqueo)->lockForUpdate()->firstOrFail();
            F::check($day->estado === 'cerrado' && $source->estado !== 'anulado', 'id_origen', 'El origen debe pertenecer a un arqueo cerrado y estar vigente');
            $exchange = str_starts_with($data['origen_tipo'], 'cambio_');
            if ($exchange) {
                $entry = $data['origen_tipo'] === 'cambio_entrada';
                $currency = ($source->tipo === 'Compra') === $entry ? 'USD' : 'NIO';
                $direction = $entry ? 'Entrada' : 'Salida';
                $amount = $currency === 'USD' ? $source->monto_dolares : $source->monto_cordobas;
                F::check(($entry ? $source->medio_entrada : $source->medio_salida) !== 'transferencia', 'id_origen', 'Esta pata no representa efectivo de caja');
                $data['lado'] = $entry ? 'entrada' : 'salida';
            } else {
                $currency = $source->moneda;
                $amount = $source->monto;
                $direction = $source->tipo;
                F::check($source->medio !== 'transferencia', 'id_origen', 'La transferencia declarada no es una nueva entrega fisica de caja');
            }
            F::check($data['moneda'] === $currency && F::cmp($data['monto'], $amount) === 0, 'monto', 'Moneda e importe deben coincidir con el origen');
            F::check($data['fecha'] >= $day->fecha->toDateString(), 'fecha', 'El reconocimiento no puede preceder a la jornada de origen');
            if ($data['tipo'] === 'transferencia') {
                F::check($direction === 'Salida'
                    ? (! empty($data['id_cuenta_destino']) && empty($data['id_cuenta_origen']))
                    : (! empty($data['id_cuenta_origen']) && empty($data['id_cuenta_destino'])),
                    'tipo', 'El traslado de caja requiere solamente la cuenta bancaria contraparte');
            } else {
                F::check($data['tipo'] === ($direction === 'Entrada' ? 'ingreso' : 'egreso')
                    && empty($data['id_cuenta_origen']) && empty($data['id_cuenta_destino']), 'tipo', 'La operacion de caja ya representa su movimiento; no agregue otra cuenta de efectivo');
            }
            $data[$field] = $source->id;
            $data['clave_origen_activa'] = $data['origen_tipo'].':'.$source->id;
            $data['origen_snapshot'] = ['tabla' => $source->getTable(), 'registro' => $source->getAttributes(),
                'id_arqueo' => $day->id, 'fecha_arqueo' => $day->fecha->toDateString(), 'direccion' => $direction];
        } else {
            $origin = ! empty($data['id_cuenta_origen']);
            $destination = ! empty($data['id_cuenta_destino']);
            F::check(match ($data['tipo']) {
                'ingreso' => ! $origin && $destination, 'egreso' => $origin && ! $destination,
                'transferencia' => $origin && $destination && $data['id_cuenta_origen'] !== $data['id_cuenta_destino'],
            }, 'tipo', 'Seleccione las cuentas bancarias de origen/destino correspondientes');
        }
        foreach (['id_cuenta_origen', 'id_cuenta_destino'] as $field) {
            if (! empty($data[$field])) {
                $account = CuentaFinanciera::findOrFail($data[$field]);
                F::active($account, $field);
                F::check(in_array($account->tipo, ['banco', 'pos'], true) && $account->moneda === $data['moneda'], $field, 'Seleccione banco/POS de la misma moneda');
                F::check($account->fecha_apertura->toDateString() <= $data['fecha'], 'fecha', 'La cuenta no existia en esa fecha');
            }
        }

        return $data;
    }

    public function postOperation(Request $request, OperacionFinanciera $operation): void
    {
        foreach (['id_cuenta_origen' => 'abono', 'id_cuenta_destino' => 'cargo'] as $field => $type) {
            if ($operation->$field) {
                $this->entry($request, CuentaFinanciera::findOrFail($operation->$field), $operation->fecha->toDateString(),
                    $type, $operation->monto, $operation->concepto, 'operacion:'.$operation->id, $operation->id);
            }
        }
    }
}
