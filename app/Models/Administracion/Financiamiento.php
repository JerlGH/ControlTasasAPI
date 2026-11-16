<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class Financiamiento extends Model
{
    protected $table = 'financiamientos';

    protected $fillable = ['referencia', 'id_cuenta', 'id_contraparte', 'fecha', 'moneda', 'principal', 'tasa_interes', 'base_tasa', 'periodicidad', 'numero_cuotas', 'fecha_vencimiento', 'id_operacion_desembolso', 'id_movimiento_origen', 'id_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_cuenta' => 'integer', 'id_contraparte' => 'integer', 'fecha' => 'date:Y-m-d', 'principal' => 'decimal:8', 'tasa_interes' => 'decimal:6', 'numero_cuotas' => 'integer', 'fecha_vencimiento' => 'date:Y-m-d', 'id_operacion_desembolso' => 'integer', 'id_movimiento_origen' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function cuenta(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CuentaFinanciera::class, 'id_cuenta');
    }

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ContraparteFinanciera::class, 'id_contraparte');
    }

    public function operacion_desembolso(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\OperacionFinanciera::class, 'id_operacion_desembolso');
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
