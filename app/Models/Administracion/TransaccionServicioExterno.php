<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaccionServicioExterno extends Model
{
    protected $table = 'transacciones_servicios_externos';

    protected $fillable = ['id_arqueo_externo', 'clave', 'id_subagencia', 'id_cajero', 'nombre_cajero', 'banco',
        'servicio', 'motivo_sin_comision', 'tipo', 'moneda', 'numero_referencia', 'monto', 'monto_remesa_usd', 'id_remesa_caja',
        'clave_remesa_activa', 'id_documento', 'tarifa_nio', 'ganancia_nio', 'calculo', 'estado', 'observacion', 'id_usuario_registro'];

    protected $casts = ['id_arqueo_externo' => 'integer', 'id_subagencia' => 'integer', 'id_cajero' => 'integer',
        'monto' => 'decimal:8', 'monto_remesa_usd' => 'decimal:2', 'id_remesa_caja' => 'integer',
        'id_documento' => 'integer', 'tarifa_nio' => 'decimal:8', 'ganancia_nio' => 'decimal:8',
        'calculo' => 'array', 'id_usuario_registro' => 'integer'];

    public function arqueo_externo(): BelongsTo
    {
        return $this->belongsTo(ArqueoServicioExterno::class, 'id_arqueo_externo');
    }

    public function subagencia(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_cajero');
    }

    public function remesa_caja(): BelongsTo
    {
        return $this->belongsTo(\App\Models\RemesaBancaria::class, 'id_remesa_caja');
    }
}
