<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class DetalleArqueoMadre extends Model
{
    protected $table = 'detalle_arqueo_madre';

    protected $fillable = ['id_arqueo_madre', 'id_usuario', 'id_subagencia', 'id_arqueo', 'estado_fuente', 'datos', 'huella', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_arqueo_madre' => 'integer', 'id_usuario' => 'integer', 'id_subagencia' => 'integer', 'id_arqueo' => 'integer', 'datos' => 'array', 'id_usuario_registro' => 'integer'];

    public function arqueo_madre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ArqueoMadre::class, 'id_arqueo_madre');
    }

    public function usuario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario');
    }

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function arqueo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Arqueo::class, 'id_arqueo');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
