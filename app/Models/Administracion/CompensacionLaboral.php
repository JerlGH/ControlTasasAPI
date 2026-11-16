<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class CompensacionLaboral extends Model
{
    protected $table = 'compensaciones_laborales';

    protected $fillable = ['id_retencion', 'id_anticipo', 'id_obligacion', 'fecha', 'monto', 'id_abono_anticipo', 'id_abono_obligacion', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_retencion' => 'integer', 'id_anticipo' => 'integer', 'id_obligacion' => 'integer', 'fecha' => 'date:Y-m-d', 'monto' => 'decimal:8', 'id_abono_anticipo' => 'integer', 'id_abono_obligacion' => 'integer', 'id_usuario_registro' => 'integer'];

    public function anticipo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ObligacionLaboral::class, 'id_anticipo');
    }

    public function retencion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ObligacionLaboral::class, 'id_retencion');
    }

    public function obligacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ObligacionLaboral::class, 'id_obligacion');
    }

    public function abono_anticipo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_abono_anticipo');
    }

    public function abono_obligacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\MovimientoCuenta::class, 'id_abono_obligacion');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
