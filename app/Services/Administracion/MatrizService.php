<?php

namespace App\Services\Administracion;

use App\Models\Administracion\ArqueoMadre;
use App\Models\Administracion\ConciliacionMatriz;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\DepositoLiquidacion;
use App\Models\Administracion\DetalleReporteMatriz;
use App\Models\Administracion\LiquidacionMatriz;
use App\Models\Administracion\OperacionFinanciera;
use App\Models\Administracion\ReporteMatriz;
use App\Support\Administracion\Finanzas as F;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class MatrizService
{
    public function confirm(Request $request, ReporteMatriz $report): ReporteMatriz
    {
        F::check($report->estado === 'borrador', 'estado', 'El reporte ya no admite cambios');
        $rows = DetalleReporteMatriz::where('id_reporte', $report->id)->where('estado', 'registrado')->get();
        foreach (['wu', 'biller'] as $service) {
            foreach (['NIO', 'USD'] as $currency) {
                F::check($rows->where('servicio', $service)->where('moneda', $currency)->count() === 1,
                    'detalles', 'Informe los cuatro totales WU/Biller por moneda, incluido cero');
            }
        }
        $before = $report->getAttributes();
        $report->update(['estado' => 'confirmado']);
        F::audit($request, $report, 'confirmar', $before);

        return $report;
    }

    public function reconcile(array $data): array
    {
        $root = ArqueoMadre::findOrFail($data['id_arqueo_madre']);
        $report = ReporteMatriz::findOrFail($data['id_reporte']);
        F::check($root->estado === 'cerrado' && $report->estado === 'confirmado', 'id_reporte', 'Se requieren consolidacion cerrada y reporte confirmado');
        F::check($root->fecha->toDateString() === $report->fecha->toDateString()
            && $root->id_subagencia === $report->id_subagencia, 'id_reporte', 'Fecha y alcance del reporte deben coincidir con el consolidado');
        $totals = $root->totales['metricas'];
        $lines = [];
        $same = true;
        foreach (DetalleReporteMatriz::where('id_reporte', $report->id)->where('estado', 'registrado')->get() as $row) {
            $field = $row->servicio.'_'.($row->moneda === 'NIO' ? 'cordobas' : 'dolares');
            $difference = F::sub($totals[$field], $row->monto);
            $same = $same && F::cmp($difference, 0) === 0;
            $lines[] = ['servicio' => $row->servicio, 'moneda' => $row->moneda, 'interno' => $totals[$field],
                'matriz' => $row->monto, 'diferencia' => $difference, 'id_linea_reporte' => $row->id];
        }
        F::check(count($lines) === 4, 'id_reporte', 'El reporte no conserva sus cuatro totales');
        $data['resultado'] = ['lineas' => $lines, 'coincide' => $same, 'version_consolidacion' => $root->version,
            'corte_matriz' => $report->corte_at->format('Y-m-d H:i:s')];
        $data['estado'] = $same ? 'conciliado' : 'con_diferencias';

        return $data;
    }

    public function prepareSettlement(array $data, ?LiquidacionMatriz $record): array
    {
        $root = ArqueoMadre::findOrFail($data['id_arqueo_madre']);
        F::check($root->estado === 'cerrado', 'id_arqueo_madre', 'La liquidacion requiere una consolidacion cerrada');
        $data['fecha'] = $root->fecha->toDateString();
        if ($record) {
            F::check($record->estado === 'borrador', 'estado', 'La liquidacion conserva su cierre');
        } else {
            $previous = LiquidacionMatriz::where('estado', '!=', 'anulado')
                ->whereHas('arqueo_madre', fn ($q) => $q->where('alcance', $root->alcance))
                ->orderByDesc('fecha')->orderByDesc('id')->first();
            if ($previous) {
                F::check($previous->estado === 'cerrado' && $previous->fecha->lt($root->fecha),
                    'id_anterior', 'La continuidad requiere el ultimo cierre de una fecha anterior y el mismo alcance');
                F::check(empty($data['id_anterior']) || (int) $data['id_anterior'] === $previous->id, 'id_anterior', 'Debe utilizar el ultimo cierre');
                $inherited = F::sub(0, $previous->resultado['pendiente_usd']);
                F::check(! isset($data['saldo_anterior_usd']) || F::cmp($data['saldo_anterior_usd'], $inherited) === 0,
                    'saldo_anterior_usd', 'El arrastre debe corresponder al pendiente del cierre anterior');
                $data['id_anterior'] = $previous->id;
                $data['saldo_anterior_usd'] = $inherited;
            } else {
                F::check(empty($data['id_anterior']) && isset($data['saldo_anterior_usd']) && ! empty($data['motivo_saldo_inicial']),
                    'saldo_anterior_usd', 'La primera liquidacion requiere saldo anterior explicito y motivo');
            }
        }
        if ($record?->id_anterior) {
            F::check(F::cmp($data['saldo_anterior_usd'], $record->saldo_anterior_usd) === 0, 'saldo_anterior_usd', 'El arrastre es inmutable');
        }
        $comparison = isset($data['saldo_matriz_nio']) || isset($data['saldo_interno_nio']);
        F::check(! $comparison || (isset($data['saldo_matriz_nio'], $data['saldo_interno_nio'], $data['corte_comparacion_at'])),
            'saldo_matriz_nio', 'La comparacion requiere ambos saldos y un momento de corte');
        if ($comparison) {
            F::check(Carbon::parse($data['corte_comparacion_at'])->toDateString() === $data['fecha'], 'corte_comparacion_at', 'El momento comparado debe corresponder a la fecha de liquidacion');
        }

        return $data;
    }

    public function summary(LiquidacionMatriz $settlement): array
    {
        if ($settlement->estado === 'cerrado') {
            return $settlement->resultado;
        }
        F::check($settlement->estado === 'borrador', 'estado', 'La liquidacion esta anulada');
        $root = $settlement->arqueo_madre;
        $metrics = $root->totales['metricas'];
        $rate = $root->tasa_referencia;
        $prior = $settlement->anterior;
        $query = DepositoLiquidacion::where('estado', '!=', 'anulado')
            ->whereDate('fecha', '<=', $settlement->fecha)
            ->whereHas('liquidacion.arqueo_madre', fn ($q) => $q->where('alcance', $root->alcance));
        $credited = '0';
        $pending = [];
        $used = [];
        foreach ($query->get() as $deposit) {
            if (! $deposit->fecha_acreditacion || $deposit->fecha_acreditacion->gt($settlement->fecha)) {
                $pending[] = $deposit->id;
                continue;
            }
            if ($prior && $deposit->fecha_acreditacion->lte($prior->fecha)) {
                continue;
            }
            $operation = $deposit->operacion;
            F::active($operation, 'id_operacion');
            $amount = F::convert($operation->monto, $operation->moneda, 'USD', $rate);
            $credited = F::add($credited, $amount);
            $used[] = ['id_deposito' => $deposit->id, 'id_operacion' => $operation->id, 'moneda' => $operation->moneda,
                'monto_original' => $operation->monto, 'equivalente_usd' => $amount, 'fecha_acreditacion' => $deposit->fecha_acreditacion->toDateString()];
        }
        $projection = F::sub(F::sub(F::div($metrics['proyeccion_cordobas'], $rate), $settlement->saldo_anterior_usd),
            F::add($settlement->anticipo_usd, $settlement->comision_anticipo_usd));
        $remaining = F::add($projection, $credited);

        return ['tasa_referencia' => $rate, 'movimientos_dia_nio' => $metrics['proyeccion_cordobas'],
            'saldo_anterior_usd' => $settlement->saldo_anterior_usd, 'proyeccion_usd' => $projection,
            'proyeccion_nio' => F::mul($projection, $rate), 'depositos_acreditados_usd' => F::amount($credited),
            'pendiente_usd' => $remaining, 'pendiente_nio' => F::mul($remaining, $rate),
            'diferencia_saldos_nio' => $settlement->saldo_matriz_nio !== null && $settlement->saldo_interno_nio !== null
                ? F::sub($settlement->saldo_matriz_nio, $settlement->saldo_interno_nio) : null,
            'depositos_incluidos' => $used, 'depositos_pendientes' => $pending];
    }

    public function postDeposit(Request $request, DepositoLiquidacion $deposit): void
    {
        if (! $deposit->id_cuenta_transito) {
            return;
        }
        $operation = $deposit->operacion;
        $entry = (new LedgerService)->entry($request, CuentaFinanciera::findOrFail($deposit->id_cuenta_transito),
            $deposit->fecha->toDateString(), 'cargo', $operation->monto, 'Deposito pendiente de acreditar '.$deposit->id,
            'deposito_transito:'.$deposit->id);
        $deposit->update(['id_movimiento_transito' => $entry->id]);
        if ($deposit->fecha_acreditacion) {
            $this->creditDeposit($request, $deposit, $deposit->fecha_acreditacion->toDateString());
        }
    }

    public function creditDeposit(Request $request, DepositoLiquidacion $deposit, string $date): void
    {
        if (! $deposit->id_movimiento_transito) {
            return;
        }
        $ledger = new LedgerService;
        $entry = $ledger->entry($request, CuentaFinanciera::findOrFail($deposit->id_cuenta_transito), $date,
            'abono', $deposit->operacion->monto, 'Acreditacion de deposito '.$deposit->id, 'deposito_acreditacion:'.$deposit->id);
        $ledger->apply($request, \App\Models\Administracion\MovimientoCuenta::findOrFail($deposit->id_movimiento_transito),
            $entry, $date, $entry->monto);
        $deposit->update(['id_movimiento_acreditacion' => $entry->id]);
    }
}
