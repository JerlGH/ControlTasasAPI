<?php

namespace App\Http\Controllers\Api;

use App\Models\Entradas_Y_Salidas;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntradasYSalidasController extends RegistroCajaController
{
    protected string $model = Entradas_Y_Salidas::class;
    protected string $label = 'Entrada salida';
    protected string $plural = 'Entradas y salidas';

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), [
            'categoria' => [$required, 'in:bancario,entre_agencias,gasto,varios'], 'tipo' => [$required, 'in:Entrada,Salida'],
            'moneda' => [$required, 'in:NIO,USD'], 'monto' => $this->amount($required, 2, false, true),
            'medio' => ['nullable', 'in:efectivo,cheque,transferencia,mixto'],
            'monto_efectivo' => $this->amount('nullable'), 'monto_cheques' => $this->amount('nullable'),
            'detalle' => ['nullable', 'string', 'max:255'], 'banco' => ['nullable', 'string', 'max:100'],
            'numero_cuenta' => ['nullable', 'string', 'max:100'], 'referencia' => ['nullable', 'string', 'max:100'],
            'id_subagencia_contraparte' => ['nullable', 'integer', 'exists:subagencias,id'],
            'id_movimiento_relacionado' => ['nullable', 'integer', 'exists:entradas_y_salidas,id'],
            'fecha_operacion' => ['nullable', 'date']]);
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        if (($values['medio'] ?? null) === 'mixto') {
            Access::check(isset($values['monto_efectivo'], $values['monto_cheques']), 'medio', 'Indique ambos componentes del movimiento mixto');
            Access::check(Decimal::compare(Decimal::add($values['monto_efectivo'], $values['monto_cheques']), $values['monto']) === 0,
                'monto', 'Los componentes no suman el importe del movimiento');
        } else {
            Access::check(! isset($values['monto_efectivo']) && ! isset($values['monto_cheques']),
                'medio', 'Los componentes se utilizan solamente para movimientos mixtos');
        }
        if ($values['categoria'] === 'gasto') {
            Access::check(! empty($values['detalle']), 'detalle', 'Indique el concepto del gasto');
        }
        if (! empty($values['id_subagencia_contraparte'])) {
            Access::check($values['categoria'] === 'entre_agencias'
                && (int) $values['id_subagencia_contraparte'] !== $day->id_subagencia,
                'id_subagencia_contraparte', 'La contraparte debe ser otra agencia');
        }
        References::pair($request, $values, $record, $day, Entradas_Y_Salidas::class, 'id_movimiento_relacionado');
        if (($values['estado'] ?? null) === 'anulado') {
            Access::check(empty($values['id_movimiento_relacionado'])
                && (! $record || ! Entradas_Y_Salidas::where('id_movimiento_relacionado', $record->id)->exists()),
                'estado', 'Desvincule primero el movimiento relacionado');
        }

        return $values;
    }
}
