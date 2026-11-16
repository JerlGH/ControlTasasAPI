<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class DetalleReporteMatriz extends Model
{
    protected $table = 'detalles_reporte_matriz';

    protected $fillable = ['id_reporte', 'servicio', 'moneda', 'monto', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_reporte' => 'integer', 'monto' => 'decimal:8', 'id_usuario_registro' => 'integer'];

    public function reporte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ReporteMatriz::class, 'id_reporte');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
