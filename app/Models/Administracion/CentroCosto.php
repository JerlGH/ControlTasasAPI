<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class CentroCosto extends Model
{
    protected $table = 'centros_costo';

    protected $fillable = ['codigo', 'nombre', 'id_subagencia', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_subagencia' => 'integer', 'id_usuario_registro' => 'integer'];

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
