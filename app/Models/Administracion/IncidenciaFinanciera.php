<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class IncidenciaFinanciera extends Model
{
    protected $table = 'incidencias_financieras';

    protected $fillable = ['fecha', 'referencia', 'servicio', 'id_arqueo', 'id_envio_dhl', 'id_subagencia', 'id_operador', 'biller', 'referencia_cliente', 'ticket', 'caso', 'motivo_incidencia', 'moneda', 'monto', 'id_cuenta', 'id_movimiento_origen', 'gestion', 'id_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'id_arqueo' => 'integer', 'id_envio_dhl' => 'integer', 'id_subagencia' => 'integer', 'id_operador' => 'integer', 'monto' => 'decimal:8', 'id_cuenta' => 'integer', 'id_movimiento_origen' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function arqueo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Arqueo::class, 'id_arqueo');
    }

    public function envio_dhl(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\EnvioDhl::class, 'id_envio_dhl');
    }

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function operador(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_operador');
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
