<?php

namespace App\Http\Controllers\Api;

use App\Models\Detalle_De_Arqueo;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use App\Support\Caja\ConteoEfectivo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DetalleArqueoController extends RegistroCajaController
{
    protected string $model = Detalle_De_Arqueo::class;
    protected string $label = 'DetalleArqueo';
    protected string $plural = 'DetallesArqueo';
    protected bool $annul = false;
    protected array $immutable = ['id_arqueo', 'moneda'];


    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        $rules = ['id_arqueo' => [$required, 'integer', 'exists:arqueo,id'], 'moneda' => [$required, 'in:NIO,USD'],
            'total' => ['prohibited'], 'contado_at' => ['prohibited'], 'estado' => ['sometimes', 'in:borrador,contado'],
            'observacion' => ['nullable', 'string', 'max:10000']];
        foreach (ConteoEfectivo::DENOMINACIONES as $field => $value) {
            $rules[$field] = ['sometimes', 'integer', 'between:0,4294967295'];
        }

        return $rules;
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        $total = ConteoEfectivo::total($values['moneda'], $values);
        foreach (ConteoEfectivo::DENOMINACIONES as $field => $denomination) {
            $values[$field] = (int) ($values[$field] ?? 0);
        }
        $duplicate = Detalle_De_Arqueo::where('id_arqueo', $day->id)->where('moneda', $values['moneda']);
        if ($record) {
            $duplicate->whereKeyNot($record->id);
        }
        Access::check(! $duplicate->exists(), 'moneda', 'Ya existe un conteo para esa moneda');
        $counted = ($values['estado'] ?? 'borrador') === 'contado';
        $values['total'] = $counted ? Decimal::value($total, 2) : null;
        $values['contado_at'] = $counted ? \Carbon\Carbon::now() : null;

        return $values;
    }
}
