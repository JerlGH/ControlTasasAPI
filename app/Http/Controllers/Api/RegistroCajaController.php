<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Arqueo;
use App\Models\EnvioDhl;
use App\Models\User;
use App\Support\Caja\Access;
use App\Support\Caja\Audit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class RegistroCajaController extends Controller
{
    protected string $model;
    protected string $label;
    protected string $plural;
    protected string $scope = 'journal';
    protected string $journalRelation = 'arqueo';
    protected bool $adminWrites = false;
    protected bool $annul = true;
    protected bool $normalizeType = true;
    protected array $closedFollowup = [];
    protected array $immutable = ['id_arqueo'];

    abstract protected function rules(?Model $record): array;

    protected function amount(string $required = 'required', int $scale = 2, bool $signed = false, bool $positive = false): array
    {
        $digits = $scale === 8 ? 16 : ($scale === 6 ? 6 : 16);

        return [$required, 'numeric', 'regex:/^'.($signed ? '-?' : '').'\d{1,'.$digits.'}(?:\.\d{1,'.$scale.'})?$/D', $positive ? 'gt:0' : ($signed ? 'numeric' : 'min:0')];
    }

    protected function common(?Model $record): array
    {
        return ['id_arqueo' => [$record ? 'sometimes' : 'required', 'integer', 'exists:arqueo,id'],
            'estado' => ['sometimes', 'in:registrado,anulado'], 'observacion' => ['nullable', 'string', 'max:10000']];
    }

    protected function prepare(Request $request, array $values, ?Model $record, ?Arqueo $day): array
    {
        return $values;
    }

    protected function deleting(Request $request, Model $record, ?Arqueo $day): void
    {
    }

    protected function query(Request $request): Builder
    {
        $query = ($this->model)::query();
        $user = Access::user($request);
        if (Access::admin($request)) {
            return $query;
        }
        if ($this->scope === 'catalog') {
            return $query;
        }
        if ($this->scope === 'agency') {
            return $query->where('id_subagencia', Access::agency($user));
        }
        if ($this->scope === 'quotes') {
            return $query->where(fn ($q) => $q->where('id_subagencia', Access::agency($user))->orWhereNull('id_subagencia'));
        }
        if ($this->scope === 'owned') {
            return $query->where('id_usuario', $user->id);
        }
        if ($this->scope === 'cheques') {
            return $query->where(fn ($q) => $q->whereHas('arqueo', fn ($a) => $a->where('id_usuario', $user->id))
                ->orWhereHas('custodias.arqueo', fn ($a) => $a->where('id_usuario', $user->id)));
        }

        return $query->whereHas($this->journalRelation, fn ($q) => $q->where('id_usuario', $user->id));
    }

    public function index(Request $request): JsonResponse
    {
        $filters = Validator::make($request->query(), [
            'id_arqueo' => 'sometimes|integer', 'id_usuario' => 'sometimes|integer',
            'id_subagencia' => 'sometimes|integer', 'fecha_desde' => 'sometimes|date_format:Y-m-d',
            'fecha_hasta' => $request->filled('fecha_desde') ? 'sometimes|date_format:Y-m-d|after_or_equal:fecha_desde' : 'sometimes|date_format:Y-m-d',
            'per_page' => 'sometimes|integer|between:1,100', 'page' => 'sometimes|integer|min:1',
        ])->validate();
        $query = $this->query($request);
        if (in_array($this->scope, ['journal', 'cheques'], true)) {
            $query->whereHas($this->journalRelation, function ($day) use ($filters) {
                foreach (['id_arqueo' => 'id', 'id_usuario' => 'id_usuario', 'id_subagencia' => 'id_subagencia'] as $key => $column) {
                    if (isset($filters[$key])) {
                        $day->where($column, $filters[$key]);
                    }
                }
                if (isset($filters['fecha_desde'])) {
                    $day->whereDate('fecha', '>=', $filters['fecha_desde']);
                }
                if (isset($filters['fecha_hasta'])) {
                    $day->whereDate('fecha', '<=', $filters['fecha_hasta']);
                }
            });
        } else {
            foreach (['id_arqueo', 'id_usuario', 'id_subagencia'] as $field) {
                if (isset($filters[$field]) && in_array($field, (new $this->model)->getFillable(), true)) {
                    $query->where($field, $filters[$field]);
                }
            }
            if ($this->scope === 'owned') {
                if (isset($filters['fecha_desde'])) {
                    $query->whereDate('fecha', '>=', $filters['fecha_desde']);
                }
                if (isset($filters['fecha_hasta'])) {
                    $query->whereDate('fecha', '<=', $filters['fecha_hasta']);
                }
            }
        }
        foreach (['moneda', 'tipo', 'categoria', 'estado', 'id_servicio', 'id_envio_dhl', 'momento', 'guia', 'numero_documento'] as $field) {
            if ($request->filled($field) && in_array($field, (new $this->model)->getFillable(), true)) {
                Validator::make($request->query(), [$field => ['string', 'max:255']])->validate();
                $query->where($field, $request->query($field));
            }
        }
        $page = $query->orderByDesc('id')->paginate($filters['per_page'] ?? 50, ['*'], 'page', $filters['page'] ?? 1);

        return new JsonResponse([$this->plural => $page->items(), 'Paginacion' => [
            'total' => $page->total(), 'pagina' => $page->currentPage(), 'por_pagina' => $page->perPage(),
        ], 'Status' => 200]);
    }

    public function indexid(Request $request, int $id): JsonResponse
    {
        return new JsonResponse([$this->label => $this->query($request)->findOrFail($id), 'Status' => 200]);
    }

    public function store(Request $request): JsonResponse
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->save($request, $id);
    }

    private function save(Request $request, ?int $id = null): JsonResponse
    {
        if ($this->adminWrites) {
            Access::requireAdmin($request);
        }

        return $this->transaction(function () use ($request, $id) {
            $record = $id ? $this->query($request)->findOrFail($id) : null;
            $payload = $request->all();
            foreach (['moneda', 'moneda_ganancia'] as $field) {
                if (isset($payload[$field]) && is_string($payload[$field])) {
                    $payload[$field] = match (strtoupper(trim($payload[$field]))) {
                        'C$', 'CORDOBAS' => 'NIO', '$', 'US$', 'U$' => 'USD', default => strtoupper(trim($payload[$field])),
                    };
                }
            }
            if ($this->normalizeType && isset($payload['tipo']) && is_string($payload['tipo'])) {
                $payload['tipo'] = ucfirst(mb_strtolower(trim($payload['tipo'])));
            }
            $rules = $this->rules($record) + ['id' => 'prohibited', 'created_at' => 'prohibited',
                'updated_at' => 'prohibited', 'motivo' => 'nullable|string|max:10000'];
            $data = Validator::make($payload, $rules)->validate();
            foreach ($this->immutable as $field) {
                if ($record && array_key_exists($field, $data)) {
                    Access::check((string) $record->$field === (string) $data[$field], $field, 'La relacion de origen no puede cambiarse');
                }
            }
            $candidate = array_replace($record?->getAttributes() ?? [], $data);
            $day = $this->journal($request, $candidate);
            if ($day && $day->estado !== 'abierto') {
                $allowed = $record && $day->estado === 'cerrado' && $this->closedFollowup !== []
                    && array_diff(array_keys($payload), [...$this->closedFollowup, 'motivo']) === [];
                Access::check($allowed, 'id_arqueo', 'La jornada ya no admite cambios de captura');
            }
            if ($record) {
                $record->refresh();
                $candidate = array_replace($record->getAttributes(), $data);
            }
            if ($day && in_array('id_usuario', (new $this->model)->getFillable(), true)) {
                Access::check(! isset($data['id_usuario']) || (int) $data['id_usuario'] === $day->id_usuario,
                    'id_usuario', 'El usuario debe corresponder a la jornada');
                $candidate['id_usuario'] = $day->id_usuario;
            }
            $before = $record?->getAttributes();
            if ($day && ! empty($candidate['fecha_operacion'])) {
                Access::check(Carbon::parse($candidate['fecha_operacion'])->toDateString() === $day->fecha->toDateString(),
                    'fecha_operacion', 'La fecha de la operacion debe corresponder a la jornada');
            }
            $values = $this->prepare($request, $candidate, $record, $day);
            unset($values['motivo']);
            $record ??= new $this->model;
            $record->fill($values)->save();
            Audit::record($request, $record, $id ? 'actualizar' : 'crear', $before, $day?->id);

            return new JsonResponse([$this->label => $record->fresh(), 'Status' => $id ? 200 : 201], $id ? 200 : 201);
        });
    }

    protected function journal(Request $request, array $data): ?Arqueo
    {
        if ($this->scope === 'journal' || $this->scope === 'cheques') {
            $id = $this->journalRelation === 'envio.arqueo'
                ? EnvioDhl::findOrFail($data['id_envio_dhl'])->id_arqueo : ($data['id_arqueo'] ?? null);

            return $id ? Access::day($request, (int) $id, true) : null;
        }
        if ($this->scope === 'owned' && ! empty($data['id_arqueo'])) {
            return Access::day($request, (int) $data['id_arqueo'], true);
        }

        return null;
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($this->adminWrites) {
            Access::requireAdmin($request);
        }
        Validator::make($request->all(), ['motivo' => 'required|string|max:10000'])->validate();

        return $this->transaction(function () use ($request, $id) {
            $record = $this->query($request)->findOrFail($id);
            $day = $this->journal($request, $record->getAttributes());
            Access::check(! $day || $day->estado === 'abierto', 'id_arqueo', 'La jornada ya no admite eliminaciones');
            $before = $record->getAttributes();
            $this->deleting($request, $record, $day);
            if ($this->annul) {
                $values = $this->prepare($request, array_replace($before, ['estado' => 'anulado']), $record, $day);
                $record->fill($values)->save();
            } else {
                $record->delete();
            }
            Audit::record($request, $record, $this->annul ? 'anular' : 'eliminar', $before, $day?->id);

            return new JsonResponse(['Mensaje' => $this->annul ? 'Registro anulado' : 'Registro eliminado', 'Status' => 200]);
        });
    }

    protected function transaction(callable $operation): mixed
    {
        try {
            return DB::transaction($operation, 3);
        } catch (QueryException $e) {
            if (str_starts_with((string) $e->getCode(), '23') || str_contains($e->getMessage(), 'constraint')) {
                throw new HttpException(409, 'El registro esta duplicado o mantiene relaciones que impiden el cambio');
            }
            throw $e;
        }
    }
}
