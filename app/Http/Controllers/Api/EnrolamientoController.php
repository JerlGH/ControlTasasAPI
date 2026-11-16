<?php

namespace App\Http\Controllers\Api;

use App\Models\Enrolamiento;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EnrolamientoController extends RegistroCajaController
{
    protected string $model = Enrolamiento::class;
    protected string $label = 'Enrolamiento';
    protected string $plural = 'Enrolamientos';

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), [
            'id_cliente' => ['nullable', 'integer', 'exists:clientes,id'], 'id_servicio' => ['nullable', 'integer', 'exists:servicios_caja,id'],
            'fecha_enrolamiento' => ['nullable', 'date_format:Y-m-d'], 'fecha_original' => ['nullable', 'string', 'max:100'],
            'nombre_completo' => [$required, 'string', 'max:255'], 'tipo_documento' => ['nullable', 'string', 'max:50'],
            'numero_documento' => [$required, 'string', 'max:100'], 'usuario_servicio' => ['nullable', 'string', 'max:100'],
            'estado' => ['sometimes', 'in:borrador,registrado,anulado']]);
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        if (($values['estado'] ?? 'borrador') === 'registrado') {
            Access::check(! empty($values['fecha_enrolamiento']), 'fecha_enrolamiento', 'La fecha es necesaria para confirmar el enrolamiento');
        }
        if (! empty($values['id_cliente'])) {
            $client = \App\Models\Cliente::findOrFail($values['id_cliente']);
            $normalize = fn ($v) => mb_strtoupper(preg_replace('/[\s-]+/u', '', $v));
            Access::check($normalize($client->cedula) === $normalize($values['numero_documento']),
                'id_cliente', 'El documento no corresponde al cliente existente');
        }

        return $values;
    }
}
