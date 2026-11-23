<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudPase extends Model
{
    protected $table = 'solicitudes_pases';
    protected $guarded = ['id'];
    protected $casts = [
        'id_arqueo_origen' => 'integer', 'id_arqueo_destino' => 'integer',
        'id_usuario_origen' => 'integer', 'id_usuario_destino' => 'integer',
        'id_pase_salida' => 'integer', 'id_pase_entrada' => 'integer',
        'monto' => 'decimal:2', 'entre_subagencias' => 'boolean',
        'resuelto_at' => 'datetime:Y-m-d H:i:s',
    ];
}
