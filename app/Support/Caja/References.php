<?php

namespace App\Support\Caja;

use App\Models\Arqueo;
use App\Models\Cheques;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class References
{
    public static function pair(Request $request, array $values, ?Model $record, Arqueo $day, string $model, string $field): void
    {
        if (empty($values[$field])) {
            if ($record && $record->$field && $request->exists($field)) {
                Access::requireAdmin($request);
            }
            return;
        }
        if (! $record || (int) $record->$field !== (int) $values[$field]) {
            Access::requireAdmin($request);
        }
        $other = $model::with('arqueo')->findOrFail($values[$field]);
        Access::check(! $record || $other->id !== $record->id, $field, 'Un movimiento no puede relacionarse consigo mismo');
        Access::check($other->id_arqueo !== $day->id && $other->estado !== 'anulado' && $other->arqueo->estado !== 'anulado',
            $field, 'La contraparte debe pertenecer a otra jornada vigente');
        Access::check($other->moneda === $values['moneda'] && $other->tipo !== $values['tipo']
            && Decimal::compare($other->monto, $values['monto']) === 0, $field, 'La contraparte debe tener moneda e importe iguales y direccion opuesta');
        Access::check(! $other->$field || ($record && (int) $other->$field === $record->id), $field, 'La contraparte ya esta relacionada');
        if ($field === 'id_pase_relacionado') {
            Access::check($other->arqueo->id_subagencia === $day->id_subagencia,
                $field, 'Los pases relacionados requieren cajas de la misma agencia; use movimientos entre agencias');
            Access::check($other->arqueo->id_caja !== null && $day->id_caja !== null
                && $other->arqueo->id_caja !== $day->id_caja, $field, 'Los pases relacionados requieren cajas distintas');
            Access::check(empty($values['id_caja_contraparte']) || (int) $values['id_caja_contraparte'] === $other->arqueo->id_caja,
                'id_caja_contraparte', 'La caja contraparte no coincide con el pase relacionado');
            Access::check(! $other->id_caja_contraparte || $other->id_caja_contraparte === $day->id_caja,
                $field, 'La declaracion contraparte identifica otra caja');
        } else {
            Access::check($values['categoria'] === 'entre_agencias' && $other->categoria === 'entre_agencias'
                && $other->arqueo->id_subagencia !== $day->id_subagencia, $field, 'El enlace requiere movimientos entre agencias diferentes');
            Access::check(empty($values['id_subagencia_contraparte'])
                || (int) $values['id_subagencia_contraparte'] === $other->arqueo->id_subagencia,
                'id_subagencia_contraparte', 'La agencia contraparte no coincide');
            Access::check(! $other->id_subagencia_contraparte || $other->id_subagencia_contraparte === $day->id_subagencia,
                $field, 'La declaracion contraparte identifica otra agencia');
        }
    }

    public static function cheque(Request $request, int $id, Arqueo $day): Cheques
    {
        $query = Cheques::query();
        if (! Access::admin($request)) {
            $query->where(fn ($q) => $q->whereHas('arqueo', fn ($a) => $a->where('id_usuario', $day->id_usuario))
                ->orWhereHas('custodias.arqueo', fn ($a) => $a->where('id_usuario', $day->id_usuario)));
        }

        return $query->findOrFail($id);
    }
}
