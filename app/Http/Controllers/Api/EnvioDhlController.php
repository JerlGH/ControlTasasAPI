<?php

namespace App\Http\Controllers\Api;

use App\Models\EnvioDhl;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnvioDhlController extends RegistroCajaController
{
    protected string $model = EnvioDhl::class;
    protected string $label = 'Envio';
    protected string $plural = 'Envios';
    protected bool $normalizeType = false;
    protected array $closedFollowup = ['estado_envio', 'confirmacion_original', 'fecha_entrega', 'fecha_entrega_original', 'observacion'];

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), [
            'guia' => [$required, 'string', 'max:100'], 'id_cliente' => ['nullable', 'integer', 'exists:clientes,id'],
            'fecha_envio' => ['nullable', 'date_format:Y-m-d'], 'fecha_original' => ['nullable', 'string', 'max:100'],
            'nombre_remitente' => ['nullable', 'string', 'max:255'], 'nombre_beneficiario' => ['nullable', 'string', 'max:255'],
            'nombre_contacto' => ['nullable', 'string', 'max:255'], 'telefono_contacto' => ['nullable', 'string', 'max:50'],
            'tipo' => ['nullable', 'string', 'max:30'], 'descripcion' => ['nullable', 'string', 'max:10000'],
            'destino' => ['nullable', 'string', 'max:255'], 'estado_envio' => ['nullable', 'string', 'max:100'],
            'confirmacion_original' => ['nullable', 'string', 'max:100'], 'fecha_entrega' => ['nullable', 'date'],
            'fecha_entrega_original' => ['nullable', 'string', 'max:150'], 'estado' => ['sometimes', 'in:borrador,registrado,anulado']]);
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        if (! $record && ! isset($values['estado'])) {
            $values['estado'] = empty($values['fecha_envio']) ? 'borrador' : 'registrado';
        }
        if (! empty($values['fecha_entrega']) && ! empty($values['fecha_envio'])) {
            Access::check(\Carbon\Carbon::parse($values['fecha_entrega'])->gte(\Carbon\Carbon::parse($values['fecha_envio'])),
                'fecha_entrega', 'La entrega no puede preceder al envio');
        }

        return $values;
    }
}
