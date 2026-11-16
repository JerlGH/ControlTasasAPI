<?php

namespace App\Services;

use App\Models\Arqueo;
use App\Models\User;
use App\Support\Caja\Access;
use App\Support\Caja\Audit;
use App\Support\Caja\Decimal;
use App\Support\Caja\ConteoEfectivo;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class ArqueoService
{
    public const TOTALES = ['biller_cordobas', 'biller_dolares', 'envios_cordobas',
        'envios_dolares', 'pagos_cordobas', 'pagos_dolares'];

    public function open(Request $request, array $data): Arqueo
    {
        $actor = Access::user($request);
        $owner = User::findOrFail($data['id_usuario'] ?? $actor->id);
        Access::check(Access::admin($request) || $owner->id === $actor->id, 'id_usuario', 'Usuario fuera del alcance permitido');
        $agency = $data['id_subagencia'] ?? Access::agency($owner);
        Access::check($agency !== null && (Access::admin($request) || (int) $agency === Access::agency($owner)),
            'id_subagencia', 'La agencia debe corresponder al cajero');
        if (! empty($data['id_caja'])) {
            $box = \App\Models\Caja::findOrFail($data['id_caja']);
            Access::check($box->id_subagencia === (int) $agency && $box->estado === 'activo', 'id_caja', 'La caja no esta activa en esa agencia');
        }
        Access::check(! Arqueo::where('id_usuario', $owner->id)->where('estado', 'abierto')->exists(),
            'fecha', 'El cajero tiene una jornada abierta');
        Access::check(! Arqueo::where('id_usuario', $owner->id)->where('fecha', '>=', $data['fecha'])->exists(),
            'fecha', 'La apertura debe ser posterior a las jornadas existentes');
        $previous = Arqueo::where('id_usuario', $owner->id)->where('estado', '!=', 'anulado')
            ->orderByDesc('fecha')->lockForUpdate()->first();
        $manual = (bool) ($data['apertura_manual'] ?? false);
        if ($manual && $previous) {
            Access::requireAdmin($request);
        }
        if (! empty($data['id_arqueo_anterior'])) {
            Access::check(! $manual && $previous && $previous->id === (int) $data['id_arqueo_anterior'],
                'id_arqueo_anterior', 'Debe utilizarse el ultimo cierre del mismo cajero');
        }
        $values = array_intersect_key($data, array_flip([...self::TOTALES, 'id_caja', 'fecha', 'tasa_referencia', 'observacion', 'motivo_apertura_manual']));
        $values += ['id_usuario' => $owner->id, 'id_subagencia' => $agency, 'estado' => 'abierto', 'abierto_at' => Carbon::now()];
        if ($previous && ! $manual) {
            Access::check($previous->estado === 'cerrado' && $previous->id_subagencia === (int) $agency,
                'id_arqueo_anterior', 'La continuidad requiere un cierre de la misma agencia; use apertura manual supervisada para un cambio');
            Access::check($previous->caja_final_cordobas !== null && $previous->caja_final_dolares !== null,
                'id_arqueo_anterior', 'El cierre previo no tiene totales contados');
            $values['id_arqueo_anterior'] = $previous->id;
            $values['id_caja'] ??= $previous->id_caja;
            if ($values['id_caja'] !== null) {
                $box = \App\Models\Caja::findOrFail($values['id_caja']);
                Access::check($box->estado === 'activo' && $box->id_subagencia === (int) $agency,
                    'id_caja', 'Seleccione una caja activa para la nueva jornada');
            }
            $values['caja_inicial_cordobas'] = $previous->caja_final_cordobas;
            $values['caja_inicial_dolares'] = $previous->caja_final_dolares;
            $values['cheques_inicial_cordobas'] = $previous->monto_total_cheques;
            $values['cheques_inicial_dolares'] = $previous->monto_total_cheques_dolares;
            $values['tasa_referencia'] ??= $previous->tasa_referencia;
            if (isset($data['tasa_referencia']) && Decimal::compare($data['tasa_referencia'], $previous->tasa_referencia) !== 0) {
                Access::requireAdmin($request);
            }
            foreach (['caja_inicial_cordobas', 'caja_inicial_dolares', 'cheques_inicial_cordobas', 'cheques_inicial_dolares'] as $field) {
                Access::check(! isset($data[$field]) || ($values[$field] !== null && Decimal::compare($data[$field], $values[$field]) === 0),
                    $field, 'El valor debe coincidir con el cierre anterior');
            }
        } else {
            Access::check(! empty($data['motivo_apertura_manual']), 'motivo_apertura_manual', 'Indique el origen del saldo de apertura');
            foreach (['caja_inicial_cordobas', 'caja_inicial_dolares'] as $field) {
                Access::check(isset($data[$field]), $field, 'Indique el saldo de apertura');
                $values[$field] = $data[$field];
            }
            foreach (['cordobas', 'dolares'] as $currency) {
                $values['cheques_inicial_'.$currency] = $data['cheques_inicial_'.$currency] ?? null;
                Access::check($values['cheques_inicial_'.$currency] === null
                    || Decimal::compare($values['cheques_inicial_'.$currency], $values['caja_inicial_'.$currency]) <= 0,
                    'cheques_inicial_'.$currency, 'Los cheques no pueden superar el saldo total');
            }
        }
        if (! empty($values['id_caja'])) {
            $box = \App\Models\Caja::whereKey($values['id_caja'])->lockForUpdate()->firstOrFail();
            Access::check($box->estado === 'activo' && $box->id_subagencia === (int) $agency,
                'id_caja', 'La caja no esta activa en esa agencia');
            Access::check(! Arqueo::where('id_caja', $box->id)->where('estado', 'abierto')->exists(),
                'id_caja', 'La caja ya tiene una jornada abierta');
        }
        $day = Arqueo::create($values);
        if ($previous && ! $manual) {
            foreach ($previous->custodias()->where('momento', 'cierre')->get() as $cheque) {
                $copy = $day->custodias()->create(['id_cheque' => $cheque->id_cheque, 'momento' => 'apertura',
                    'moneda' => $cheque->moneda, 'monto' => $cheque->monto]);
                Audit::record($request, $copy, 'arrastrar', null, $day->id);
            }
        }
        Audit::record($request, $day, 'abrir', null, $day->id);

        return $day;
    }

    public function summary(Arqueo $day, bool $strict = false): array
    {
        $pending = [];
        foreach (self::TOTALES as $field) {
            if ($day->$field === null) {
                $pending[] = $field;
            }
        }
        $expected = ['NIO' => Decimal::value($day->caja_inicial_cordobas), 'USD' => Decimal::value($day->caja_inicial_dolares)];
        foreach (['NIO' => 'cordobas', 'USD' => 'dolares'] as $currency => $suffix) {
            $expected[$currency] = Decimal::add($expected[$currency], $day->{'biller_'.$suffix});
            $expected[$currency] = Decimal::add($expected[$currency], $day->{'envios_'.$suffix});
            $expected[$currency] = Decimal::subtract($expected[$currency], $day->{'pagos_'.$suffix});
        }
        foreach (['entradas_y_salidas', 'pases', 'remesas'] as $relation) {
            foreach ($day->$relation()->where('estado', '!=', 'anulado')->get() as $movement) {
                if ($movement->medio !== 'transferencia') {
                    $expected[$movement->moneda] = $movement->tipo === 'Entrada'
                        ? Decimal::add($expected[$movement->moneda], $movement->monto)
                        : Decimal::subtract($expected[$movement->moneda], $movement->monto);
                }
            }
        }
        foreach ($day->operaciones_cambio()->where('estado', '!=', 'anulado')->get() as $operation) {
            $entry = $operation->tipo === 'Compra' ? 'USD' : 'NIO';
            $exit = $operation->tipo === 'Compra' ? 'NIO' : 'USD';
            if ($operation->medio_entrada !== 'transferencia') {
                $expected[$entry] = Decimal::add($expected[$entry], $entry === 'USD' ? $operation->monto_dolares : $operation->monto_cordobas);
            }
            if ($operation->medio_salida !== 'transferencia') {
                $expected[$exit] = Decimal::subtract($expected[$exit], $exit === 'USD' ? $operation->monto_dolares : $operation->monto_cordobas);
            }
        }
        $gains = ['NIO' => Decimal::value(0), 'USD' => Decimal::value(0)];
        foreach ($day->cheques()->where('estado', '!=', 'anulado')->get() as $cheque) {
            $currency = $cheque->moneda_ganancia ?? $cheque->moneda;
            $gains[$currency] = Decimal::add($gains[$currency], $cheque->ganancia);
            $expected[$currency] = Decimal::add($expected[$currency], $cheque->ganancia);
        }
        $checks = ['NIO' => Decimal::value(0), 'USD' => Decimal::value(0)];
        $custody = $day->custodias()->where('momento', 'cierre')->get();
        foreach ($custody as $cheque) {
            $checks[$cheque->moneda] = Decimal::add($checks[$cheque->moneda], $cheque->monto);
        }
        if ($strict) {
            foreach (['NIO' => 'cordobas', 'USD' => 'dolares'] as $currency => $suffix) {
                $declared = $day->{'cheques_inicial_'.$suffix};
                if ($declared !== null) {
                    $opening = '0';
                    foreach ($day->custodias()->where('momento', 'apertura')->where('moneda', $currency)->get() as $cheque) {
                        $opening = Decimal::add($opening, $cheque->monto);
                    }
                    Access::check(Decimal::compare($opening, $declared) === 0,
                        'cheques_inicial_'.$suffix, 'El detalle de cheques de apertura no corresponde al principal declarado');
                }
            }
        }
        $physical = ['NIO' => null, 'USD' => null];
        foreach (['NIO', 'USD'] as $currency) {
            $count = $day->detalle_arqueo()->where('moneda', $currency)->first();
            if (! $count || $count->estado !== 'contado') {
                $pending[] = 'conteo_'.$currency;
                continue;
            }
            $cash = ConteoEfectivo::total($currency, $count->getAttributes());
            Access::check($count->total !== null && Decimal::compare($cash, $count->total) === 0,
                'conteo_'.$currency, 'El total del conteo no corresponde a sus denominaciones');
            $physical[$currency] = Decimal::add($cash, $checks[$currency]);
        }
        if ($strict) {
            Access::check($pending === [], 'cierre', 'Faltan datos: '.implode(', ', $pending));
        }
        $hasTotals = array_intersect(self::TOTALES, $pending) === [];
        $difference = [];
        foreach (['NIO', 'USD'] as $currency) {
            $difference[$currency] = $hasTotals && $physical[$currency] !== null
                ? Decimal::subtract($physical[$currency], $expected[$currency]) : null;
        }

        return ['pendientes' => $pending, 'esperado' => $hasTotals ? $expected : ['NIO' => null, 'USD' => null],
            'contado' => $physical, 'diferencia' => $difference, 'cheques' => $checks,
            'cantidad_cheques' => $custody->count(), 'ganancias_cheques' => $gains];
    }

    public function close(Request $request, Arqueo $day): Arqueo
    {
        Access::check($day->estado === 'abierto', 'estado', 'La jornada no esta abierta');
        $summary = $this->summary($day, true);
        $before = $day->getAttributes();
        $day->fill([
            'caja_final_cordobas' => $summary['contado']['NIO'], 'caja_final_dolares' => $summary['contado']['USD'],
            'saldo_esperado_cordobas' => $summary['esperado']['NIO'], 'saldo_esperado_dolares' => $summary['esperado']['USD'],
            'diferencia_cordobas' => $summary['diferencia']['NIO'], 'diferencia_dolares' => $summary['diferencia']['USD'],
            'monto_total_cheques' => $summary['cheques']['NIO'], 'monto_total_cheques_dolares' => $summary['cheques']['USD'],
            'cantidad_cheques' => $summary['cantidad_cheques'], 'ganancia_total_cheques' => $summary['ganancias_cheques']['NIO'],
            'ganancia_total_cheques_dolares' => $summary['ganancias_cheques']['USD'],
            'estado' => 'cerrado', 'cerrado_at' => Carbon::now(), 'cheques_confirmados_at' => Carbon::now(),
            'id_usuario_cierre' => Access::user($request)->id,
        ])->save();
        Audit::record($request, $day, 'cerrar', $before, $day->id);

        return $day;
    }
}
