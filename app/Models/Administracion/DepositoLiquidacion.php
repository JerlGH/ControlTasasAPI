<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class DepositoLiquidacion extends Model
{
    protected $table = 'depositos_liquidacion';

    protected $fillable = ['id_liquidacion', 'id_operacion', 'fecha', 'fecha_acreditacion', 'estado', 'observacion', 'id_usuario_registro', 'id_cuenta_transito', 'id_movimiento_transito', 'id_movimiento_acreditacion', 'clave_operacion_activa'];

    protected $casts = ['id_liquidacion' => 'integer', 'id_operacion' => 'integer', 'fecha' => 'date:Y-m-d', 'fecha_acreditacion' => 'date:Y-m-d', 'id_usuario_registro' => 'integer', 'id_cuenta_transito' => 'integer', 'id_movimiento_transito' => 'integer', 'id_movimiento_acreditacion' => 'integer'];

    public function liquidacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\LiquidacionMatriz::class, 'id_liquidacion');
    }

    public function operacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\OperacionFinanciera::class, 'id_operacion');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }

    public function cuenta_transito(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CuentaFinanciera::class, 'id_cuenta_transito');
    }

    public function movimiento_transito(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_movimiento_transito');
    }

    public function movimiento_acreditacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_movimiento_acreditacion');
    }
}
