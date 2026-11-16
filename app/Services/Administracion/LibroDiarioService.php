<?php

namespace App\Services\Administracion;

use App\Models\Administracion\FuenteLibroDiario;
use App\Models\Administracion\LibroDiario;
use App\Models\Administracion\RevisionLibroDiario;
use App\Models\Arqueo;
use App\Services\ArqueoService;
use App\Support\Administracion\Finanzas as F;
use App\Support\Caja\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class LibroDiarioService
{
    private const BLOCKS = ['bancario', 'pases', 'entre_agencias', 'gastos', 'cuentas', 'lafise', 'banpro'];

    public static function bank(string $value): ?string
    {
        return match (preg_replace('/[^a-z]/', '', strtolower($value))) {
            'lafise', 'bancolafise', 'bancentro', 'lafisebancentro' => 'lafise',
            'banpro', 'bancobanpro' => 'banpro', default => null,
        };
    }

    public static function previous(string $date): ?LibroDiario
    {
        return LibroDiario::whereDate('fecha', '<', $date)->where('estado', 'cerrado')
            ->orderByDesc('fecha')->orderByDesc('version')->first();
    }

    public static function cashCoverage(array $snapshot): array
    {
        $expected = array_map('intval', array_column($snapshot['fuentes'], 'id_arqueo'));
        $actual = Arqueo::whereDate('fecha', $snapshot['fecha'])->where('estado', '!=', 'anulado')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        sort($expected);
        sort($actual);

        return ['coincide' => $expected === $actual, 'ids_corte' => $expected, 'ids_actuales' => $actual];
    }

    private function hash(mixed $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function zero(): array
    {
        $values = array_fill_keys(['diferencia_equivalente_nio', 'apertura_nio', 'apertura_usd'], F::amount());
        foreach (self::BLOCKS as $block) {
            foreach (['entrada', 'salida'] as $direction) {
                foreach (['nio', 'usd'] as $currency) {
                    $values[$block.'_'.$direction.'_'.$currency] = F::amount();
                }
            }
        }

        return $values;
    }

    private function control(array &$controls, string $key, bool $matches, array $data, bool $review = false): void
    {
        $controls[] = ['clave' => $key, 'coincide' => $matches, 'requiere_revision' => $review || ! $matches,
            'datos' => $data, 'huella' => $this->hash([$key, $matches, $data])];
    }

    public function calculate(LibroDiario $root): array
    {
        if ($root->estado === 'cerrado') {
            return $root->resultado; // Nunca reconstruir un corte cerrado con fuentes actuales.
        }
        F::check($root->estado === 'borrador', 'estado', 'El libro diario esta anulado');
        $date = F::date($root->fecha);
        $totals = $this->zero();
        $byAgency = $sources = $movements = $controls = $pending = [];
        $counts = ['esperados' => count($root->usuarios_esperados), 'recibidos' => 0, 'cerrados' => 0];
        $extra = Arqueo::whereDate('fecha', $date)->where('estado', '!=', 'anulado')->whereNotIn('id_usuario', $root->usuarios_esperados)->orderBy('id')->pluck('id')->all();
        if ($extra !== []) {
            $pending[] = ['tipo' => 'arqueos_fuera_alcance', 'ids' => $extra];
        }
        foreach ($root->usuarios_esperados as $user) {
            $day = Arqueo::whereDate('fecha', $date)->where('id_usuario', $user)->lockForUpdate()->first();
            $values = $this->zero();
            $data = null;
            if ($day && $day->estado !== 'anulado') {
                $counts['recibidos']++;
                if ($day->estado === 'cerrado') {
                    $counts['cerrados']++;
                } else {
                    $pending[] = ['tipo' => 'arqueo_abierto', 'id_arqueo' => $day->id];
                }
                $summary = (new ArqueoService)->summary($day);
                foreach ($summary['pendientes'] as $field) {
                    $pending[] = ['tipo' => $field, 'id_arqueo' => $day->id];
                }
                if ($day->tasa_referencia === null || F::cmp($day->tasa_referencia, 0) <= 0) {
                    $pending[] = ['tipo' => 'tasa_individual_ausente', 'id_arqueo' => $day->id];
                    $values['diferencia_equivalente_nio'] = null;
                } elseif ($summary['diferencia']['NIO'] === null || $summary['diferencia']['USD'] === null) {
                    $values['diferencia_equivalente_nio'] = null;
                } else {
                    $values['diferencia_equivalente_nio'] = F::add($summary['diferencia']['NIO'], F::mul($summary['diferencia']['USD'], $day->tasa_referencia));
                }
                $values['apertura_nio'] = F::amount($day->caja_inicial_cordobas);
                $values['apertura_usd'] = F::amount($day->caja_inicial_dolares);
                $rows = [];
                foreach (['entradas_y_salidas', 'pases', 'remesas'] as $relation) {
                    $rows[$relation] = $day->$relation()->where('estado', '!=', 'anulado')->orderBy('id')->get()->toArray();
                    foreach ($rows[$relation] as $row) {
                        $block = $relation === 'pases' ? 'pases' : ($relation === 'remesas' ? self::bank($row['banco']) :
                            match ($row['categoria']) {
                                'gasto' => 'gastos', 'varios' => 'cuentas', default => $row['categoria']
                            });
                        $source = ['origen_tipo' => $relation, 'id_origen' => $row['id'], 'id_arqueo' => $day->id,
                            'id_usuario' => $day->id_usuario, 'id_subagencia' => $day->id_subagencia, 'id_caja' => $day->id_caja, 'bloque' => $block, 'registro' => $row];
                        $movements[] = $source;
                        if ($block === null) {
                            $this->control($controls, 'banco_remesa:'.$row['id'], false, $source);

                            continue;
                        }
                        $field = $block.'_'.strtolower($row['tipo']).'_'.strtolower($row['moneda']);
                        $values[$field] = F::add($values[$field], $row['monto']);
                    }
                }
                $owner = $day->usuarios;
                $data = ['metricas' => $values, 'arqueo' => $day->getAttributes(), 'resumen' => $summary, 'movimientos' => $rows,
                    'operador' => ['id' => $owner->id, 'usuario' => $owner->usuario, 'nombre' => $owner->trabajadores?->nombre],
                    'agencia' => ['id' => $day->id_subagencia, 'nombre' => $day->subagencia?->nombre],
                    'conteos' => $day->detalle_arqueo()->orderBy('id')->get()->toArray(), 'custodias' => $day->custodias()->orderBy('id')->get()->toArray(),
                    'cambios' => $day->operaciones_cambio()->where('estado', '!=', 'anulado')->orderBy('id')->get()->toArray(),
                    'cheques' => $day->cheques()->where('estado', '!=', 'anulado')->orderBy('id')->get()->toArray()];
                $this->control($controls, 'cajero:'.$user, $summary['pendientes'] === [] && $summary['diferencia']['NIO'] !== null
                    && $summary['diferencia']['USD'] !== null && F::cmp($summary['diferencia']['NIO'], 0) === 0 && F::cmp($summary['diferencia']['USD'], 0) === 0, $data, true);
                $agency = (string) $day->id_subagencia;
                $byAgency[$agency] ??= ['id_subagencia' => $day->id_subagencia, 'metricas' => $this->zero()];
                foreach ($values as $field => $value) {
                    // Totales disponibles; la cobertura declara explicitamente las fuentes incompletas.
                    $totals[$field] = F::add($totals[$field], $value);
                    $byAgency[$agency]['metricas'][$field] = F::add($byAgency[$agency]['metricas'][$field], $value);
                }
            } else {
                $pending[] = ['tipo' => $day ? 'arqueo_anulado' : 'arqueo_ausente', 'id_usuario' => $user];
            }
            $sources[] = ['id_usuario' => $user, 'id_arqueo' => $day?->id, 'id_subagencia' => $day?->id_subagencia,
                'estado_fuente' => $day?->estado ?? 'pendiente', 'datos' => $data, 'huella' => $this->hash($data)];
        }
        $this->pairs($movements, $controls, $totals);
        $report = $root->reporte_remesas;
        $central = $report?->detalles()->where('estado', 'registrado')->orderBy('id')->get()->toArray() ?? [];
        $agencyIds = array_map('intval', array_keys($byAgency));
        sort($agencyIds);
        $expectedAgencies = $report?->agencias_esperadas ?? [];
        sort($expectedAgencies);
        $centralReady = $report && $report->estado === 'confirmado' && F::date($report->fecha) === $date && $expectedAgencies === $agencyIds;
        if (! $centralReady) {
            $pending[] = ['tipo' => 'reporte_remesas_incompleto_o_alcance_distinto', 'agencias_cajeros' => $agencyIds];
        }
        $this->control($controls, 'reporte_central', (bool) $centralReady, ['reporte' => $report?->getAttributes(), 'lineas' => $central, 'agencias_cajeros' => $agencyIds], true);
        foreach ($byAgency as $agency) {
            foreach (['lafise', 'banpro'] as $bank) {
                foreach (['NIO', 'USD'] as $currency) {
                    foreach (['Entrada', 'Salida'] as $type) {
                        $other = $type === 'Entrada' ? 'Salida' : 'Entrada';
                        $line = collect($central)->where('id_subagencia', $agency['id_subagencia'])->where('banco', $bank)->where('moneda', $currency)->firstWhere('tipo', $other);
                        $declared = $agency['metricas'][$bank.'_'.strtolower($type).'_'.strtolower($currency)];
                        $diff = $line ? ($type === 'Entrada' ? F::sub($declared, $line['monto']) : F::sub($line['monto'], $declared)) : null;
                        $this->control($controls, 'remesa:'.$agency['id_subagencia'].':'.$bank.':'.$currency.':'.$type,
                            $diff !== null && F::cmp($diff, 0) === 0, ['id_subagencia' => $agency['id_subagencia'], 'banco' => $bank, 'moneda' => $currency,
                                'direccion_cajero' => $type, 'declarado_cajeros' => $declared, 'direccion_central' => $other, 'linea_central' => $line, 'diferencia' => $diff]);
                    }
                }
            }
        }
        $details = $root->detalles_cuenta()->with(['cuenta_auxiliar', 'contraparte', 'documento', 'operacion'])->where('estado', 'registrado')->orderBy('id')->get();
        $accountTotals = array_fill_keys(['entrada_nio', 'salida_nio', 'entrada_usd', 'salida_usd'], F::amount());
        $accountDetail = [];
        foreach ($details as $detail) {
            try {
                (new RegistroLibroDiarioService)->validateDetail($root, $detail->getAttributes(), $detail);
            } catch (ValidationException $e) {
                $pending[] = ['tipo' => 'detalle_cuenta_invalido', 'id_detalle' => $detail->id, 'errores' => $e->errors()];
            }
            $source = collect($movements)->first(fn ($m) => $m['origen_tipo'] === 'entradas_y_salidas' && $m['id_origen'] === $detail->id_entrada_salida && $m['bloque'] === 'cuentas');
            if (! $source) {
                $pending[] = ['tipo' => 'detalle_cuenta_fuera_alcance', 'id_detalle' => $detail->id];

                continue;
            }
            $row = $source['registro'];
            $key = strtolower($row['tipo']).'_'.strtolower($row['moneda']);
            $accountTotals[$key] = F::add($accountTotals[$key], $row['monto']);
            $accountDetail[] = ['detalle' => $detail->toArray(), 'origen' => $source,
                'cuenta' => $detail->cuenta_auxiliar?->toArray()];
        }
        $missingDetails = array_values(array_map(fn ($m) => $m['id_origen'], array_filter($movements,
            fn ($m) => $m['bloque'] === 'cuentas' && ! $details->contains('id_entrada_salida', $m['id_origen']))));
        if ($missingDetails !== []) {
            $pending[] = ['tipo' => 'detalle_cuentas_incompleto', 'ids_entrada_salida' => $missingDetails];
        }
        $accountDiffs = [];
        foreach ($accountTotals as $field => $total) {
            $accountDiffs[$field] = F::sub($total, $totals['cuentas_'.$field]);
        }
        $this->control($controls, 'detalle_cuentas', $missingDetails === [] && count($accountDetail) === $details->count()
            && collect($accountDiffs)->every(fn ($v) => F::cmp($v, 0) === 0), ['totales_detalle' => $accountTotals, 'diferencias' => $accountDiffs,
                'faltantes' => $missingDetails, 'partidas' => $accountDetail], true);

        $parts = $root->partidas()->with(['operacion', 'contraparte', 'documento'])->where('estado', 'registrado')->orderBy('id')->get();
        foreach ($parts as $part) {
            try {
                (new RegistroLibroDiarioService)->validatePartida($root, $part->getAttributes());
            } catch (ValidationException $e) {
                $pending[] = ['tipo' => 'partida_invalida', 'id_partida' => $part->id, 'errores' => $e->errors()];
            }
            $op = $part->operacion;
            if ($op->estado !== 'registrado' || F::date($op->fecha) !== $date || ($op->origen_snapshot
                && ! collect($sources)->contains('id_arqueo', $op->origen_snapshot['id_arqueo']))) {
                $pending[] = ['tipo' => 'partida_fuera_alcance_o_anulada', 'id_partida' => $part->id];
            }
        }
        $bankProof = [];
        foreach (['NIO', 'USD'] as $currency) {
            foreach (['Entrada', 'Salida'] as $direction) {
                $declared = F::amount();
                $bank = $airpak = F::amount();
                $ids = [];
                foreach ($movements as $m) {
                    if ($m['bloque'] !== 'bancario' || $m['registro']['medio'] === 'transferencia' || $m['registro']['moneda'] !== $currency || $m['registro']['tipo'] !== $direction) {
                        continue;
                    }
                    $declared = F::add($declared, $m['registro']['monto']);
                    $part = $parts->first(fn ($p) => $p->operacion->id_entrada_salida === $m['id_origen'] && in_array($p->bloque, ['banco_cajeros', 'efectivo_airpak'], true));
                    if (! $part) {
                        $ids[] = $m['id_origen'];
                    } elseif ($part->bloque === 'banco_cajeros') {
                        $bank = F::add($bank, $part->operacion->monto);
                    } else {
                        $airpak = F::add($airpak, $part->operacion->monto);
                    }
                }
                $diff = F::sub($declared, F::add($bank, $airpak));
                $bankProof[$currency][$direction] = ['declarado_caja' => $declared, 'reconocido_banco' => $bank, 'efectivo_airpak' => $airpak, 'diferencia' => $diff, 'origenes_sin_contraparte' => $ids];
                $this->control($controls, 'banco_cajeros:'.$currency.':'.$direction, F::cmp($diff, 0) === 0 && $ids === [], $bankProof[$currency][$direction]);
            }
        }
        $accounts = $root->cuentas()->with('cuenta')->where('estado', 'registrado')->orderBy('canal')->orderBy('moneda')->get();
        if ($accounts->count() !== 6) {
            $pending[] = ['tipo' => 'cuentas_banco_pos_incompletas', 'recibidas' => $accounts->count(), 'esperadas' => 6];
        }
        $balances = [];
        $previous = self::previous($date);
        foreach ($accounts as $mapping) {
            $account = $mapping->cuenta;
            $ledgerService = new LedgerService;
            $ledger = $ledgerService->balance($account, $date, false);
            $openingLedger = $ledgerService->balance($account, Carbon::parse($date)->subDay()->toDateString(), false);
            $opening = F::date($account->fecha_apertura) === $date ? $account->saldo_inicial
                : $openingLedger['saldo'];
            $entries = $exits = $posEntries = $posExits = F::amount();
            $bankRows = [];
            foreach ($ledger['movimientos'] as $item) {
                $row = $item['movimiento'];
                if (F::date($row->fecha) !== $date) {
                    continue;
                }
                $part = $parts->firstWhere('id_operacion', $row->id_operacion);
                if (! $part) {
                    $pending[] = ['tipo' => 'movimiento_bancario_sin_clasificar', 'id_movimiento' => $row->id, 'id_operacion' => $row->id_operacion];
                }
                if ($row->tipo === 'cargo') {
                    $entries = F::add($entries, $row->monto);
                    if ($part?->bloque === 'remesa_pos') {
                        $posEntries = F::add($posEntries, $row->monto);
                    }
                } else {
                    $exits = F::add($exits, $row->monto);
                    if ($part?->bloque === 'remesa_pos') {
                        $posExits = F::add($posExits, $row->monto);
                    }
                }
                $bankRows[] = ['movimiento' => $row->toArray(), 'partida' => $part?->toArray()];
            }
            $key = $mapping->canal.':'.$mapping->moneda;
            $prior = $previous?->resultado['saldos'][$key] ?? null;
            $continuity = $prior && $prior['id_cuenta'] === $mapping->id_cuenta ? F::sub($opening, $prior['saldo_final']) : null;
            $openingFingerprint = $ledgerService->fingerprint($openingLedger);
            $sameHistory = ! isset($prior['huella_auxiliar']) || hash_equals($prior['huella_auxiliar'], $openingFingerprint);
            $validOpening = $previous ? $continuity !== null && F::cmp($continuity, 0) === 0 && $sameHistory : ! empty($root->motivo_apertura);
            if (! $previous && empty($root->motivo_apertura)) {
                $pending[] = ['tipo' => 'motivo_apertura_inicial_ausente'];
            }
            if ($account->estado !== 'activo' || F::date($account->fecha_apertura) > $date || $account->moneda !== $mapping->moneda
                || $account->tipo !== ($mapping->canal === 'bancentro' ? 'banco' : 'pos') || $account->rubro_capital !== 'banco') {
                $pending[] = ['tipo' => 'cuenta_incompatible', 'id_cuenta' => $account->id];
            }
            $balance = ['id_cuenta' => $account->id, 'canal' => $mapping->canal, 'moneda' => $mapping->moneda, 'cuenta' => $account->toArray(),
                'saldo_inicial' => F::amount($opening), 'entradas' => $entries, 'salidas' => $exits, 'saldo_final' => $ledger['saldo'],
                'id_diario_anterior' => $previous?->id, 'saldo_cierre_anterior' => $prior['saldo_final'] ?? null,
                'huella_auxiliar' => $ledgerService->fingerprint($ledger), 'huella_auxiliar_apertura' => $openingFingerprint,
                'huella_auxiliar_anterior' => $prior['huella_auxiliar'] ?? null, 'historial_apertura_coincide' => $sameHistory,
                'diferencia_continuidad' => $continuity, 'motivo_apertura' => $root->motivo_apertura, 'movimientos' => $bankRows];
            $this->control($controls, 'apertura:'.$key, $validOpening, array_diff_key($balance, array_flip(['movimientos', 'entradas', 'salidas', 'saldo_final', 'huella_auxiliar'])));
            $this->control($controls, 'cuenta:'.$key, true, $balance, true);
            if ($mapping->canal !== 'bancentro') {
                $centralEntries = F::sum(collect($central)->where('banco', $mapping->canal)->where('moneda', $mapping->moneda)->where('tipo', 'Entrada'), 'monto');
                $centralExits = F::sum(collect($central)->where('banco', $mapping->canal)->where('moneda', $mapping->moneda)->where('tipo', 'Salida'), 'monto');
                $diffIn = F::sub($posEntries, $centralEntries);
                $diffOut = F::sub($posExits, $centralExits);
                $balance['conciliacion_central'] = ['entradas_central' => $centralEntries, 'salidas_central' => $centralExits,
                    'entradas_reconocidas' => $posEntries, 'salidas_reconocidas' => $posExits, 'diferencia_entradas' => $diffIn, 'diferencia_salidas' => $diffOut,
                    'saldo_segun_central_y_otros_movimientos' => F::add($ledger['saldo'], F::sub($diffOut, $diffIn))];
                $this->control($controls, 'pos:'.$key, (bool) $centralReady && F::cmp($diffIn, 0) === 0 && F::cmp($diffOut, 0) === 0, $balance);
            }
            $balances[$key] = $balance;
        }
        $accountIds = $accounts->pluck('id_cuenta')->all();
        foreach ($parts as $part) {
            if ($part->bloque !== 'efectivo_airpak' && ! in_array($part->operacion->id_cuenta_origen, $accountIds, true) && ! in_array($part->operacion->id_cuenta_destino, $accountIds, true)) {
                $pending[] = ['tipo' => 'partida_sin_cuenta_del_diario', 'id_partida' => $part->id];
            }
        }
        $reviews = RevisionLibroDiario::where('id_libro_diario', $root->id)->orderBy('id')->get();
        $pendingReviews = [];
        foreach ($controls as &$control) {
            $review = $reviews->where('clave_control', $control['clave'])->where('huella', $control['huella'])->last();
            $control['revision'] = $review?->toArray();
            $control['estado_revision'] = $review?->resultado ?? 'pendiente';
            if ($control['requiere_revision'] && ! in_array($control['estado_revision'], ['revisado', 'justificado'], true)) {
                $pendingReviews[] = $control['clave'];
            }
        }
        unset($control);

        return ['fecha' => $date, 'version' => $root->version, 'id_diario_anterior' => $previous?->id, 'metricas' => $totals, 'por_agencia' => array_values($byAgency),
            'fuentes' => $sources, 'movimientos' => $movements, 'cobertura' => $counts, 'pendientes' => $pending, 'completo' => $pending === [],
            'detalle_cuentas' => $accountDetail, 'cuadre_cuentas' => $accountDiffs, 'cuadre_banco_cajeros' => $bankProof,
            'reporte_remesas' => ['cabecera' => $report?->toArray(), 'lineas' => $central], 'partidas' => $parts->toArray(),
            'saldos' => $balances, 'controles' => $controls, 'revisiones_pendientes' => $pendingReviews,
            'con_diferencias' => collect($controls)->contains(fn ($c) => ! $c['coincide'])];
    }

    private function pairs(array $movements, array &$controls, array $totals): void
    {
        foreach (['pases', 'entre_agencias'] as $block) {
            foreach (['nio', 'usd'] as $currency) {
                $diff = F::sub($totals[$block.'_entrada_'.$currency], $totals[$block.'_salida_'.$currency]);
                $this->control($controls, 'total:'.$block.':'.$currency, F::cmp($diff, 0) === 0, ['diferencia' => $diff]);
            }
            $field = $block === 'pases' ? 'id_pase_relacionado' : 'id_movimiento_relacionado';
            $items = collect($movements)->where('bloque', $block)->keyBy('id_origen');
            $seen = [];
            foreach ($items as $id => $item) {
                if (in_array($id, $seen, true)) {
                    continue;
                }
                $row = $item['registro'];
                $other = $items->get($row[$field] ?? 0);
                if (! $other && empty($row[$field])) {
                    $other = $items->first(fn ($i) => ($i['registro'][$field] ?? null) === $id);
                }
                $valid = $other && ! in_array($other['id_origen'], $seen, true) && $other['id_arqueo'] !== $item['id_arqueo'] && $other['registro']['moneda'] === $row['moneda']
                    && $other['registro']['tipo'] !== $row['tipo'] && F::cmp($other['registro']['monto'], $row['monto']) === 0
                    && $other['registro']['medio'] === $row['medio']
                    && (empty($row[$field]) || $row[$field] === $other['id_origen'])
                    && (empty($other['registro'][$field]) || $other['registro'][$field] === $id);
                if ($valid && $block === 'pases') {
                    $valid = $item['id_subagencia'] === $other['id_subagencia'] && $item['id_caja'] !== null && $other['id_caja'] !== null && $item['id_caja'] !== $other['id_caja']
                        && (empty($row['id_caja_contraparte']) || $row['id_caja_contraparte'] === $other['id_caja'])
                        && (empty($other['registro']['id_caja_contraparte']) || $other['registro']['id_caja_contraparte'] === $item['id_caja']);
                }
                if ($valid && $block === 'entre_agencias') {
                    $valid = $item['id_subagencia'] !== $other['id_subagencia']
                        && (empty($row['id_subagencia_contraparte']) || $row['id_subagencia_contraparte'] === $other['id_subagencia'])
                        && (empty($other['registro']['id_subagencia_contraparte']) || $other['registro']['id_subagencia_contraparte'] === $item['id_subagencia']);
                }
                $this->control($controls, 'par:'.$block.':'.$id, (bool) $valid, ['origen' => $item, 'contraparte' => $other]);
                $seen[] = $id;
                if ($valid) {
                    $seen[] = $other['id_origen'];
                }
            }
        }
    }

    public function persist(Request $request, LibroDiario $root, bool $close = false): array
    {
        (new RegistroLibroDiarioService)->draft($root);
        $result = $this->calculate($root);
        if ($close) {
            F::check($result['completo'], 'cierre', 'Faltan fuentes, clasificaciones o detalle completo; consulte pendientes');
            F::check($result['revisiones_pendientes'] === [], 'revision', 'Faltan revisiones vigentes o justificaciones de diferencias');
        }
        FuenteLibroDiario::where('id_libro_diario', $root->id)->whereNotIn('id_usuario', $root->usuarios_esperados)->delete();
        foreach ($result['fuentes'] as $source) {
            FuenteLibroDiario::updateOrCreate(['id_libro_diario' => $root->id, 'id_usuario' => $source['id_usuario']], $source);
        }
        $before = $root->getAttributes();
        $root->fill(['resultado' => $result, 'huella' => $this->hash($result), 'id_anterior' => $result['id_diario_anterior']]);
        if ($close) {
            $root->fill(['estado' => 'cerrado', 'cerrado_at' => Carbon::now(), 'id_usuario_cierre' => Access::user($request)->id]);
        }
        $root->save();
        F::audit($request, $root, $close ? 'cerrar' : 'recalcular', $before);

        return $result;
    }
}
