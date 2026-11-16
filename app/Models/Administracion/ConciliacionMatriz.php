<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ConciliacionMatriz extends Model
{
    protected $table = 'conciliaciones_matriz';

    protected $fillable = ['id_arqueo_madre', 'id_reporte', 'resultado', 'estado', 'motivo_revision', 'revisado_at', 'observacion', 'id_usuario_registro'];

    protected $casts = ['id_arqueo_madre' => 'integer', 'id_reporte' => 'integer', 'resultado' => 'array', 'revisado_at' => 'datetime:Y-m-d H:i:s', 'id_usuario_registro' => 'integer'];

    public function arqueo_madre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ArqueoMadre::class, 'id_arqueo_madre');
    }

    public function reporte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ReporteMatriz::class, 'id_reporte');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
