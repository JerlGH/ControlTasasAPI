<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class FuenteLibroDiario extends Model
{
    protected $table = 'fuentes_libro_diario';

    protected $fillable = ['id_libro_diario', 'id_usuario', 'id_arqueo', 'id_subagencia', 'estado_fuente', 'datos', 'huella'];

    protected $casts = ['id_libro_diario' => 'integer', 'id_usuario' => 'integer', 'id_arqueo' => 'integer', 'id_subagencia' => 'integer', 'datos' => 'array'];

    public function libro_diario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LibroDiario::class, 'id_libro_diario');
    }

    public function arqueo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Arqueo::class, 'id_arqueo');
    }

    public function usuario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario');
    }
}
