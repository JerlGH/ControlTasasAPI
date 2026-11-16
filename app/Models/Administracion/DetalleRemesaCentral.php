<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class DetalleRemesaCentral extends Model
{
    protected $table = 'detalles_remesa_central';

    protected $fillable = ['id_reporte', 'id_subagencia', 'banco', 'moneda', 'tipo', 'monto', 'clave_activa', 'estado', 'observacion', 'id_usuario_registro'];

    protected $casts = ['id_reporte' => 'integer', 'id_subagencia' => 'integer', 'monto' => 'decimal:8'];

    public function reporte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ReporteRemesaCentral::class, 'id_reporte');
    }
}
