<?php

namespace App\Http\Controllers\Api;

use App\Models\Arqueo;
use App\Services\ArqueoService;
use App\Support\Caja\Access;
use App\Support\Caja\Audit;
use App\Support\Caja\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArqueoController extends RegistroCajaController
{
    protected string $model = Arqueo::class;
    protected string $label = 'Arqueo';
    protected string $plural = 'Arqueos';

    protected function query(Request $request): Builder
    {
        return Access::days($request);
    }

    protected function rules(?Model $record): array
    {
        $rules = ['observacion' => ['nullable', 'string', 'max:10000'], 'motivo' => ['nullable', 'string', 'max:10000'],
            'tasa_referencia' => $this->amount('nullable', 6, false, true),
            'motivo_apertura_manual' => ['nullable', 'string', 'max:10000']];
        foreach (['caja_inicial_cordobas', 'caja_inicial_dolares', 'cheques_inicial_cordobas', 'cheques_inicial_dolares'] as $field) {
            $rules[$field] = $this->amount('nullable');
        }
        foreach (ArqueoService::TOTALES as $field) {
            $rules[$field] = $this->amount('nullable', 2, str_starts_with($field, 'biller_'));
        }
        foreach (['id', 'estado', 'abierto_at', 'cerrado_at', 'cheques_confirmados_at', 'id_usuario_cierre',
            'caja_final_cordobas', 'caja_final_dolares', 'saldo_esperado_cordobas', 'saldo_esperado_dolares',
            'diferencia_cordobas', 'diferencia_dolares', 'cantidad_cheques', 'monto_total_cheques',
            'monto_total_cheques_dolares', 'ganancia_total_cheques', 'ganancia_total_cheques_dolares',
            'created_at', 'updated_at'] as $field) {
            $rules[$field] = ['prohibited'];
        }
        if ($record) {
            foreach (['id_usuario', 'id_subagencia', 'id_caja', 'fecha', 'id_arqueo_anterior', 'apertura_manual'] as $field) {
                $rules[$field] = ['prohibited'];
            }
        } else {
            $rules += ['id_usuario' => ['sometimes', 'integer', 'exists:users,id'],
                'id_subagencia' => ['sometimes', 'integer', 'exists:subagencias,id'],
                'id_caja' => ['nullable', 'integer', 'exists:cajas,id'], 'fecha' => ['required', 'date_format:Y-m-d'],
                'id_arqueo_anterior' => ['nullable', 'integer', 'exists:arqueo,id'], 'apertura_manual' => ['sometimes', 'boolean']];
        }

        return $rules;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'id_usuario' => 'sometimes|integer', 'id_subagencia' => 'sometimes|integer', 'id_caja' => 'sometimes|integer',
            'fecha' => 'sometimes|date_format:Y-m-d', 'fecha_desde' => 'sometimes|date_format:Y-m-d',
            'fecha_hasta' => $request->filled('fecha_desde') ? 'sometimes|date_format:Y-m-d|after_or_equal:fecha_desde' : 'sometimes|date_format:Y-m-d',
            'estado' => 'sometimes|in:abierto,cerrado,anulado', 'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|between:1,100',
        ])->validate();
        $query = $this->query($request);
        foreach (['id_usuario', 'id_subagencia', 'id_caja', 'fecha', 'estado'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filters['fecha_desde']);
        }
        if (isset($filters['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filters['fecha_hasta']);
        }
        $page = $query->orderByDesc('fecha')->orderByDesc('id')->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1);

        return new JsonResponse(['Arqueos' => $page->items(), 'Paginacion' => [
            'total' => $page->total(), 'pagina' => $page->currentPage(), 'por_pagina' => $page->perPage(),
        ], 'Status' => 200]);
    }

    public function store(Request $request): JsonResponse
    {
        Access::user($request);
        $data = Validator::make($request->all(), $this->rules(null))->validate();

        return $this->transaction(fn () => new JsonResponse([
            'Arqueo' => (new ArqueoService)->open($request, $data)->fresh(), 'Status' => 201,
        ], 201));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->transaction(function () use ($request, $id) {
            $day = Access::day($request, $id, true);
            Access::check($day->estado === 'abierto', 'estado', 'La jornada ya no admite modificaciones');
            $data = Validator::make($request->all(), $this->rules($day))->validate();
            if ($request->exists('tasa_referencia') && (string) ($data['tasa_referencia'] ?? '') !== (string) $day->tasa_referencia
                && (($data['tasa_referencia'] ?? null) === null || $day->tasa_referencia === null
                    || Decimal::compare($data['tasa_referencia'], $day->tasa_referencia) !== 0)) {
                Access::requireAdmin($request);
            }
            $before = $day->getAttributes();
            foreach (['caja_inicial_cordobas', 'caja_inicial_dolares', 'cheques_inicial_cordobas', 'cheques_inicial_dolares'] as $field) {
                if ($request->exists($field)) {
                    Access::check(! $day->id_arqueo_anterior, $field, 'La apertura trasladada no puede modificarse');
                    Access::check($data[$field] !== null || str_starts_with($field, 'cheques_'), $field, 'El saldo total de apertura es obligatorio');
                    Access::check(! empty($data['motivo_apertura_manual']), 'motivo_apertura_manual', 'Indique el motivo de la correccion de apertura');
                }
            }
            $day->fill($data);
            foreach (['cordobas', 'dolares'] as $suffix) {
                Access::check($day->{'cheques_inicial_'.$suffix} === null
                    || Decimal::compare($day->{'cheques_inicial_'.$suffix}, $day->{'caja_inicial_'.$suffix}) <= 0,
                    'cheques_inicial_'.$suffix, 'Los cheques no pueden superar el saldo total');
            }
            $day->save();
            Audit::record($request, $day, 'actualizar', $before, $day->id);

            return new JsonResponse(['Arqueo' => $day->fresh(), 'Status' => 200]);
        });
    }

    public function ultimoPorUsuario(Request $request, int $id_usuario): JsonResponse
    {
        return new JsonResponse(['Arqueo' => $this->query($request)->where('id_usuario', $id_usuario)
            ->orderByDesc('fecha')->orderByDesc('id')->firstOrFail(), 'Status' => 200]);
    }

    public function detallePorArqueo(Request $request, int $id_arqueo): JsonResponse
    {
        $day = Access::day($request, $id_arqueo);

        return new JsonResponse(['DetalleArqueo' => $day->detalle_arqueo()->get(), 'Status' => 200]);
    }

    public function resumen(Request $request, int $id): JsonResponse
    {
        $day = Access::day($request, $id);

        return new JsonResponse(['Arqueo' => $day, 'Resumen' => (new ArqueoService)->summary($day), 'Status' => 200]);
    }

    public function registros(Request $request, int $id): JsonResponse
    {
        $day = Access::day($request, $id)->load(['caja', 'subagencia', 'detalle_arqueo', 'cheques',
            'custodias.cheque', 'tasas', 'operaciones_cambio', 'pases', 'entradas_y_salidas',
            'remesas', 'envios_dhl.detalles', 'enrolamientos', 'conteos_servicios.servicio', 'papeleria']);

        return new JsonResponse(['Arqueo' => $day, 'Status' => 200]);
    }

    public function cerrar(Request $request, int $id): JsonResponse
    {
        Validator::make($request->all(), ['confirmar_cheques' => 'required|accepted', 'motivo' => 'nullable|string|max:10000'])->validate();

        return $this->transaction(function () use ($request, $id) {
            $day = Access::day($request, $id, true);

            return new JsonResponse(['Arqueo' => (new ArqueoService)->close($request, $day)->fresh(), 'Status' => 200]);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        Validator::make($request->all(), ['motivo' => 'required|string|max:10000'])->validate();

        return $this->transaction(function () use ($request, $id) {
            $day = Access::day($request, $id, true);
            Access::check($day->estado === 'abierto', 'estado', 'Solo puede anularse una jornada abierta');
            Access::check(! \App\Models\SolicitudPase::whereIn('estado', ['pendiente', 'aceptado'])->where(fn ($q) =>
                $q->where('id_arqueo_origen', $day->id)->orWhere('id_arqueo_destino', $day->id))->exists(),
                'estado', 'No puede anular una jornada con pases pendientes o aceptados');
            $before = $day->getAttributes();
            $day->update(['estado' => 'anulado']);
            Audit::record($request, $day, 'anular', $before, $day->id);

            return new JsonResponse(['Arqueo' => $day->fresh(), 'Status' => 200]);
        });
    }
}
