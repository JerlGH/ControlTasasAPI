<?php

namespace App\Http\Controllers\Api;

use App\Models\ServicioCaja;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServicioCajaController extends RegistroCajaController
{
    protected string $model = ServicioCaja::class;
    protected string $label = 'Servicio';
    protected string $plural = 'Servicios';
    protected string $scope = 'catalog';
    protected bool $adminWrites = true;

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return ['codigo' => [$required, 'string', 'max:50', Rule::unique('servicios_caja', 'codigo')->ignore($record?->id)],
            'nombre' => [$required, 'string', 'max:100'], 'categoria' => ['nullable', 'string', 'max:100'],
            'estado' => ['sometimes', 'in:activo,inactivo']];
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        if ($record && ($record->conteos()->exists() || $record->enrolamientos()->exists() || $record->papeleria()->exists())) {
            Access::check($values['codigo'] === $record->codigo, 'codigo', 'El codigo de un servicio utilizado debe conservarse');
        }
        if (($values['estado'] ?? null) === 'anulado') {
            $values['estado'] = 'inactivo';
        }

        return $values;
    }
}
