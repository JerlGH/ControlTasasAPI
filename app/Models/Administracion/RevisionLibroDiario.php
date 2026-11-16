<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class RevisionLibroDiario extends Model
{
    protected $table = 'revisiones_libro_diario';

    protected $fillable = ['id_libro_diario', 'clave_control', 'huella', 'resultado', 'detalle', 'id_documento', 'referencia', 'revisado_at', 'id_usuario_registro'];

    protected $casts = ['id_libro_diario' => 'integer', 'revisado_at' => 'datetime:Y-m-d H:i:s'];

    public function libro_diario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LibroDiario::class, 'id_libro_diario');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
