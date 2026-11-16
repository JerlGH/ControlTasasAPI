<?php

namespace App\Http\Controllers\Api;

use App\Models\OperacionCambioCaja;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperacionCambioCajaController extends RegistroCajaController
{
    protected string $model = OperacionCambioCaja::class;
    protected string $label = 'Operacion';
    protected string $plural = 'Operaciones';

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), ['tipo' => [$required, 'in:Compra,Venta'],
            'monto_dolares' => $this->amount($required, 2, false, true), 'tasa' => $this->amount($required, 6, false, true),
            'monto_cordobas' => $this->amount('sometimes', 8, false, true),
            'id_tasa_arqueo' => ['nullable', 'integer', 'exists:tasas_cambio_arqueo,id'],
            'id_mesa_cambio' => ['nullable', 'integer', Rule::unique('operaciones_cambio_caja', 'id_mesa_cambio')->ignore($record?->id), 'exists:mesas_cambio,id'],
            'id_cliente' => ['nullable', 'integer', 'exists:clientes,id'],
            'medio_entrada' => ['nullable', 'in:efectivo,cheque,transferencia,mixto'],
            'medio_salida' => ['nullable', 'in:efectivo,cheque,transferencia,mixto'],
            'fecha_operacion' => ['nullable', 'date']]);
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        if (! empty($values['id_tasa_arqueo'])) {
            $rate = \App\Models\TasaCambioArqueo::findOrFail($values['id_tasa_arqueo']);
            Access::check($rate->id_arqueo === $day->id && $rate->tipo === $values['tipo'], 'id_tasa_arqueo', 'La tasa no pertenece a esta jornada y tipo');
            if (! $record || $request->exists('id_tasa_arqueo') || $request->exists('tasa')) {
                Access::check(Decimal::compare($rate->tasa, $values['tasa']) === 0, 'tasa', 'La tasa aplicada no coincide con el precio seleccionado');
            }
        }
        if (! $record || (! $request->exists('monto_cordobas') && ($request->exists('tasa') || $request->exists('monto_dolares')))) {
            $values['monto_cordobas'] = $request->input('monto_cordobas') ?? Decimal::multiply($values['monto_dolares'], $values['tasa']);
        }
        if (! empty($values['id_mesa_cambio'])) {
            $mesa = \App\Models\Mesa_De_Cambio::findOrFail($values['id_mesa_cambio']);
            Access::check($mesa->id_usuario == $day->id_usuario && \Carbon\Carbon::parse($mesa->fecha)->toDateString() === $day->fecha->toDateString(),
                'id_mesa_cambio', 'La mesa debe pertenecer al mismo cajero y dia');
            $kind = mb_strtolower(trim($mesa->tipo_transaccion));
            Access::check(str_starts_with($kind, mb_strtolower($values['tipo']))
                && mb_strtolower(trim($mesa->estado_mesa)) !== 'anulado', 'id_mesa_cambio', 'El tipo o estado de la mesa no coincide');
            $usd = $values['tipo'] === 'Compra' ? $mesa->monto_entrada : $mesa->monto_salida;
            $nio = $values['tipo'] === 'Compra' ? $mesa->monto_salida : $mesa->monto_entrada;
            Access::check(Decimal::compare($usd, $values['monto_dolares']) === 0
                && Decimal::compare($nio, $values['monto_cordobas']) === 0 && Decimal::compare($mesa->tasa, $values['tasa']) === 0,
                'id_mesa_cambio', 'Los importes y la tasa deben corresponder a la mesa existente');
            Access::check(empty($values['id_cliente']) || (int) $values['id_cliente'] === (int) $mesa->id_cliente,
                'id_cliente', 'El cliente debe corresponder a la mesa');
            $values['id_cliente'] = $mesa->id_cliente;
        }

        return $values;
    }
}
