<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class AplicacionCuenta extends Model
{
    protected $table = 'aplicaciones_cuenta_financiera';

    protected $fillable = ['id_cargo', 'id_abono', 'fecha', 'monto', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_cargo' => 'integer', 'id_abono' => 'integer', 'fecha' => 'date:Y-m-d', 'monto' => 'decimal:8', 'id_usuario_registro' => 'integer'];

    public function cargo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_cargo');
    }

    public function abono(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_abono');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
