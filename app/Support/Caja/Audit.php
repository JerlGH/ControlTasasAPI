<?php

namespace App\Support\Caja;

use App\Models\HistorialCaja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class Audit
{
    public static function record(Request $request, Model $record, string $action, ?array $before = null, ?int $day = null): void
    {
        HistorialCaja::create([
            'id_usuario' => Access::user($request)->id,
            'id_arqueo' => $day,
            'entidad' => $record->getTable(),
            'id_registro' => $record->getKey(),
            'accion' => $action,
            'antes' => $before,
            'despues' => $action === 'eliminar' ? null : $record->fresh()->getAttributes(),
            'motivo' => $request->input('motivo'),
            'ip' => $request->ip(),
        ]);
    }
}
