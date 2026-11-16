<?php

namespace App\Http\Controllers\Api;

use App\Models\Pases;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PasesController extends RegistroCajaController
{
    protected string $model = Pases::class;
    protected string $label = 'Pase';
    protected string $plural = 'Pases';

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), ['tipo' => [$required, 'in:Entrada,Salida'],
            'moneda' => [$required, 'in:NIO,USD'], 'monto' => $this->amount($required, 2, false, true),
            'id_usuario' => ['sometimes', 'integer'], 'id_caja_contraparte' => ['nullable', 'integer', 'exists:cajas,id'],
            'id_pase_relacionado' => ['nullable', 'integer', 'exists:pases,id'], 'detalle' => ['nullable', 'string', 'max:255'],
            'medio' => ['nullable', 'in:efectivo,cheque,transferencia,mixto'],
            'referencia' => ['nullable', 'string', 'max:100'], 'fecha_operacion' => ['nullable', 'date']]);
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        if (! empty($values['id_caja_contraparte'])) {
            $box = \App\Models\Caja::findOrFail($values['id_caja_contraparte']);
            Access::check($box->id_subagencia === $day->id_subagencia && $box->id !== $day->id_caja,
                'id_caja_contraparte', 'El pase requiere otra caja de la misma agencia');
        }
        References::pair($request, $values, $record, $day, Pases::class, 'id_pase_relacionado');
        if (($values['estado'] ?? null) === 'anulado') {
            Access::check(empty($values['id_pase_relacionado'])
                && (! $record || ! Pases::where('id_pase_relacionado', $record->id)->exists()),
                'estado', 'Desvincule primero la contraparte con supervision');
        }

        return $values;
    }

    public function ultimosPorUsuario(Request $request, int $id_usuario): JsonResponse
    {
        if (! Access::admin($request) && $id_usuario !== Access::user($request)->id) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(403, 'Usuario fuera del alcance permitido');
        }
        $request->query->set('id_usuario', (string) $id_usuario);

        return $this->index($request);
    }
}
