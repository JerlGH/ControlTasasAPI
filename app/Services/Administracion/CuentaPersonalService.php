<?php

namespace App\Services\Administracion;

use App\Models\Administracion\ContraparteFinanciera;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\MovimientoCuenta;
use App\Models\Administracion\MovimientoPersonal;
use App\Models\Administracion\OperacionFinanciera;
use App\Support\Administracion\Finanzas as F;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CuentaPersonalService
{
    public function prepare(array $data): array
    {
        if (MovimientoPersonal::where('clave', $data['clave'])->exists()) {
            throw new HttpException(409, 'La clave del movimiento personal ya fue utilizada');
        }
        $account = CuentaFinanciera::findOrFail($data['id_cuenta']);
        $operation = OperacionFinanciera::findOrFail($data['id_operacion']);
        F::active($account, 'id_cuenta');
        F::active($operation, 'id_operacion');
        F::check((bool) $account->id_contraparte, 'id_cuenta', 'La cuenta personal debe tener una contraparte identificada');
        F::active(ContraparteFinanciera::findOrFail($account->id_contraparte), 'id_cuenta');
        $loan = $data['tipo'] === 'prestamo_entregado';
        F::check($account->tipo === ($loan ? 'por_cobrar' : 'fondo'), 'id_cuenta',
            'Los depositos/retiros usan fondo; el prestamo entregado usa cuenta por cobrar');
        F::check($operation->tipo === ($data['tipo'] === 'deposito' ? 'ingreso' : 'egreso'),
            'id_operacion', 'La direccion del dinero no corresponde al movimiento personal');
        F::check($data['fecha'] >= $operation->fecha->toDateString() && $data['fecha'] >= $account->fecha_apertura->toDateString(),
            'fecha', 'El movimiento no puede preceder a la operacion ni a la apertura de la cuenta');
        F::check(! MovimientoCuenta::where('id_cuenta', $account->id)->where('estado', 'registrado')->whereDate('fecha', '>', $data['fecha'])->exists(),
            'fecha', 'Registre los movimientos personales en orden cronologico de su cuenta');
        $ledger = new LedgerService;
        F::check(F::cmp($data['monto'], $ledger->operationAvailable($operation)) <= 0, 'monto', 'El dinero ya tiene otra aplicacion o reserva');
        $data['moneda'] = $account->moneda;
        $data['moneda_origen'] = $operation->moneda;
        $amount = F::convert($data['monto'], $operation->moneda, $account->moneda, $data['tasa_cambio'] ?? null);
        F::check(F::cmp($amount, 0) > 0 && F::cmp($amount, '9999999999999999.99999999') <= 0,
            'monto', 'El importe convertido debe ser positivo y caber en la precision de la cuenta');
        $data['monto_cuenta'] = $amount;
        $before = $ledger->balance($account, $data['fecha'])['saldo'];
        $data['saldo_anterior'] = $before;
        $data['saldo_posterior'] = $data['tipo'] === 'retiro' ? F::sub($before, $amount) : F::add($before, $amount);
        $data += array_fill_keys(['fondos_utilizados', 'fondos_incrementados', 'deuda_generada', 'deuda_cancelada'], F::amount());
        if ($loan) {
            // No emitir un cargo nuevo contra un anticipo agregado sin partidas que lo compensen.
            F::check(F::cmp($before, 0) >= 0, 'id_cuenta', 'Regularice el saldo a favor antes de entregar un nuevo prestamo en esta CxC');
            $data['deuda_generada'] = $amount;
        } elseif ($data['tipo'] === 'retiro') {
            $available = F::cmp($before, 0) > 0 ? $before : F::amount();
            $data['fondos_utilizados'] = F::cmp($available, $amount) < 0 ? $available : $amount;
            $data['deuda_generada'] = F::sub($amount, $data['fondos_utilizados']);
        } else {
            $debt = F::cmp($before, 0) < 0 ? F::sub(0, $before) : F::amount();
            $data['deuda_cancelada'] = F::cmp($debt, $amount) < 0 ? $debt : $amount;
            $data['fondos_incrementados'] = F::sub($amount, $data['deuda_cancelada']);
        }

        return $data;
    }

    public function post(Request $request, MovimientoPersonal $record): void
    {
        $entry = (new LedgerService)->entry($request, $record->cuenta, $record->fecha->toDateString(),
            $record->tipo === 'retiro' ? 'abono' : 'cargo', $record->monto_cuenta,
            $record->concepto, 'personal:'.$record->id, $record->id_operacion);
        $record->update(['id_movimiento_generado' => $entry->id]);
    }

    public function annul(Request $request, MovimientoPersonal $record): void
    {
        $entry = $record->movimiento_generado;
        (new LedgerService)->assertEntryMutation($entry);
        F::check(! (new AdministracionService)->dependencies($entry, $record), 'registro',
            'Retire primero los cobros o aplicaciones de este movimiento personal');
        $before = $entry->getAttributes();
        $entry->update(['estado' => 'anulado']);
        F::audit($request, $entry, 'anular_origen', $before);
    }
}
