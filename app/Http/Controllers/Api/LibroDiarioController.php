<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Administracion\LibroDiario;
use App\Models\Administracion\ReporteRemesaCentral;
use App\Services\Administracion\LibroDiarioService;
use App\Services\Administracion\RegistroLibroDiarioService;
use App\Support\Administracion\Finanzas as F;
use App\Support\Administracion\RecursosLibroDiario as R;
use App\Support\Caja\Access;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LibroDiarioController extends Controller
{
    public function index(Request $request, string $recurso_diario): JsonResponse
    {
        Access::requireAdmin($request);
        $class = R::model($recurso_diario);
        $rules = ['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100',
            'fecha_desde' => 'sometimes|date_format:Y-m-d', 'fecha_hasta' => 'sometimes|date_format:Y-m-d|after_or_equal:fecha_desde'];
        foreach (['id_libro_diario', 'id_reporte', 'id_arqueo_externo', 'id_cuenta', 'id_operacion', 'id_entrada_salida', 'id_subagencia', 'estado', 'canal', 'banco', 'moneda', 'bloque'] as $key) {
            if (in_array($key, (new $class)->getFillable(), true)) {
                $rules[$key] = str_starts_with($key, 'id_') ? 'sometimes|integer|min:1' : 'sometimes|string|max:100';
            }
        }
        $filters = Validator::make($request->query(), $rules)->validate();
        $query = $class::query();
        foreach ($filters as $key => $value) {
            if (in_array($key, ['page', 'per_page'], true)) {
                continue;
            }
            if (in_array($key, ['fecha_desde', 'fecha_hasta'], true)) {
                $query->whereDate(in_array('fecha', (new $class)->getFillable(), true) ? 'fecha' : 'created_at', $key === 'fecha_desde' ? '>=' : '<=', $value);
            } else {
                $query->where($key, $value);
            }
        }
        $page = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1);

        return new JsonResponse(['Registros' => $page->items(), 'Paginacion' => ['total' => $page->total(), 'pagina' => $page->currentPage(), 'por_pagina' => $page->perPage()], 'Status' => 200]);
    }

    public function show(Request $request, int $id, string $recurso_diario): JsonResponse
    {
        Access::requireAdmin($request);

        return new JsonResponse(['Registro' => (R::model($recurso_diario))::findOrFail($id), 'Status' => 200]);
    }

    public function store(Request $request, string $recurso_diario): JsonResponse
    {
        return F::transaction($request, function () use ($request, $recurso_diario) {
            $data = $this->body($request, $recurso_diario);
            $record = (new RegistroLibroDiarioService)->save($request, $recurso_diario, $data);

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 201], 201);
        });
    }

    public function update(Request $request, int $id, string $recurso_diario): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id, $recurso_diario) {
            $record = (R::model($recurso_diario))::whereKey($id)->lockForUpdate()->firstOrFail();
            $data = $this->body($request, $recurso_diario, true);
            $record = (new RegistroLibroDiarioService)->save($request, $recurso_diario, $data, $record);

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 200]);
        });
    }

    public function destroy(Request $request, int $id, string $recurso_diario): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id, $recurso_diario) {
            F::unknown($request->all(), ['motivo']);
            Validator::make($request->all(), ['motivo' => 'required|string|max:10000'])->validate();
            $record = (R::model($recurso_diario))::whereKey($id)->lockForUpdate()->firstOrFail();
            (new RegistroLibroDiarioService)->annul($request, $record);

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 200]);
        });
    }

    public function summary(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, fn () => new JsonResponse(['Resultado' => (new LibroDiarioService)->calculate(LibroDiario::whereKey($id)->lockForUpdate()->firstOrFail()), 'Status' => 200]));
    }

    public function recalculate(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id) {
            F::unknown($request->all(), []);
            $root = LibroDiario::whereKey($id)->lockForUpdate()->firstOrFail();
            $result = (new LibroDiarioService)->persist($request, $root);

            return new JsonResponse(['Resultado' => $result, 'Status' => 200]);
        });
    }

    public function close(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id) {
            F::unknown($request->all(), ['confirmar_fuentes']);
            Validator::make($request->all(), ['confirmar_fuentes' => 'required|accepted'])->validate();
            $root = LibroDiario::whereKey($id)->lockForUpdate()->firstOrFail();
            $result = (new LibroDiarioService)->persist($request, $root, true);

            return new JsonResponse(['Resultado' => $result, 'Status' => 200]);
        });
    }

    public function confirmReport(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id) {
            F::unknown($request->all(), []);
            $report = ReporteRemesaCentral::whereKey($id)->lockForUpdate()->firstOrFail();
            (new RegistroLibroDiarioService)->confirmReport($request, $report);

            return new JsonResponse(['Registro' => $report->fresh(), 'Status' => 200]);
        });
    }

    private function body(Request $request, string $resource, bool $update = false): array
    {
        R::model($resource);
        $rules = R::DEFINICIONES[$resource]['rules'];
        if ($update) {
            foreach ($rules as $key => &$rule) {
                if (! str_contains($key, '.')) {
                    $rule = is_array($rule) ? array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $rule) : preg_replace('/^required\|/', 'sometimes|', $rule);
                }
            }
            unset($rule);
        }
        $rules['motivo'] = 'nullable|string|max:10000';
        F::unknown($request->all(), array_filter(array_keys($rules), fn ($key) => ! str_contains($key, '.')));

        return Validator::make($request->all(), $rules)->validate();
    }
}
