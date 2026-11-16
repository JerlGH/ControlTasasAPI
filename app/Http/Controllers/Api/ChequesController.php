<?php

namespace App\Http\Controllers\Api;

use App\Models\Cheques;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChequesController extends RegistroCajaController
{
    protected string $model = Cheques::class;
    protected string $label = 'Cheque';
    protected string $plural = 'Cheques';
    protected string $scope = 'cheques';
    protected array $closedFollowup = ['estado', 'fecha_liquidacion', 'observacion'];

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), [
            'moneda' => [$required, 'in:NIO,USD'], 'moneda_ganancia' => ['nullable', 'in:NIO,USD'],
            'monto' => $this->amount('nullable', 2, false, true), 'ganancia' => $this->amount('sometimes'),
            'efectivo_entregado' => $this->amount('nullable'),
            'id_cliente' => ['nullable', 'integer', 'exists:clientes,id'],
            'numero_cheque' => ['nullable', 'string', 'max:100'], 'banco' => ['nullable', 'string', 'max:100'],
            'fecha_operacion' => ['nullable', 'date'], 'fecha_liquidacion' => ['nullable', 'date'],
            'estado' => ['sometimes', 'in:registrado,en_custodia,depositado,transferido,devuelto,anulado'],
        ]);
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        $values['moneda_ganancia'] ??= $values['moneda'];
        if ($record && $day->estado === 'cerrado') {
            Access::check(($values['estado'] === 'anulado') === ($record->estado === 'anulado'),
                'estado', 'El seguimiento no puede anular ni reactivar una operacion de un cierre');
        }
        if ($record && $record->custodias()->exists()) {
            foreach (['moneda', 'monto'] as $field) {
                Access::check((string) $values[$field] === (string) $record->getRawOriginal($field)
                    || ($field === 'monto' && Decimal::compare($values[$field], $record->$field) === 0),
                    $field, 'Retire primero los conteos abiertos antes de cambiar el principal del cheque');
            }
            Access::check(($values['estado'] ?? $record->estado) !== 'anulado', 'estado', 'No se puede anular un cheque incluido en custodia');
        }
        if (! empty($values['fecha_liquidacion']) && ! empty($values['fecha_operacion'])) {
            Access::check(\Carbon\Carbon::parse($values['fecha_liquidacion'])->gte(\Carbon\Carbon::parse($values['fecha_operacion'])),
                'fecha_liquidacion', 'La liquidacion no puede preceder a la operacion');
        }
        if (isset($values['monto'], $values['efectivo_entregado']) && $values['moneda_ganancia'] === $values['moneda']) {
            Access::check(Decimal::compare($values['efectivo_entregado'], $values['monto']) <= 0,
                'efectivo_entregado', 'El efectivo entregado no puede superar el principal');
        }

        return $values;
    }

    public function porArqueo(Request $request, int $id_arqueo): JsonResponse
    {
        Access::day($request, $id_arqueo);

        return new JsonResponse(['Cheques' => $this->query($request)->where('id_arqueo', $id_arqueo)->get(), 'Status' => 200]);
    }
}
