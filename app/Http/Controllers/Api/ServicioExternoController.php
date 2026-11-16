<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Administracion\ArqueoServicioExterno;
use App\Services\Administracion\ServicioExternoService;
use App\Support\Administracion\Finanzas as F;
use App\Support\Administracion\RecursosServiciosExternos as R;
use App\Support\Caja\Access;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServicioExternoController extends Controller
{
    public function catalog(Request $request): JsonResponse
    {
        Access::requireAdmin($request);

        return new JsonResponse(['Resultado' => ServicioExternoService::catalog(), 'Status' => 200]);
    }

    public function index(Request $request, string $recurso_externo): JsonResponse
    {
        Access::requireAdmin($request);
        $class = R::model($recurso_externo);
        $rootResource = $recurso_externo === 'arqueosserviciosexternos';
        $rules = ['page' => 'sometimes|integer|min:1', 'per_page' => 'sometimes|integer|between:1,100',
            'fecha_desde' => 'sometimes|date_format:Y-m-d', 'fecha_hasta' => 'sometimes|date_format:Y-m-d'];
        $fields = $rootResource ? ['estado', 'id_responsable']
            : ['id_arqueo_externo', 'id_subagencia', 'id_cajero', 'banco', 'servicio', 'tipo', 'moneda', 'estado', 'numero_referencia', 'clave'];
        foreach ($fields as $field) {
            $rules[$field] = str_starts_with($field, 'id_') ? 'sometimes|integer|min:1' : 'sometimes|string|max:100';
        }
        F::unknown($request->query(), array_keys($rules));
        $filters = Validator::make($request->query(), $rules)->validate();
        F::check(! isset($filters['fecha_desde'], $filters['fecha_hasta']) || $filters['fecha_desde'] <= $filters['fecha_hasta'], 'fecha_hasta', 'El intervalo esta invertido');
        $query = $class::query();
        foreach ($filters as $key => $value) {
            if (in_array($key, ['page', 'per_page', 'fecha_desde', 'fecha_hasta'], true)) {
                continue;
            }
            $query->where($key, $value);
        }
        $dates = function ($q) use ($filters) {
            foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $field => $operator) {
                if (isset($filters[$field])) {
                    $q->whereDate('fecha', $operator, $filters[$field]);
                }
            }
        };
        if ($rootResource) {
            $dates($query);
        } else {
            $query->whereHas('arqueo_externo', $dates);
        }
        $page = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1);

        return new JsonResponse(['Registros' => $page->items(), 'Paginacion' => ['total' => $page->total(),
            'pagina' => $page->currentPage(), 'por_pagina' => $page->perPage()], 'Status' => 200]);
    }

    public function show(Request $request, int $id, string $recurso_externo): JsonResponse
    {
        Access::requireAdmin($request);

        return new JsonResponse(['Registro' => (R::model($recurso_externo))::findOrFail($id), 'Status' => 200]);
    }

    public function store(Request $request, string $recurso_externo): JsonResponse
    {
        return F::transaction($request, function () use ($request, $recurso_externo) {
            $record = (new ServicioExternoService)->save($request, $recurso_externo, $this->body($request, $recurso_externo));

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 201], 201);
        });
    }

    public function update(Request $request, int $id, string $recurso_externo): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id, $recurso_externo) {
            $record = (R::model($recurso_externo))::whereKey($id)->lockForUpdate()->firstOrFail();
            $record = (new ServicioExternoService)->save($request, $recurso_externo, $this->body($request, $recurso_externo, true), $record);

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 200]);
        });
    }

    public function destroy(Request $request, int $id, string $recurso_externo): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id, $recurso_externo) {
            F::unknown($request->all(), ['motivo']);
            Validator::make($request->all(), ['motivo' => 'required|string|max:10000'])->validate();
            $record = (R::model($recurso_externo))::whereKey($id)->lockForUpdate()->firstOrFail();
            (new ServicioExternoService)->annul($request, $record);

            return new JsonResponse(['Registro' => $record->fresh(), 'Status' => 200]);
        });
    }

    public function summary(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id) {
            F::unknown($request->query(), []);
            $root = ArqueoServicioExterno::whereKey($id)->lockForUpdate()->firstOrFail();

            return new JsonResponse(['Resultado' => (new ServicioExternoService)->summary($root), 'Status' => 200]);
        });
    }

    public function close(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id) {
            F::unknown($request->all(), ['confirmar_registros', 'huella', 'motivo']);
            $data = Validator::make($request->all(), ['confirmar_registros' => 'required|accepted',
                'huella' => 'required|string|regex:/^[a-f0-9]{64}$/D', 'motivo' => 'nullable|string|max:10000'])->validate();
            $root = ArqueoServicioExterno::whereKey($id)->lockForUpdate()->firstOrFail();
            $summary = (new ServicioExternoService)->close($request, $root, $data['huella']);

            return new JsonResponse(['Resultado' => $summary, 'Status' => 200]);
        });
    }

    public function generateReport(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id) {
            F::unknown($request->all(), ['corte_at']);
            $data = Validator::make($request->all(), ['corte_at' => 'required|date_format:Y-m-d H:i:s'])->validate();
            $root = ArqueoServicioExterno::whereKey($id)->lockForUpdate()->firstOrFail();
            $report = (new ServicioExternoService)->generateReport($request, $root, $data['corte_at']);

            return new JsonResponse(['Registro' => $report, 'Status' => 200]);
        });
    }

    public function commissions(Request $request, int $id): JsonResponse
    {
        return F::transaction($request, function () use ($request, $id) {
            F::unknown($request->all(), ['fecha', 'cuentas']);
            $data = Validator::make($request->all(), ['fecha' => 'required|date_format:Y-m-d', 'cuentas' => 'required|array|min:1|max:2',
                'cuentas.*' => 'required|array:banco,id_cuenta', 'cuentas.*.banco' => 'required|in:lafise,banpro|distinct',
                'cuentas.*.id_cuenta' => 'required|integer|min:1|distinct|exists:cuentas_financieras,id'])->validate();
            $root = ArqueoServicioExterno::whereKey($id)->lockForUpdate()->firstOrFail();
            $commissions = (new ServicioExternoService)->commissions($request, $root, $data);

            return new JsonResponse(['Resultado' => ['comisiones' => $commissions], 'Status' => 200]);
        });
    }

    public function period(Request $request): JsonResponse
    {
        return F::transaction($request, function () use ($request) {
            F::unknown($request->query(), ['fecha_desde', 'fecha_hasta']);
            $data = Validator::make($request->query(), ['fecha_desde' => 'required|date_format:Y-m-d',
                'fecha_hasta' => 'required|date_format:Y-m-d|after_or_equal:fecha_desde'])->validate();
            F::check(Carbon::parse($data['fecha_desde'])->diffInDays(Carbon::parse($data['fecha_hasta'])) <= 365,
                'fecha_hasta', 'Consulte hasta 366 dias por solicitud');
            $result = ['fecha_desde' => $data['fecha_desde'], 'fecha_hasta' => $data['fecha_hasta'],
                'moneda_ganancia' => 'NIO', 'dias' => [], 'borradores' => [], 'fechas_sin_registro' => [], 'cantidad' => 0,
                'cantidad_con_comision' => 0, 'cantidad_sin_comision' => 0, 'ganancia_nio' => F::amount(),
                'por_banco' => ['lafise' => ['cantidad' => 0, 'cantidad_con_comision' => 0, 'cantidad_sin_comision' => 0, 'ganancia_nio' => F::amount()],
                    'banpro' => ['cantidad' => 0, 'cantidad_con_comision' => 0, 'cantidad_sin_comision' => 0, 'ganancia_nio' => F::amount()]]];
            $roots = ArqueoServicioExterno::whereBetween('fecha', [$data['fecha_desde'], $data['fecha_hasta']])
                ->where('estado', '!=', 'anulado')->orderBy('fecha')->get();
            $recorded = [];
            foreach ($roots as $root) {
                $recorded[F::date($root->fecha)] = true;
                if ($root->estado !== 'cerrado') {
                    $result['borradores'][] = ['id' => $root->id, 'fecha' => F::date($root->fecha)];

                    continue;
                }
                $summary = $root->resumen;
                $result['dias'][] = ['id' => $root->id, 'fecha' => F::date($root->fecha), 'totales' => $summary['totales'],
                    'por_banco' => $summary['por_banco']];
                $result['cantidad'] += $summary['totales']['cantidad'];
                $result['cantidad_con_comision'] += $summary['totales']['cantidad_con_comision'] ?? $summary['totales']['cantidad'];
                $result['cantidad_sin_comision'] += $summary['totales']['cantidad_sin_comision'] ?? 0;
                $result['ganancia_nio'] = F::add($result['ganancia_nio'], $summary['totales']['ganancia_nio']);
                foreach (['lafise', 'banpro'] as $bank) {
                    $result['por_banco'][$bank]['cantidad'] += $summary['por_banco'][$bank]['cantidad'];
                    $result['por_banco'][$bank]['cantidad_con_comision'] += $summary['por_banco'][$bank]['cantidad_con_comision'] ?? $summary['por_banco'][$bank]['cantidad'];
                    $result['por_banco'][$bank]['cantidad_sin_comision'] += $summary['por_banco'][$bank]['cantidad_sin_comision'] ?? 0;
                    $result['por_banco'][$bank]['ganancia_nio'] = F::add($result['por_banco'][$bank]['ganancia_nio'], $summary['por_banco'][$bank]['ganancia_nio']);
                }
            }
            for ($day = Carbon::parse($data['fecha_desde']); $day->toDateString() <= $data['fecha_hasta']; $day->addDay()) {
                if (! isset($recorded[$day->toDateString()])) {
                    $result['fechas_sin_registro'][] = $day->toDateString();
                }
            }

            return new JsonResponse(['Resultado' => $result, 'Status' => 200]);
        });
    }

    private function body(Request $request, string $resource, bool $update = false): array
    {
        $rules = R::DEFINICIONES[$resource]['rules'];
        if ($update) {
            foreach ($rules as $field => &$rule) {
                if (! str_contains($field, '.')) {
                    $rule = is_array($rule) ? array_map(fn ($r) => $r === 'required' ? 'sometimes' : $r, $rule)
                        : preg_replace('/^required\|/', 'sometimes|', $rule);
                }
            }
            unset($rule);
        }
        $rules['motivo'] = 'nullable|string|max:10000';
        F::unknown($request->all(), array_filter(array_keys($rules), fn ($field) => ! str_contains($field, '.')));

        return Validator::make($request->all(), $rules)->validate();
    }
}
