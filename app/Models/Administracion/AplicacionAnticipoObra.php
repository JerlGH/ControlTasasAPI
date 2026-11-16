<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class AplicacionAnticipoObra extends Model
{
    protected $table = 'aplicaciones_anticipo_obra';

    protected $fillable = ['id_anticipo', 'id_costo', 'fecha', 'monto', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_anticipo' => 'integer', 'id_costo' => 'integer', 'fecha' => 'date:Y-m-d', 'monto' => 'decimal:8', 'id_usuario_registro' => 'integer'];

    public function anticipo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoObra::class, 'id_anticipo');
    }

    public function costo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoObra::class, 'id_costo');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
