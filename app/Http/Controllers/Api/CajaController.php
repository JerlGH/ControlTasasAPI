<?php

namespace App\Http\Controllers\Api;

use App\Models\Caja;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CajaController extends RegistroCajaController
{
    protected string $model = Caja::class;
    protected string $label = 'Caja';
    protected string $plural = 'Cajas';
    protected string $scope = 'agency';
    protected bool $adminWrites = true;

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return [
            'id_subagencia' => [$required, 'integer', 'exists:subagencias,id'],
            'codigo' => [$required, 'string', 'max:30'], 'nombre' => [$required, 'string', 'max:100'],
            'estado' => ['sometimes', 'in:activo,inactivo'],
        ];
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        $values['estado'] = ($values['estado'] ?? 'activo') === 'anulado' ? 'inactivo' : ($values['estado'] ?? 'activo');
        $duplicate = Caja::where('id_subagencia', $values['id_subagencia'])->where('codigo', $values['codigo']);
        if ($record) {
            $duplicate->whereKeyNot($record->id);
        }
        Access::check(! $duplicate->exists(), 'codigo', 'El codigo ya existe en esa agencia');

        return $values;
    }
}
