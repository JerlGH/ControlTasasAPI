<?php

namespace App\Http\Controllers\Api;

use App\Models\ConteoServicioDiario;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConteoServicioDiarioController extends RegistroCajaController
{
    protected string $model = ConteoServicioDiario::class;
    protected string $label = 'ConteoServicio';
    protected string $plural = 'ConteosServicios';

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return array_replace($this->common($record), ['id_servicio' => [$required, 'integer', 'exists:servicios_caja,id'],
            'cantidad' => [$required, 'integer', 'between:0,4294967295']]);
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        $duplicate = ConteoServicioDiario::where('id_arqueo', $day->id)->where('id_servicio', $values['id_servicio']);
        if ($record) {
            $duplicate->whereKeyNot($record->id);
        }
        Access::check(! $duplicate->exists(), 'id_servicio', 'Ya existe ese conteo de servicio para la jornada');

        return $values;
    }
}
