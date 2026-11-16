<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class CuotaFinanciamiento extends Model
{
    protected $table = 'cuotas_financiamiento';

    protected $fillable = ['id_financiamiento', 'numero', 'fecha', 'capital', 'interes', 'seguro', 'comision', 'monto', 'moneda', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_financiamiento' => 'integer', 'numero' => 'integer', 'fecha' => 'date:Y-m-d', 'capital' => 'decimal:8', 'interes' => 'decimal:8', 'seguro' => 'decimal:8', 'comision' => 'decimal:8', 'monto' => 'decimal:8', 'id_usuario_registro' => 'integer'];

    public function financiamiento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\Financiamiento::class, 'id_financiamiento');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
