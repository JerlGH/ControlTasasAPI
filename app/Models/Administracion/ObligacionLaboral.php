<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ObligacionLaboral extends Model
{
    protected $table = 'obligaciones_laborales';

    protected $fillable = ['fecha', 'periodo_desde', 'periodo_hasta', 'id_trabajador', 'id_subagencia', 'id_cuenta', 'concepto', 'moneda', 'monto', 'id_operacion_anticipo', 'id_movimiento_origen', 'id_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'periodo_desde' => 'date:Y-m-d', 'periodo_hasta' => 'date:Y-m-d', 'id_trabajador' => 'integer', 'id_subagencia' => 'integer', 'id_cuenta' => 'integer', 'monto' => 'decimal:8', 'id_operacion_anticipo' => 'integer', 'id_movimiento_origen' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function trabajador(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Trabajador::class, 'id_trabajador');
    }

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function cuenta(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CuentaFinanciera::class, 'id_cuenta');
    }

    public function operacion_anticipo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OperacionFinanciera::class, 'id_operacion_anticipo');
    }

    public function movimiento_origen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_movimiento_origen');
    }

    public function documento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\DocumentoFinanciero::class, 'id_documento');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
