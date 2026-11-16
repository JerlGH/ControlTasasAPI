<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class LiquidacionMatriz extends Model
{
    protected $table = 'liquidaciones_matriz';

    protected $fillable = ['id_arqueo_madre', 'fecha', 'id_anterior', 'saldo_anterior_usd', 'motivo_saldo_inicial', 'anticipo_usd', 'comision_anticipo_usd', 'saldo_matriz_nio', 'saldo_interno_nio', 'corte_comparacion_at', 'resultado', 'estado', 'observacion', 'id_usuario_registro'];

    protected $casts = ['id_arqueo_madre' => 'integer', 'fecha' => 'date:Y-m-d', 'id_anterior' => 'integer', 'saldo_anterior_usd' => 'decimal:8', 'anticipo_usd' => 'decimal:8', 'comision_anticipo_usd' => 'decimal:8', 'saldo_matriz_nio' => 'decimal:8', 'saldo_interno_nio' => 'decimal:8', 'corte_comparacion_at' => 'datetime:Y-m-d H:i:s', 'resultado' => 'array', 'id_usuario_registro' => 'integer'];

    public function arqueo_madre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ArqueoMadre::class, 'id_arqueo_madre');
    }

    public function anterior(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\LiquidacionMatriz::class, 'id_anterior');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
