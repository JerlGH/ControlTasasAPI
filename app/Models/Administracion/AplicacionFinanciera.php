<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class AplicacionFinanciera extends Model
{
    protected $table = 'aplicaciones_financieras';

    protected $fillable = ['id_operacion', 'fecha', 'monto', 'tasa_cambio', 'monto_destino', 'id_gasto', 'id_obligacion', 'id_comision', 'id_obra', 'id_activo', 'id_cuota', 'id_financiamiento', 'id_incidencia', 'componentes', 'id_movimiento_generado', 'observacion', 'estado', 'id_usuario_registro', 'id_cargo', 'id_aplicacion_cuenta_generada'];

    protected $casts = ['id_operacion' => 'integer', 'fecha' => 'date:Y-m-d', 'monto' => 'decimal:8', 'tasa_cambio' => 'decimal:6', 'monto_destino' => 'decimal:8', 'id_gasto' => 'integer', 'id_obligacion' => 'integer', 'id_comision' => 'integer', 'id_obra' => 'integer', 'id_activo' => 'integer', 'id_cuota' => 'integer', 'id_financiamiento' => 'integer', 'id_incidencia' => 'integer', 'componentes' => 'array', 'id_movimiento_generado' => 'integer', 'id_usuario_registro' => 'integer', 'id_cargo' => 'integer', 'id_aplicacion_cuenta_generada' => 'integer'];

    public function operacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\OperacionFinanciera::class, 'id_operacion');
    }

    public function gasto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\GastoAdministrativo::class, 'id_gasto');
    }

    public function obligacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ObligacionLaboral::class, 'id_obligacion');
    }

    public function comision(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ComisionAdministrativa::class, 'id_comision');
    }

    public function obra(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoObra::class, 'id_obra');
    }

    public function activo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ActivoAdministrativo::class, 'id_activo');
    }

    public function cuota(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CuotaFinanciamiento::class, 'id_cuota');
    }

    public function financiamiento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\Financiamiento::class, 'id_financiamiento');
    }

    public function incidencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\IncidenciaFinanciera::class, 'id_incidencia');
    }

    public function movimiento_generado(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_movimiento_generado');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }

    public function cargo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_cargo');
    }

    public function aplicacion_cuenta_generada(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\AplicacionCuenta::class, 'id_aplicacion_cuenta_generada');
    }
}
