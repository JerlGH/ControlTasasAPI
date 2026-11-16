<?php

namespace App\Services\Administracion;

use App\Models\Administracion\ActivoAdministrativo;
use App\Models\Administracion\AplicacionAnticipoObra;
use App\Models\Administracion\AplicacionFinanciera;
use App\Models\Administracion\ArqueoMadre;
use App\Models\Administracion\CierreFinanciero;
use App\Models\Administracion\ComisionAdministrativa;
use App\Models\Administracion\ConciliacionMatriz;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\GastoAdministrativo;
use App\Models\Administracion\LiquidacionMatriz;
use App\Models\Administracion\MovimientoObra;
use App\Models\Administracion\ProyectoObra;
use App\Support\Administracion\Finanzas as F;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class CapitalService
{
    private function currencies(): array
    {
        return ['NIO' => F::amount(), 'USD' => F::amount()];
    }

    public function project(ProyectoObra $project, string $date): array
    {
        $totals = array_fill_keys(['costo', 'anticipo', 'recuperacion', 'devolucion', 'anticipo_aplicado', 'neto'], $this->currencies());
        foreach (MovimientoObra::where('id_proyecto', $project->id)->where('estado', 'registrado')->whereDate('fecha', '<=', $date)->get() as $row) {
            $totals[$row->tipo][$row->moneda] = F::add($totals[$row->tipo][$row->moneda], $row->monto);
        }
        foreach (AplicacionAnticipoObra::where('estado', 'registrado')->whereDate('fecha', '<=', $date)
            ->whereHas('anticipo', fn ($q) => $q->where('id_proyecto', $project->id))->get() as $application) {
            $currency = $application->anticipo->moneda;
            $totals['anticipo_aplicado'][$currency] = F::add($totals['anticipo_aplicado'][$currency], $application->monto);
        }
        foreach (['NIO', 'USD'] as $currency) {
            $totals['neto'][$currency] = F::sub(F::sub(F::sub(F::add($totals['costo'][$currency], $totals['anticipo'][$currency]),
                $totals['anticipo_aplicado'][$currency]), $totals['recuperacion'][$currency]), $totals['devolucion'][$currency]);
        }

        return ['Registro' => $project, 'fecha_corte' => $date, 'Totales' => $totals];
    }

    public function fromRequest(Request $request): array
    {
        $data = Validator::make($request->query(), ['fecha' => 'required|date_format:Y-m-d',
            'periodo_desde' => 'required|date_format:Y-m-d|before_or_equal:fecha',
            'id_arqueo_madre' => 'required|integer|exists:arqueo_madre,id',
            'id_liquidacion' => 'required|integer|exists:liquidaciones_matriz,id',
            'cuentas_capital_trabajo' => 'sometimes|array|min:1',
            'cuentas_capital_trabajo.*' => 'required|integer|distinct|exists:cuentas_financieras,id'])->validate();

        return $this->calculate($data);
    }

    public function calculate(array $data): array
    {
        $root = ArqueoMadre::findOrFail($data['id_arqueo_madre']);
        $settlement = LiquidacionMatriz::findOrFail($data['id_liquidacion']);
        F::check($root->estado === 'cerrado' && $root->id_subagencia === null && $root->totales['completo'],
            'id_arqueo_madre', 'El control global requiere una consolidacion de grupo completa y cerrada');
        F::check($settlement->estado === 'cerrado' && $settlement->id_arqueo_madre === $root->id
            && $root->fecha->toDateString() === $data['fecha'], 'id_liquidacion', 'Utilice el cierre de liquidacion del mismo corte');
        $date = $data['fecha'];
        $from = $data['periodo_desde'];
        F::check($from <= $date, 'periodo_desde', 'El periodo esta invertido');
        $metrics = $root->totales['metricas'];
        $rate = $root->tasa_referencia;
        $parts = array_fill_keys(['bancos', 'pendientes_netos', 'deudas', 'gastos', 'activos', 'obra_neta', 'comisiones_recibidas'], $this->currencies());
        $sources = ['cuentas' => [], 'gastos' => [], 'activos' => [], 'obras' => [], 'comisiones' => []];
        $excluded = [];
        $externalCoverage = ServicioExternoService::financialCoverage($date);
        $pending = $externalCoverage['pendientes'];
        $sources['servicios_externos'] = $externalCoverage['fuentes'];
        $ledger = new LedgerService;
        $diarySource = $root->totales['libro_diario'] ?? null;
        $diaryBalances = collect($diarySource['saldos'] ?? [])->keyBy('id_cuenta');
        if ($diarySource) {
            $cashCoverage = LibroDiarioService::cashCoverage($root->libro_diario->resultado);
            if (! $cashCoverage['coincide']) {
                $pending[] = ['tipo' => 'fuentes_distintas_libro_diario', 'cobertura' => $cashCoverage];
            }
        }
        foreach (CuentaFinanciera::whereDate('fecha_apertura', '<=', $date)->orderBy('id')->get() as $account) {
            $accountLedger = $ledger->balance($account, $date, false);
            $balance = $accountLedger['saldo'];
            $liveBalance = $balance;
            if ($diaryBalances->has($account->id)) {
                $balance = $diaryBalances[$account->id]['saldo_final'];
                if (F::cmp($balance, $liveBalance) !== 0) {
                    $pending[] = ['tipo' => 'saldo_distinto_libro_diario', 'id_cuenta' => $account->id,
                        'saldo_diario' => $balance, 'saldo_ledger_actual' => $liveBalance];
                }
                $frozen = $diaryBalances[$account->id];
                $currentFingerprint = $ledger->fingerprint($accountLedger);
                $sameHistory = isset($frozen['huella_auxiliar']) ? hash_equals($frozen['huella_auxiliar'], $currentFingerprint)
                    : $ledger->fingerprintRows(array_column($frozen['movimientos'], 'movimiento')) === $ledger->fingerprintRows(
                        array_map(fn ($item) => $item['movimiento'], array_filter($accountLedger['movimientos'], fn ($item) => F::date($item['movimiento']->fecha) === $date)));
                if (! $sameHistory) {
                    $pending[] = ['tipo' => 'movimientos_distintos_libro_diario', 'id_cuenta' => $account->id,
                        'huella_diario' => $frozen['huella_auxiliar'] ?? null, 'huella_actual' => $currentFingerprint];
                }
            }
            if ($account->rubro_capital === 'excluido') {
                if (F::cmp($balance, 0) !== 0) {
                    $excluded[] = ['id_cuenta' => $account->id, 'moneda' => $account->moneda, 'saldo' => $balance];
                }
                continue;
            }
            $field = match ($account->rubro_capital) { 'banco' => 'bancos', 'pendiente' => 'pendientes_netos', 'deuda' => 'deudas' };
            $signed = $field === 'pendientes_netos' && in_array($account->tipo, ['por_pagar', 'fondo'], true) ? F::sub(0, $balance) : $balance;
            $parts[$field][$account->moneda] = F::add($parts[$field][$account->moneda], $signed);
            $sources['cuentas'][] = ['id' => $account->id, 'rubro' => $account->rubro_capital, 'moneda' => $account->moneda,
                'naturaleza' => $account->tipo, 'saldo' => $balance, 'valor_agregado' => $signed,
                'origen_saldo' => $diaryBalances->has($account->id) ? 'libro_diario' : 'ledger', 'saldo_ledger_actual' => $liveBalance];
        }
        foreach (GastoAdministrativo::where('estado', 'registrado')->whereDate('fecha', '<=', $date)
            ->whereDate('periodo_hasta', '>=', $from)->whereDate('periodo_desde', '<=', $date)->get() as $expense) {
            if ($expense->periodo_desde->toDateString() < $from || $expense->periodo_hasta->toDateString() > $date) {
                $pending[] = ['tipo' => 'periodo_gasto_partido', 'id' => $expense->id];
                continue;
            }
            $parts['gastos'][$expense->moneda] = F::add($parts['gastos'][$expense->moneda], $expense->monto);
            $sources['gastos'][] = $expense->toArray();
        }
        $admin = new AdministracionService;
        foreach (ActivoAdministrativo::where('estado', 'registrado')->whereDate('fecha', '<=', $date)->get() as $asset) {
            $quantity = array_reduce($admin->assetLocations($asset, $date), fn ($sum, $value) => F::add($sum, $value), '0');
            $value = F::mul($quantity, $asset->precio_unitario);
            $parts['activos'][$asset->moneda] = F::add($parts['activos'][$asset->moneda], $value);
            $sources['activos'][] = ['id' => $asset->id, 'cantidad_vigente' => $quantity, 'moneda' => $asset->moneda, 'valor' => $value];
        }
        foreach (ProyectoObra::whereDate('fecha', '<=', $date)->get() as $project) {
            $report = $this->project($project, $date);
            foreach (['NIO', 'USD'] as $currency) {
                $parts['obra_neta'][$currency] = F::add($parts['obra_neta'][$currency], $report['Totales']['neto'][$currency]);
            }
            $sources['obras'][] = ['id' => $project->id, 'totales' => $report['Totales']];
        }
        foreach (ComisionAdministrativa::where('estado', 'registrado')->where('tipo', 'proveedor')->where('naturaleza', 'cobrar')->whereDate('fecha', '<=', $date)->get() as $commission) {
            $paid = F::sum(AplicacionFinanciera::where('id_comision', $commission->id)->where('estado', 'registrado')
                ->whereBetween('fecha', [$from, $date])->get(), 'monto_destino');
            $parts['comisiones_recibidas'][$commission->moneda] = F::add($parts['comisiones_recibidas'][$commission->moneda], $paid);
            if (F::cmp($paid, 0) > 0) {
                $sources['comisiones'][] = ['id' => $commission->id, 'moneda' => $commission->moneda, 'recibido' => $paid];
            }
        }
        $nio = $metrics['contado_cordobas'];
        $usd = $metrics['contado_dolares'];
        foreach (['bancos', 'pendientes_netos', 'gastos', 'activos', 'obra_neta'] as $part) {
            $nio = F::add($nio, $parts[$part]['NIO']);
            $usd = F::add($usd, $parts[$part]['USD']);
        }
        $nio = F::sub(F::sub(F::sub($nio, F::add($metrics['wu_cordobas'], $metrics['biller_cordobas'])),
            $parts['comisiones_recibidas']['NIO']), $parts['deudas']['NIO']);
        $airpakUsd = F::sub(F::sub($metrics['wu_dolares'], $settlement->saldo_anterior_usd),
            F::add($settlement->anticipo_usd, $settlement->comision_anticipo_usd));
        $usd = F::sub(F::sub(F::sub(F::add($usd, $airpakUsd), $parts['deudas']['USD']), $metrics['biller_dolares']), $parts['comisiones_recibidas']['USD']);
        $total = F::add($usd, F::div($nio, $rate));
        $workingCapital = null;
        if (! empty($data['cuentas_capital_trabajo'])) {
            $accountIds = array_map('intval', $data['cuentas_capital_trabajo']);
            $banks = $this->currencies();
            foreach ($accountIds as $accountId) {
                $account = CuentaFinanciera::findOrFail($accountId);
                F::check(in_array($account->tipo, ['banco', 'pos'], true) && $account->rubro_capital === 'banco'
                    && $account->fecha_apertura->toDateString() <= $date, 'cuentas_capital_trabajo', 'Seleccione bancos/POS del corte incluidos como bancos');
                $banks[$account->moneda] = F::add($banks[$account->moneda], $diaryBalances->has($account->id)
                    ? $diaryBalances[$account->id]['saldo_final'] : $ledger->balance($account, $date)['saldo']);
            }
            $workingCapital = ['cuentas' => $accountIds, 'bancos' => $banks,
                'valor_usd' => F::add(F::add(F::div(F::add($metrics['contado_cordobas'], $banks['NIO']), $rate),
                    F::add($metrics['contado_dolares'], $banks['USD'])), $settlement->resultado['proyeccion_usd']),
                'formula' => '(contado_NIO + bancos_seleccionados_NIO)/TC + contado_USD + bancos_seleccionados_USD + proyeccion_USD'];
        }

        return ['fecha_corte' => $date, 'periodo_desde' => $from, 'id_arqueo_madre' => $root->id,
            'version_arqueo_madre' => $root->version, 'id_liquidacion' => $settlement->id,
            'tasa_referencia' => $rate, 'componentes' => $parts, 'metricas_caja' => $metrics,
            'saldo_airpak_usd' => $airpakUsd, 'neto_nio' => $nio, 'neto_usd' => $usd,
            'capital_grupo_ajustado_usd' => $total, 'pendientes' => $pending, 'cuentas_excluidas' => $excluded,
            'capital_trabajo_excel' => $workingCapital,
            'libro_diario' => $diarySource,
            'fuentes' => $sources, 'conciliaciones' => ConciliacionMatriz::where('id_arqueo_madre', $root->id)->orderBy('id')->get()->toArray(),
            'formula_version' => 'capital_grupo_ajustado_v1'];
    }

    public function prepareClose(Request $request, array $data): array
    {
        Validator::make($request->all(), ['confirmar_fuentes' => 'required|accepted'])->validate();
        F::openDate($data['fecha']);
        $last = CierreFinanciero::orderByDesc('fecha')->first();
        F::check(! $last || $data['fecha'] > $last->fecha->toDateString(), 'fecha', 'Los cierres financieros deben avanzar en fecha');
        $snapshot = $this->calculate($data);
        F::check($snapshot['pendientes'] === [], 'cierre', 'Hay pendientes financieros o saldos distintos al Libro Diario; revise el resumen');
        $reconciliation = ConciliacionMatriz::where('id_arqueo_madre', $data['id_arqueo_madre'])->orderByDesc('id')->first();
        F::check($reconciliation && in_array($reconciliation->estado, ['conciliado', 'revisado'], true),
            'cierre', 'Confirme la conciliacion de matriz o revise sus diferencias antes de cerrar');
        $data['datos'] = $snapshot;
        $data['huella'] = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
        $data['cerrado_at'] = Carbon::now();

        return $data;
    }
}
