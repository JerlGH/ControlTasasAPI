<?php

namespace App\Services\Administracion;

use App\Models\Administracion\ArqueoMadre;
use App\Models\Administracion\DetalleArqueoMadre;
use App\Models\Arqueo;
use App\Models\User;
use App\Services\ArqueoService;
use App\Support\Administracion\Finanzas as F;
use App\Support\Caja\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class ConsolidacionService
{
    private const METRICAS = ['contado_cordobas', 'contado_dolares', 'utilidad_dolares', 'compras_dolares',
        'ventas_dolares', 'wu_cordobas', 'wu_dolares', 'biller_cordobas', 'biller_dolares',
        'diferencia_cordobas', 'diferencia_dolares', 'proyeccion_cordobas'];

    public function prepare(array $data, ?ArqueoMadre $record): array
    {
        Validator::make($data, ['usuarios_esperados' => 'required|array|min:1',
            'usuarios_esperados.*' => 'required|integer|min:1|distinct|exists:users,id'])->validate();
        $data['usuarios_esperados'] = array_map('intval', $data['usuarios_esperados']);
        if (! empty($data['id_libro_diario'])) {
            $this->diary($data);
        }
        if ($record) {
            F::check($record->estado === 'borrador', 'estado', 'El corte esta cerrado o anulado; cree otra version');
        } else {
            $data['alcance'] = empty($data['id_subagencia']) ? 'grupo' : 'agencia:'.$data['id_subagencia'];
            $data['version'] = 1 + (int) ArqueoMadre::whereDate('fecha', $data['fecha'])->where('alcance', $data['alcance'])->max('version');
        }

        return $data;
    }

    public function calculate(Request $request, ArqueoMadre $root, bool $persist = false, bool $close = false): array
    {
        if ($root->estado === 'cerrado') {
            F::check(! $persist, 'estado', 'El corte cerrado conserva su version');

            return ['Registro' => $root, 'Totales' => $root->totales, 'Fuentes' => DetalleArqueoMadre::where('id_arqueo_madre', $root->id)->get()];
        }
        F::check($root->estado === 'borrador', 'estado', 'La consolidacion esta anulada');
        $zero = array_fill_keys(self::METRICAS, F::amount());
        $totals = $zero;
        $agencies = [];
        $pending = [];
        $sources = [];
        $received = $closed = 0;
        if ($persist) {
            DetalleArqueoMadre::where('id_arqueo_madre', $root->id)->whereNotIn('id_usuario', $root->usuarios_esperados)->delete();
        }
        foreach ($root->usuarios_esperados as $owner) {
            $day = Arqueo::where('id_usuario', $owner)->whereDate('fecha', $root->fecha->toDateString())->lockForUpdate()->first();
            $agency = $day?->id_subagencia ?? $root->id_subagencia ?? \App\Support\Caja\Access::agency(User::findOrFail($owner));
            F::check(! $root->id_subagencia || ! $day || $day->id_subagencia === $root->id_subagencia,
                'usuarios_esperados', 'Un arqueo pertenece a otra agencia en la fecha del corte');
            $state = $day?->estado ?? 'pendiente';
            $values = null;
            if ($day && $day->estado !== 'anulado') {
                $received++;
                if ($day->estado === 'cerrado') {
                    $closed++;
                } else {
                    $pending[] = ['id_usuario' => $owner, 'motivo' => 'arqueo_abierto'];
                }
                $summary = (new ArqueoService)->summary($day);
                foreach ($summary['pendientes'] as $field) {
                    $pending[] = ['id_usuario' => $owner, 'motivo' => $field];
                }
                $metrics = $zero;
                foreach (['cordobas' => 'NIO', 'dolares' => 'USD'] as $suffix => $currency) {
                    $metrics['contado_'.$suffix] = $summary['contado'][$currency];
                    $metrics['diferencia_'.$suffix] = $summary['diferencia'][$currency];
                    $metrics['biller_'.$suffix] = $day->{'biller_'.$suffix};
                }
                $metrics['wu_cordobas'] = F::sub($day->envios_cordobas, $day->pagos_cordobas);
                $metrics['wu_dolares'] = F::sub($day->pagos_dolares, $day->envios_dolares);
                $rate = $day->tasa_referencia ?? $root->tasa_referencia;
                $profitNio = F::amount();
                $operations = $day->operaciones_cambio()->where('estado', '!=', 'anulado')->orderBy('id')->get();
                foreach ($operations as $operation) {
                    $field = $operation->tipo === 'Compra' ? 'compras_dolares' : 'ventas_dolares';
                    $metrics[$field] = F::add($metrics[$field], $operation->monto_dolares);
                    $equivalent = F::mul($operation->monto_dolares, $rate);
                    $profitNio = F::add($profitNio, $operation->tipo === 'Compra'
                        ? F::sub($equivalent, $operation->monto_cordobas) : F::sub($operation->monto_cordobas, $equivalent));
                }
                $metrics['utilidad_dolares'] = F::add(F::div(F::add($profitNio, $summary['ganancias_cheques']['NIO']), $rate), $summary['ganancias_cheques']['USD']);
                $metrics['proyeccion_cordobas'] = F::sub(F::mul(F::sub($metrics['wu_dolares'], $metrics['biller_dolares']), $root->tasa_referencia),
                    F::add($metrics['wu_cordobas'], $metrics['biller_cordobas']));
                $values = ['metricas' => $metrics, 'tasa_utilidad' => $rate,
                    'origen_tasa' => $day->tasa_referencia === null ? 'consolidacion' : 'arqueo',
                    'arqueo' => $day->getAttributes(), 'operaciones_cambio' => $operations->toArray(),
                    'resumen' => $summary];
                $key = (string) ($agency ?? 'sin_agencia');
                $agencies[$key] ??= ['id_subagencia' => $agency, 'metricas' => $zero];
                foreach (self::METRICAS as $metric) {
                    $totals[$metric] = F::add($totals[$metric], $metrics[$metric]);
                    $agencies[$key]['metricas'][$metric] = F::add($agencies[$key]['metricas'][$metric], $metrics[$metric]);
                }
            } else {
                $pending[] = ['id_usuario' => $owner, 'motivo' => $day ? 'arqueo_anulado' : 'arqueo_ausente'];
            }
            $source = ['id_usuario' => (int) $owner, 'id_subagencia' => $agency, 'id_arqueo' => $day?->id,
                'estado_fuente' => $state, 'datos' => $values, 'huella' => hash('sha256', json_encode($values, JSON_THROW_ON_ERROR))];
            if ($persist) {
                DetalleArqueoMadre::updateOrCreate(['id_arqueo_madre' => $root->id, 'id_usuario' => (int) $owner],
                    $source + ['id_usuario_registro' => Access::user($request)->id]);
            }
            $sources[] = $source;
        }
        $result = ['metricas' => $totals, 'por_agencia' => array_values($agencies), 'pendientes' => $pending,
            'cobertura' => ['esperados' => count($root->usuarios_esperados), 'recibidos' => $received, 'cerrados' => $closed],
            'completo' => $pending === [], 'calculado_at' => Carbon::now()->format('Y-m-d H:i:s')];
        if ($root->id_libro_diario) {
            $diary = $this->diary($root->toArray());
            $coverage = LibroDiarioService::cashCoverage($diary->resultado);
            if (! $coverage['coincide']) {
                $pending[] = ['motivo' => 'fuentes_distintas_libro_diario', 'cobertura' => $coverage];
                $result['pendientes'] = $pending;
                $result['completo'] = false;
            }
            // Evidencia de los seis saldos; no se agrega otra vez al contado de cajeros.
            $result['libro_diario'] = ['id' => $diary->id, 'fecha' => F::date($diary->fecha), 'version' => $diary->version,
                'huella' => $diary->huella, 'saldos' => $diary->resultado['saldos'], 'con_diferencias' => $diary->resultado['con_diferencias']];
        }
        if ($close) {
            F::check($pending === [], 'cierre', 'Faltan arqueos cerrados o datos de captura; revise pendientes');
        }
        if ($persist) {
            $before = $root->getAttributes();
            $root->totales = $result;
            if ($close) {
                $root->estado = 'cerrado';
                $root->cerrado_at = Carbon::now();
            }
            $root->save();
            F::audit($request, $root, $close ? 'cerrar' : 'recalcular', $before);
        }

        return ['Registro' => $root->fresh(), 'Totales' => $result, 'Fuentes' => $sources];
    }

    private function diary(array $data): \App\Models\Administracion\LibroDiario
    {
        $diary = \App\Models\Administracion\LibroDiario::findOrFail($data['id_libro_diario']);
        $owners = array_map('intval', $data['usuarios_esperados']);
        $expected = $diary->usuarios_esperados;
        sort($owners);
        sort($expected);
        F::check(empty($data['id_subagencia']) && $diary->estado === 'cerrado' && F::date($diary->fecha) === F::date($data['fecha'])
            && $owners === $expected, 'id_libro_diario', 'El libro debe estar cerrado y corresponder al mismo grupo, fecha y cajeros');

        return $diary;
    }
}
