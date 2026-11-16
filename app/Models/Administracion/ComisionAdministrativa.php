<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ComisionAdministrativa extends Model
{
    protected $table = 'comisiones_administrativas';

    protected $fillable = ['fecha', 'periodo_desde', 'periodo_hasta', 'tipo', 'naturaleza', 'id_contraparte', 'id_servicio', 'id_trabajador', 'id_subagencia', 'id_cuenta', 'moneda', 'monto', 'id_movimiento_origen', 'id_documento', 'observacion', 'estado', 'id_usuario_registro', 'id_arqueo_externo', 'banco_externo', 'clave_externa_activa'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'periodo_desde' => 'date:Y-m-d', 'periodo_hasta' => 'date:Y-m-d', 'id_contraparte' => 'integer', 'id_servicio' => 'integer', 'id_trabajador' => 'integer', 'id_subagencia' => 'integer', 'id_cuenta' => 'integer', 'monto' => 'decimal:8', 'id_movimiento_origen' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer', 'id_arqueo_externo' => 'integer'];

    public function arqueo_externo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ArqueoServicioExterno::class, 'id_arqueo_externo');
    }

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ContraparteFinanciera::class, 'id_contraparte');
    }

    public function servicio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\ServicioCaja::class, 'id_servicio');
    }

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
