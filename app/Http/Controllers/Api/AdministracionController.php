<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Administracion\AdministracionService;
use App\Services\Administracion\CapitalService;
use App\Support\Administracion\Finanzas as F;
use App\Support\Administracion\Recursos;
use App\Support\Caja\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdministracionController extends Controller
{
    public function index(Request $request, string $recurso): JsonResponse
    {
        Access::requireAdmin($request);
        $model = F::model($recurso);
        $query = $model::query();
        $rules = ['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100',
            'fecha_desde' => 'sometimes|date_format:Y-m-d', 'fecha_hasta' => 'sometimes|date_format:Y-m-d'];
        foreach ((new $model)->getFillable() as $field) {
            if (str_starts_with($field, 'id_')) {
                $rules[$field] = 'sometimes|integer|min:1';
            } elseif (in_array($field, ['moneda', 'tipo', 'estado', 'servicio', 'naturaleza', 'rubro_capital', 'gestion', 'entidad', 'referencia', 'codigo', 'banco_externo'], true)) {
                $rules[$field] = 'sometimes|string|max:255';
            }
        }
        $filters = Validator::make($request->query(), $rules)->validate();
        if (isset($filters['fecha_desde'], $filters['fecha_hasta'])) {
            F::check($filters['fecha_hasta'] >= $filters['fecha_desde'], 'fecha_hasta', 'El intervalo esta invertido');
        }
        foreach ($filters as $key => $value) {
            if (! in_array($key, ['page', 'per_page', 'fecha_desde', 'fecha_hasta'], true)) {
                $query->where($key, $value);
            }
        }
        $date = in_array('fecha', (new $model)->getFillable(), true) ? 'fecha' :
            (in_array('fecha_apertura', (new $model)->getFillable(), true) ? 'fecha_apertura' : 'created_at');
        foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $key => $operator) {
            if (isset($filters[$key])) {
                $query->whereDate($date, $operator, $filters[$key]);
            }
        }
        $page = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1);

        return new JsonResponse(['Registros' => $page->items(), 'Paginacion' => ['total' => $page->total(),
            'pagina' => $page->currentPage(), 'por_pagina' => $page->perPage()], 'Status' => 200]);
    }

    public function show(Request $request, int $id, string $recurso): JsonResponse
    {
        Access::requireAdmin($request);
        $model = F::model($recurso);

        return new JsonResponse(['Registro' => $model::findOrFail($id), 'Status' => 200]);
    }

    public function store(Request $request, string $recurso): JsonResponse
    {
        return F::transaction($request, function () use ($request, $recurso) {
            $data = $this->validateBody($request, $recurso);
            $record = (new AdministracionService)->save($request, $recurso, $data);

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 201], 201);
        });
    }

    public function update(Request $request, int $id, string $recurso): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id, $recurso) {
            $model = F::model($recurso);
            $record = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            $data = $this->validateBody($request, $recurso, true);
            $result = (new AdministracionService)->save($request, $recurso, $data, $record);

            return new JsonResponse(['Registro' => $result->fresh(), 'Status' => 200]);
        });
    }

    public function destroy(Request $request, int $id, string $recurso): JsonResponse
    {
        Access::requireAdmin($request);
        Validator::make($request->all(), ['motivo' => 'required|string|max:10000'])->validate();

        return F::transaction($request, function () use ($request, $id, $recurso) {
            $model = F::model($recurso);
            $record = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            (new AdministracionService)->annul($request, $recurso, $record);

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 200]);
        });
    }

    public function action(Request $request, int $id, string $recurso, string $accion): JsonResponse
    {
        // Las acciones de lectura tambien usan una transaccion para obtener una vista consistente.
        return F::transaction($request, function () use ($request, $id, $recurso, $accion) {
            $model = F::model($recurso);
            $record = $model::whereKey($id)->lockForUpdate()->firstOrFail();
            $result = (new AdministracionService)->action($request, $recurso, $accion, $record);

            return new JsonResponse(['Resultado' => $result, 'Status' => 200]);
        });
    }

    public function capital(Request $request): JsonResponse
    {
        return F::transaction($request, fn () => new JsonResponse([
            'Resultado' => (new CapitalService)->fromRequest($request), 'Status' => 200,
        ]));
    }

    private function validateBody(Request $request, string $recurso, bool $update = false): array
    {
        F::model($recurso);
        $rules = Recursos::DEFINICIONES[$recurso]['rules'];
        if ($update) {
            foreach ($rules as &$rule) {
                if ($rule[0] === 'required') {
                    $rule[0] = 'sometimes';
                }
            }
            unset($rule);
        }
        $rules['motivo'] = [$recurso === 'cierres' ? 'required' : 'nullable', 'string', 'max:10000'];
        if ($recurso === 'cierres') {
            $rules['confirmar_fuentes'] = ['required', 'accepted'];
        }
        F::unknown($request->all(), array_keys($rules));

        return Validator::make($request->all(), $rules)->validate();
    }
}
