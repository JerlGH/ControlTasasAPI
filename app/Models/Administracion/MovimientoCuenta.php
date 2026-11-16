<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class MovimientoCuenta extends Model
{
    protected $table = 'movimientos_cuenta_financiera';

    protected $fillable = ['id_cuenta', 'fecha', 'tipo', 'monto', 'concepto', 'id_operacion', 'origen_automatico', 'clave', 'id_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_cuenta' => 'integer', 'fecha' => 'date:Y-m-d', 'monto' => 'decimal:8', 'id_operacion' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function cuenta(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CuentaFinanciera::class, 'id_cuenta');
    }

    public function operacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\OperacionFinanciera::class, 'id_operacion');
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
