<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class OperacionFinanciera extends Model
{
    protected $table = 'operaciones_financieras';

    protected $fillable = ['fecha', 'tipo', 'moneda', 'monto', 'concepto', 'clave', 'id_cuenta_origen', 'id_cuenta_destino', 'origen_tipo', 'id_origen', 'id_entrada_salida', 'id_pase', 'id_remesa', 'id_operacion_cambio', 'lado', 'origen_snapshot', 'id_documento', 'observacion', 'estado', 'id_usuario_registro', 'clave_origen_activa'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'monto' => 'decimal:8', 'id_cuenta_origen' => 'integer', 'id_cuenta_destino' => 'integer', 'id_origen' => 'integer', 'id_entrada_salida' => 'integer', 'id_pase' => 'integer', 'id_remesa' => 'integer', 'id_operacion_cambio' => 'integer', 'origen_snapshot' => 'array', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function cuenta_origen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CuentaFinanciera::class, 'id_cuenta_origen');
    }

    public function cuenta_destino(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\CuentaFinanciera::class, 'id_cuenta_destino');
    }

    public function entrada_salida(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Entradas_Y_Salidas::class, 'id_entrada_salida');
    }

    public function pase(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Pases::class, 'id_pase');
    }

    public function remesa(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\RemesaBancaria::class, 'id_remesa');
    }

    public function operacion_cambio(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\OperacionCambioCaja::class, 'id_operacion_cambio');
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
