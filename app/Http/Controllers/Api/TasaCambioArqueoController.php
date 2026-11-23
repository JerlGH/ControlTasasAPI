<?php

namespace App\Http\Controllers\Api;

use App\Models\TasaCambioArqueo;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TasaCambioArqueoController extends RegistroCajaController
{
    protected string $model = TasaCambioArqueo::class;
    protected string $label = 'Tasa';
    protected string $plural = 'Tasas';
    protected bool $annul = false;
    protected bool $adminWrites = true;
    protected array $immutable = ['id_arqueo', 'tipo', 'numero_precio'];

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return ['id_arqueo' => [$required, 'integer', 'exists:arqueo,id'], 'tipo' => [$required, 'in:Compra,Venta'],
            'numero_precio' => [$required, 'integer', 'between:1,4'], 'tasa' => $this->amount($required, 6, false, true)];
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        $duplicate = TasaCambioArqueo::where('id_arqueo', $day->id)->where('tipo', $values['tipo'])->where('numero_precio', $values['numero_precio']);
        if ($record) {
            $duplicate->whereKeyNot($record->id);
        }
        Access::check(! $duplicate->exists(), 'numero_precio', 'Ya existe ese precio para el tipo de operacion');

        return $values;
    }
}
