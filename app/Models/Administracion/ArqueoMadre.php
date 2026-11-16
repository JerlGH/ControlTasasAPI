<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ArqueoMadre extends Model
{
    protected $table = 'arqueo_madre';

    protected $fillable = ['fecha', 'id_subagencia', 'alcance', 'version', 'tasa_referencia', 'usuarios_esperados', 'estado', 'totales', 'cerrado_at', 'observacion', 'id_usuario_registro', 'id_libro_diario'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'id_subagencia' => 'integer', 'version' => 'integer', 'tasa_referencia' => 'decimal:6', 'usuarios_esperados' => 'array', 'totales' => 'array', 'cerrado_at' => 'datetime:Y-m-d H:i:s', 'id_usuario_registro' => 'integer', 'id_libro_diario' => 'integer'];

    public function libro_diario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LibroDiario::class, 'id_libro_diario');
    }

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
