<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ReporteRemesaCentral extends Model
{
    protected $table = 'reportes_remesa_central';

    protected $fillable = ['fecha', 'corte_at', 'referencia', 'fuente', 'agencias_esperadas', 'id_documento', 'estado', 'confirmado_at', 'observacion', 'id_usuario_registro', 'id_arqueo_externo'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'corte_at' => 'datetime:Y-m-d H:i:s', 'agencias_esperadas' => 'array', 'confirmado_at' => 'datetime:Y-m-d H:i:s', 'id_arqueo_externo' => 'integer'];

    public function arqueo_externo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ArqueoServicioExterno::class, 'id_arqueo_externo');
    }

    public function detalles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DetalleRemesaCentral::class, 'id_reporte');
    }
}
