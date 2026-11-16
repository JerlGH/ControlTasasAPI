<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class HistorialFinanciero extends Model
{
    protected $table = 'historial_financiero';

    protected $fillable = ['entidad', 'id_registro', 'accion', 'antes', 'despues', 'motivo', 'ip', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_registro' => 'integer', 'antes' => 'array', 'despues' => 'array', 'id_usuario_registro' => 'integer'];

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
