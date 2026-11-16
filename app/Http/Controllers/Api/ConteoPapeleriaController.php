<?php

namespace App\Http\Controllers\Api;

use App\Models\ConteoPapeleria;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConteoPapeleriaController extends RegistroCajaController
{
    protected string $model = ConteoPapeleria::class;
    protected string $label = 'ConteoPapeleria';
    protected string $plural = 'ConteosPapeleria';
    protected string $scope = 'owned';
    protected bool $annul = false;
    protected array $immutable = ['id_usuario', 'id_arqueo'];

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return ['id_usuario' => ['sometimes', 'integer', 'exists:users,id'],
            'id_subagencia' => ['nullable', 'integer', 'exists:subagencias,id'], 'id_arqueo' => ['nullable', 'integer', 'exists:arqueo,id'],
            'id_servicio' => ['nullable', 'integer', 'exists:servicios_caja,id'], 'fecha' => ['nullable', 'date_format:Y-m-d'],
            'concepto' => [$required, 'string', 'max:100'], 'cantidad' => [$required, 'integer', 'between:0,4294967295'],
            'observacion' => ['nullable', 'string', 'max:10000']];
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        $values['id_usuario'] ??= Access::user($request)->id;
        Access::check(Access::admin($request) || (int) $values['id_usuario'] === Access::user($request)->id, 'id_usuario', 'El trabajador esta fuera del alcance permitido');
        if ($day) {
            Access::check(empty($values['id_subagencia']) || (int) $values['id_subagencia'] === $day->id_subagencia,
                'id_subagencia', 'La agencia debe corresponder a la jornada');
            Access::check(empty($values['fecha']) || \Carbon\Carbon::parse($values['fecha'])->toDateString() === $day->fecha->toDateString(),
                'fecha', 'La fecha debe corresponder a la jornada');
            $values['id_subagencia'] = $day->id_subagencia;
            $values['fecha'] = $day->fecha->toDateString();
        } elseif (! empty($values['id_subagencia']) && ! Access::admin($request)) {
            Access::check((int) $values['id_subagencia'] === Access::agency(Access::user($request)), 'id_subagencia', 'Agencia fuera del alcance permitido');
        }

        return $values;
    }
}
