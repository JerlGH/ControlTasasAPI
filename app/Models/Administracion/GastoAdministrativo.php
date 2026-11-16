<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class GastoAdministrativo extends Model
{
    protected $table = 'gastos_administrativos';

    protected $fillable = ['fecha', 'periodo_desde', 'periodo_hasta', 'id_centro_costo', 'id_subagencia', 'id_contraparte', 'ambito', 'categoria', 'concepto', 'moneda', 'monto', 'id_cuenta', 'id_movimiento_origen', 'id_documento', 'observacion', 'estado', 'id_usuario_registro', 'origen_automatico'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'periodo_desde' => 'date:Y-m-d', 'periodo_hasta' => 'date:Y-m-d', 'id_centro_costo' => 'integer', 'id_subagencia' => 'integer', 'id_contraparte' => 'integer', 'monto' => 'decimal:8', 'id_cuenta' => 'integer', 'id_movimiento_origen' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function centro_costo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CentroCosto::class, 'id_centro_costo');
    }

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ContraparteFinanciera::class, 'id_contraparte');
    }

    public function cuenta(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaFinanciera::class, 'id_cuenta');
    }

    public function movimiento_origen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(MovimientoCuenta::class, 'id_movimiento_origen');
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
