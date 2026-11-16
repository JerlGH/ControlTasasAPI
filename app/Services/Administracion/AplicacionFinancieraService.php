<?php

namespace App\Services\Administracion;

use App\Models\Administracion\AplicacionAnticipoObra;
use App\Models\Administracion\AplicacionFinanciera;
use App\Models\Administracion\ComisionAdministrativa;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\CuotaFinanciamiento;
use App\Models\Administracion\Financiamiento;
use App\Models\Administracion\GastoAdministrativo;
use App\Models\Administracion\IncidenciaFinanciera;
use App\Models\Administracion\MovimientoCuenta;
use App\Models\Administracion\ObligacionLaboral;
use App\Models\Administracion\OperacionFinanciera;
use App\Models\Administracion\ActivoAdministrativo;
use App\Models\Administracion\MovimientoObra;
use App\Support\Administracion\Finanzas as F;
use App\Support\Caja\Access;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class AplicacionFinancieraService
{
    public const DESTINOS = ['id_gasto' => GastoAdministrativo::class, 'id_obligacion' => ObligacionLaboral::class,
        'id_comision' => ComisionAdministrativa::class, 'id_obra' => MovimientoObra::class,
        'id_activo' => ActivoAdministrativo::class, 'id_cuota' => CuotaFinanciamiento::class,
        'id_financiamiento' => Financiamiento::class, 'id_incidencia' => IncidenciaFinanciera::class,
        'id_cargo' => MovimientoCuenta::class];

    public function target(array $data): array
    {
        $fields = array_filter(array_keys(self::DESTINOS), fn ($key) => ! empty($data[$key]));
        F::check(count($fields) === 1, 'destino', 'Indique exactamente un destino para aplicar el dinero');
        $field = array_values($fields)[0];
        $record = (self::DESTINOS[$field])::findOrFail($data[$field]);
        F::active($record, $field);

        return [$field, $record];
    }

    public function pending(string $field, Model $record, ?string $date = null): string
    {
        $ledger = new LedgerService;
        if ($field === 'id_financiamiento') {
            return $ledger->balance(CuentaFinanciera::findOrFail($record->id_cuenta), $date ?? '9999-12-31')['saldo'];
        }
        if ($field === 'id_cargo') {
            return $ledger->pending($record, $date);
        }
        if ($record->id_movimiento_origen) {
            return $ledger->pending(MovimientoCuenta::findOrFail($record->id_movimiento_origen), $date);
        }
        if ($field === 'id_gasto' && $record->origen_automatico) {
            return F::amount();
        }
        $query = AplicacionFinanciera::where($field, $record->id)->where('estado', 'registrado');
        if ($date) {
            $query->whereDate('fecha', '<=', $date);
        }
        $pending = F::sub($record->monto, F::sum($query->get(), 'monto_destino'));
        if ($field === 'id_obra' && $record->tipo === 'costo') {
            $advance = AplicacionAnticipoObra::where('id_costo', $record->id)->where('estado', 'registrado');
            if ($date) {
                $advance->whereDate('fecha', '<=', $date);
            }
            $pending = F::sub($pending, F::sum($advance->get(), 'monto'));
        }

        return $pending;
    }

    public function prepare(array $data): array
    {
        [$field, $target] = $this->target($data);
        $operation = OperacionFinanciera::findOrFail($data['id_operacion']);
        F::active($operation, 'id_operacion');
        F::check($operation->tipo !== 'transferencia', 'id_operacion', 'Un traslado entre fondos no paga un gasto o una deuda');
        F::check(! ObligacionLaboral::where('id_operacion_anticipo', $operation->id)->where('estado', 'registrado')->exists(),
            'id_operacion', 'El egreso ya representa el desembolso de un anticipo laboral');
        F::check(! Financiamiento::where('id_operacion_desembolso', $operation->id)->where('estado', 'registrado')->exists(),
            'id_operacion', 'El ingreso ya representa el desembolso de un financiamiento');
        F::check($data['fecha'] >= $operation->fecha->toDateString(), 'fecha', 'La aplicacion no puede preceder al dinero recibido o pagado');
        $available = (new LedgerService)->operationAvailable($operation);
        F::check(F::cmp($data['monto'], $available) <= 0, 'monto', 'La operacion no tiene ese importe sin aplicar');
        $direction = 'egreso';
        $currency = $target->moneda;
        if ($field === 'id_cargo') {
            F::check($target->tipo === 'cargo' && ($target->origen_automatico === null || (new LedgerService)->isPersonalLoan($target)),
                'id_cargo', 'Use el destino especifico para cargos automaticos');
            $account = CuentaFinanciera::findOrFail($target->id_cuenta);
            F::check(in_array($account->tipo, ['por_cobrar', 'por_pagar', 'fondo', 'recargas'], true), 'id_cargo', 'Este cargo no admite pagos auxiliares');
            $currency = $account->moneda;
            $direction = in_array($account->tipo, ['por_cobrar', 'recargas'], true) ? 'ingreso' : 'egreso';
        } elseif ($field === 'id_comision') {
            $direction = $target->naturaleza === 'cobrar' ? 'ingreso' : 'egreso';
        } elseif ($field === 'id_obligacion') {
            F::check($target->concepto !== 'descuento', 'id_obligacion', 'Un descuento no representa un pago de dinero');
            $direction = $target->concepto === 'anticipo' ? 'ingreso' : 'egreso';
        } elseif ($field === 'id_obra') {
            $direction = in_array($target->tipo, ['recuperacion', 'devolucion'], true) ? 'ingreso' : 'egreso';
        } elseif ($field === 'id_incidencia') {
            $direction = $target->id_cuenta && CuentaFinanciera::findOrFail($target->id_cuenta)->tipo === 'por_pagar' ? 'egreso' : 'ingreso';
        }
        F::check($operation->tipo === $direction, 'id_operacion', 'La direccion de dinero no corresponde a este destino');
        $originDate = $field === 'id_cuota' ? $target->financiamiento->fecha : $target->fecha;
        F::check($data['fecha'] >= F::date($originDate), 'fecha', 'La aplicacion no puede preceder al registro de destino');
        $data['monto_destino'] = F::convert($data['monto'], $operation->moneda, $currency, $data['tasa_cambio'] ?? null);
        F::check(F::cmp($data['monto_destino'], 0) > 0, 'monto', 'El importe convertido debe ser positivo a ocho decimales');
        F::check(F::cmp($data['monto_destino'], $this->pending($field, $target)) <= 0, 'monto', 'La aplicacion excede el pendiente del destino');
        if ($field === 'id_cargo' || $target->id_movimiento_origen) {
            $ledger = new LedgerService;
            $charge = $field === 'id_cargo' ? $target : MovimientoCuenta::findOrFail($target->id_movimiento_origen);
            F::check(F::cmp($data['monto_destino'], $ledger->balance($charge->cuenta, $data['fecha'], false)['saldo']) <= 0
                && F::cmp($data['monto_destino'], $ledger->balance($charge->cuenta, '9999-12-31', false)['saldo']) <= 0,
                'monto', 'El pago o cobro excede la deuda neta de la cuenta, considerando sus reducciones');
        }
        if ($field === 'id_cuota') {
            $remaining = array_fill_keys(['capital', 'interes', 'seguro', 'comision'], F::amount());
            foreach (array_keys($remaining) as $key) {
                $remaining[$key] = $target->$key;
            }
            foreach (AplicacionFinanciera::where('id_cuota', $target->id)->where('estado', 'registrado')->get() as $paid) {
                foreach ($remaining as $key => $amount) {
                    $remaining[$key] = F::sub($amount, $paid->componentes[$key]);
                }
            }
            if (empty($data['componentes'])) {
                F::check(F::cmp($data['monto_destino'], array_reduce($remaining, fn ($sum, $value) => F::add($sum, $value), '0')) === 0,
                    'componentes', 'Un pago parcial requiere distribuir principal, interes, seguro y comision');
                $data['componentes'] = $remaining;
            }
            F::unknown($data['componentes'], array_keys($remaining));
            $sum = '0';
            foreach ($remaining as $key => $amount) {
                Validator::make($data['componentes'], [$key => ['required', 'numeric', 'min:0', 'regex:/^\d{1,16}(?:\.\d{1,8})?$/D']])->validate();
                F::check(F::cmp($data['componentes'][$key], $amount) <= 0, 'componentes.'.$key, 'Supera el componente pendiente');
                $sum = F::add($sum, $data['componentes'][$key]);
            }
            F::check(F::cmp($sum, $data['monto_destino']) === 0, 'componentes', 'Los componentes no suman el importe aplicado');
            F::check(F::cmp($data['componentes']['capital'], $this->pending('id_financiamiento', $target->financiamiento)) <= 0,
                'componentes.capital', 'El principal excede la deuda vigente');
        } else {
            F::check(empty($data['componentes']), 'componentes', 'El desglose de cuota solo corresponde a un financiamiento');
        }

        return $data;
    }

    public function post(Request $request, AplicacionFinanciera $application): void
    {
        [$field, $target] = $this->target($application->getAttributes());
        $ledger = new LedgerService;
        $accountId = null;
        $charge = null;
        $principal = $application->monto_destino;
        if ($field === 'id_cargo') {
            $accountId = $target->id_cuenta;
            $charge = $target;
        } elseif ($field === 'id_financiamiento' || $field === 'id_cuota') {
            $loan = $field === 'id_cuota' ? $target->financiamiento : $target;
            $accountId = $loan->id_cuenta;
            if ($field === 'id_cuota') {
                $principal = $application->componentes['capital'];
            }
        } elseif ($target->id_movimiento_origen) {
            $charge = MovimientoCuenta::findOrFail($target->id_movimiento_origen);
            $accountId = $charge->id_cuenta;
        }
        if ($accountId && F::cmp($principal, 0) > 0) {
            $entry = $ledger->entry($request, CuentaFinanciera::findOrFail($accountId), $application->fecha->toDateString(),
                'abono', $principal, 'Aplicacion '.$application->id, 'aplicacion:'.$application->id, $application->id_operacion);
            $application->id_movimiento_generado = $entry->id;
            if ($charge) {
                $allocation = $ledger->apply($request, $charge, $entry, $application->fecha->toDateString(), $principal);
                $application->id_aplicacion_cuenta_generada = $allocation->id;
            } else {
                $left = $principal;
                foreach (MovimientoCuenta::where('id_cuenta', $accountId)->where('tipo', 'cargo')->where('estado', 'registrado')
                    ->whereDate('fecha', '<=', $application->fecha)->orderBy('fecha')->orderBy('id')->get() as $candidate) {
                    $available = $ledger->pending($candidate);
                    $amount = F::cmp($left, $available) < 0 ? $left : $available;
                    if (F::cmp($amount, 0) > 0) {
                        $ledger->apply($request, $candidate, $entry, $application->fecha->toDateString(), $amount);
                        $left = F::sub($left, $amount);
                    }
                    if (F::cmp($left, 0) === 0) {
                        break;
                    }
                }
                F::check(F::cmp($left, 0) === 0, 'monto', 'El principal no corresponde a cargos pendientes de la cuenta');
            }
            $application->save();
        }
        if ($field === 'id_cuota') {
            $cost = F::sub($application->monto_destino, $principal);
            if (F::cmp($cost, 0) > 0) {
                $expense = GastoAdministrativo::create(['fecha' => $application->fecha, 'periodo_desde' => $application->fecha,
                    'periodo_hasta' => $application->fecha, 'ambito' => 'administrativo', 'categoria' => 'gastos_financiamiento',
                    'concepto' => 'Interes, seguro y comision de cuota '.$target->id, 'moneda' => $target->moneda,
                    'monto' => $cost, 'origen_automatico' => 'aplicacion:'.$application->id,
                    'id_usuario_registro' => Access::user($request)->id]);
                F::audit($request, $expense, 'clasificar_cuota');
            }
        }
    }
}
