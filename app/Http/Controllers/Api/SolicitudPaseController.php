<?php

namespace App\Http\Controllers\Api;

use App\Models\Arqueo;
use App\Models\Pases;
use App\Models\SolicitudPase;
use App\Models\User;
use App\Support\Caja\Access;
use App\Support\Caja\Audit;
use App\Support\Caja\Decimal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SolicitudPaseController extends RegistroCajaController
{
    protected function rules(?\Illuminate\Database\Eloquent\Model $record): array { return []; }

    private function visibles(Request $request)
    {
        $id = Access::user($request)->id;
        return SolicitudPase::where(fn ($q) => $q->where('id_usuario_origen', $id)->orWhere('id_usuario_destino', $id));
    }

    private function cajeros()
    {
        return User::query()->join('trabajadores', 'users.id_trabajador', '=', 'trabajadores.id')
            ->join('subagencias', 'trabajadores.id_subagencia', '=', 'subagencias.id')
            ->whereIn(DB::raw('LOWER(users.rol_usuario)'), ['caja', 'cajero'])
            ->whereRaw('LOWER(users.estado_usuario) = ?', ['activo'])
            ->whereRaw('LOWER(trabajadores.estadotrabajador) = ?', ['activo'])
            ->whereRaw('LOWER(subagencias.estado_agencia) = ?', ['activo']);
    }

    public function destinatarios(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), ['id_arqueo' => 'required|integer',
            'entre_subagencias' => 'sometimes|boolean'])->validate();
        $day = Access::day($request, $data['id_arqueo']);
        Access::check($day->id_usuario === Access::user($request)->id && $day->estado === 'abierto',
            'id_arqueo', 'Seleccione su jornada abierta');
        $query = $this->cajeros()->where('users.id', '!=', $day->id_usuario);
        if (! ($data['entre_subagencias'] ?? false)) {
            $query->where('trabajadores.id_subagencia', $day->id_subagencia);
        }
        $rows = $query->orderBy('subagencias.ubicacion')->orderBy('trabajadores.nombre')
            ->get(['users.id as id_usuario', 'trabajadores.id_subagencia', 'trabajadores.nombre',
                'subagencias.ubicacion', 'subagencias.nombre as subagencia'])
            ->map(fn ($r) => ['id_usuario' => (int) $r->id_usuario, 'id_subagencia' => (int) $r->id_subagencia,
                'nombre' => $r->nombre, 'ubicacion' => $r->ubicacion ?: $r->subagencia,
                'texto' => ($r->ubicacion ?: $r->subagencia).' - '.$r->nombre]);
        return new JsonResponse(['Destinatarios' => $rows, 'Status' => 200]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), ['clave' => 'sometimes|uuid',
            'id_arqueo' => 'sometimes|integer'])->validate();
        $query = $this->visibles($request);
        if (isset($data['clave'])) $query->where('clave', $data['clave']);
        if (isset($data['id_arqueo'])) {
            Access::day($request, $data['id_arqueo']);
            $query->where(fn ($q) => $q->where('id_arqueo_origen', $data['id_arqueo'])->orWhere('id_arqueo_destino', $data['id_arqueo']));
        } elseif (! isset($data['clave'])) {
            $query->where('id_usuario_destino', Access::user($request)->id)->where('estado', 'pendiente');
        }
        $rows = $query->orderBy('id')->get();
        $names = User::join('trabajadores', 'users.id_trabajador', '=', 'trabajadores.id')
            ->join('subagencias', 'trabajadores.id_subagencia', '=', 'subagencias.id')
            ->whereIn('users.id', $rows->pluck('id_usuario_origen'))
            ->get(['users.id', 'trabajadores.nombre', 'subagencias.ubicacion'])->keyBy('id');
        foreach ($rows as $row) {
            $row->setAttribute('nombre_origen', $names[$row->id_usuario_origen]->nombre ?? null);
            $row->setAttribute('ubicacion_origen', $names[$row->id_usuario_origen]->ubicacion ?? null);
        }
        return new JsonResponse(['Solicitudes' => $rows, 'Status' => 200]);
    }

    public function indexid(Request $request, int $id): JsonResponse
    {
        return new JsonResponse(['Solicitud' => $this->visibles($request)->findOrFail($id), 'Status' => 200]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), ['clave' => 'required|uuid', 'id_arqueo' => 'required|integer',
            'id_usuario_destino' => 'required|integer', 'entre_subagencias' => 'required|boolean',
            'moneda' => 'required|in:NIO,USD', 'monto' => $this->amount('required', 2, false, true),
            'referencia' => 'nullable|string|max:100', 'observacion' => 'nullable|string|max:10000'])->validate();
        $actor = Access::user($request);
        return $this->transaction(function () use ($request, $data, $actor) {
            // Todas las escrituras del flujo y el cierre bloquean primero jornadas; orden estable.
            $source = Access::day($request, $data['id_arqueo']);
            Access::check($source->id_usuario === $actor->id, 'id_arqueo', 'Solo puede enviar desde su propia jornada');
            $target = Arqueo::where('id_usuario', $data['id_usuario_destino'])->where('estado', 'abierto')->first();
            $prior = SolicitudPase::where('clave', $data['clave'])->first();
            if ($prior) return $this->repetida($prior, $data, $actor->id);
            Access::check($target !== null, 'id_usuario_destino', 'El destinatario debe tener una jornada abierta');
            $days = Arqueo::whereIn('id', [$source->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $days[$source->id]; $target = $days[$target->id];
            // Releer tras obtener los bloqueos evita dos altas con la misma clave.
            $prior = SolicitudPase::where('clave', $data['clave'])->first();
            if ($prior) return $this->repetida($prior, $data, $actor->id);
            Access::check($source->estado === 'abierto' && $target->estado === 'abierto',
                'id_arqueo', 'Ambas jornadas deben estar abiertas');
            Access::check($source->id !== $target->id && $source->id_usuario !== $target->id_usuario,
                'id_usuario_destino', 'Seleccione otro cajero');
            Access::check($this->cajeros()->where('users.id', $target->id_usuario)
                ->where('trabajadores.id_subagencia', $target->id_subagencia)->exists(),
                'id_usuario_destino', 'El destinatario debe ser un cajero activo de su ubicación actual');
            Access::check($data['entre_subagencias'] || $source->id_subagencia === $target->id_subagencia,
                'entre_subagencias', 'Habilite Pase entre subagencias para este destino');
            $solicitud = SolicitudPase::create([
                'clave' => $data['clave'], 'id_arqueo_origen' => $source->id, 'id_arqueo_destino' => $target->id,
                'id_usuario_origen' => $source->id_usuario, 'id_usuario_destino' => $target->id_usuario,
                'moneda' => $data['moneda'], 'monto' => $data['monto'], 'entre_subagencias' => $data['entre_subagencias'],
                'referencia' => $data['referencia'] ?? null, 'observacion' => $data['observacion'] ?? null,
                'estado' => 'pendiente',
            ]);
            $out = Pases::create($this->movimiento($solicitud, $source, $target, 'Salida'));
            $solicitud->update(['id_pase_salida' => $out->id]);
            Audit::record($request, $out, 'solicitar_pase', null, $source->id);
            Audit::record($request, $solicitud, 'solicitar', null, $source->id);
            return new JsonResponse(['Solicitud' => $solicitud->fresh(), 'Status' => 201], 201);
        });
    }

    private function repetida(SolicitudPase $s, array $d, int $actor): JsonResponse
    {
        if ($s->id_usuario_origen !== $actor) throw new HttpException(409, 'La clave de solicitud ya está en uso');
        $same = $s->id_arqueo_origen === (int) $d['id_arqueo'] && $s->id_usuario_destino === (int) $d['id_usuario_destino']
            && $s->moneda === $d['moneda'] && Decimal::compare($s->monto, $d['monto']) === 0
            && $s->entre_subagencias === (bool) $d['entre_subagencias']
            && $s->referencia === ($d['referencia'] ?? null) && $s->observacion === ($d['observacion'] ?? null);
        if (! $same) throw new HttpException(409, 'La clave corresponde a una captura diferente');
        return new JsonResponse(['Solicitud' => $s, 'Status' => 200]);
    }

    private function movimiento(SolicitudPase $s, Arqueo $day, Arqueo $other, string $tipo): array
    {
        $person = User::findOrFail($other->id_usuario)->trabajadores()->first();
        $agency = \App\Models\SubAgencia::find($other->id_subagencia);
        return ['id_arqueo' => $day->id, 'id_usuario' => $day->id_usuario, 'tipo' => $tipo,
            'moneda' => $s->moneda, 'monto' => $s->monto, 'medio' => 'efectivo',
            'id_caja_contraparte' => $other->id_caja, 'detalle' => mb_substr(($agency?->ubicacion ?: $agency?->nombre).' - '.$person?->nombre, 0, 255),
            'referencia' => $s->referencia, 'observacion' => $s->observacion, 'estado' => 'registrado'];
    }

    public function aceptar(Request $request, int $id): JsonResponse { return $this->resolver($request, $id, true); }
    public function rechazar(Request $request, int $id): JsonResponse { return $this->resolver($request, $id, false); }

    private function resolver(Request $request, int $id, bool $aceptar): JsonResponse
    {
        Validator::make($request->all(), ['motivo' => ($aceptar ? 'nullable' : 'required').'|string|max:10000'])->validate();
        $actor = Access::user($request);
        return $this->transaction(function () use ($request, $id, $aceptar, $actor) {
            $s = SolicitudPase::where('id_usuario_destino', $actor->id)->findOrFail($id);
            $days = Arqueo::whereIn('id', [$s->id_arqueo_origen, $s->id_arqueo_destino])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $s = SolicitudPase::whereKey($id)->lockForUpdate()->firstOrFail();
            $estado = $aceptar ? 'aceptado' : 'rechazado';
            if ($s->estado !== 'pendiente') {
                if ($s->estado !== $estado) throw new HttpException(409, 'El pase ya fue resuelto con otra decisión');
                return new JsonResponse(['Solicitud' => $s, 'Status' => 200]);
            }
            $source = $days[$s->id_arqueo_origen]; $target = $days[$s->id_arqueo_destino];
            Access::check($source->estado === 'abierto' && $target->estado === 'abierto',
                'id_arqueo', 'Ambas jornadas deben seguir abiertas');
            $before = $s->getAttributes();
            $out = Pases::whereKey($s->id_pase_salida)->lockForUpdate()->firstOrFail();
            Access::check($out->estado === 'registrado' && ! $out->id_pase_relacionado
                && $out->id_arqueo === $source->id && $out->moneda === $s->moneda
                && $out->tipo === 'Salida' && Decimal::compare($out->monto, $s->monto) === 0,
                'id_pase_salida', 'La salida no corresponde a la solicitud');
            if ($aceptar) {
                Access::check($this->cajeros()->where('users.id', $actor->id)
                    ->where('trabajadores.id_subagencia', $target->id_subagencia)->exists(),
                    'id_usuario_destino', 'El destinatario debe seguir activo como cajero de esa jornada');
                $oldOut = $out->getAttributes();
                $in = Pases::create($this->movimiento($s, $target, $source, 'Entrada') + ['id_pase_relacionado' => $out->id]);
                $out->update(['id_pase_relacionado' => $in->id]);
                $s->id_pase_entrada = $in->id;
                Audit::record($request, $in, 'aceptar_pase', null, $target->id);
                Audit::record($request, $out, 'vincular_pase', $oldOut, $source->id);
            } else {
                $oldOut = $out->getAttributes();
                $out->update(['estado' => 'anulado']);
                Audit::record($request, $out, 'rechazar_pase', $oldOut, $source->id);
            }
            $s->fill(['estado' => $estado, 'motivo' => $request->input('motivo'), 'resuelto_at' => Carbon::now()])->save();
            Audit::record($request, $s, $estado, $before, $target->id);
            return new JsonResponse(['Solicitud' => $s->fresh(), 'Status' => 200]);
        });
    }
}
