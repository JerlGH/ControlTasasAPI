<?php

namespace App\Http\Controllers\Api;

use App\Models\Precio_Proveedor_Dolar;
use App\Models\Arqueo;
use App\Support\Caja\Access;
use App\Support\Caja\Decimal;
use App\Support\Caja\References;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrecioProveedorDolarController extends RegistroCajaController
{
    protected string $model = Precio_Proveedor_Dolar::class;
    protected string $label = 'Precio proveedor dólar';
    protected string $plural = 'Precios proveedor dólar';
    protected string $scope = 'quotes';
    protected bool $adminWrites = true;
    protected array $immutable = ['id_usuario'];

    protected function rules(?Model $record): array
    {
        $required = $record ? 'sometimes' : 'required';
        return ['proveedor_actual' => [$required, 'string', 'max:255'],
            'precio_dolar' => $this->amount($required, 6, false, true),
            'id_usuario' => ['sometimes', 'integer'], 'id_subagencia' => ['nullable', 'integer', 'exists:subagencias,id'],
            'vigente_desde' => ['nullable', 'date'], 'estado' => ['sometimes', 'in:activo,inactivo']];
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        if (! $record) {
            $values['vigente_desde'] ??= \Carbon\Carbon::now();
            Access::check(! isset($values['id_usuario']) || (int) $values['id_usuario'] === Access::user($request)->id,
                'id_usuario', 'El autor del precio debe ser el usuario autenticado');
            $values['id_usuario'] = Access::user($request)->id;
        } else {
            Access::check((int) $values['id_usuario'] === $record->id_usuario, 'id_usuario', 'El autor no puede cambiarse');
        }
        if (($values['estado'] ?? null) === 'anulado') {
            $values['estado'] = 'inactivo';
        }

        return $values;
    }

    public function ultimo(Request $request): JsonResponse
    {
        $query = $this->query($request)->where('estado', 'activo')
            ->where(fn ($q) => $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', \Carbon\Carbon::now()));
        if ($request->filled('id_subagencia')) {
            \Illuminate\Support\Facades\Validator::make($request->query(), ['id_subagencia' => 'integer|exists:subagencias,id'])->validate();
            $agency = (int) $request->query('id_subagencia');
            $query->where(fn ($q) => $q->where('id_subagencia', $agency)->orWhereNull('id_subagencia'));
        }

        return new JsonResponse([$this->label => $query->orderByRaw('COALESCE(vigente_desde, created_at) DESC')->orderByDesc('id')->firstOrFail(), 'Status' => 200]);
    }
}
