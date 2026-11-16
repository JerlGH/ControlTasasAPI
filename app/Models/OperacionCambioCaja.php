<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperacionCambioCaja extends Model
{
    protected $table = 'operaciones_cambio_caja';

    protected $fillable = [
        'id_arqueo',
        'id_mesa_cambio',
        'id_cliente',
        'id_tasa_arqueo',
        'tipo',
        'monto_dolares',
        'tasa',
        'monto_cordobas',
        'medio_entrada',
        'medio_salida',
        'fecha_operacion',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'id_mesa_cambio' => 'integer',
        'id_cliente' => 'integer',
        'id_tasa_arqueo' => 'integer',
        'monto_dolares' => 'decimal:2',
        'tasa' => 'decimal:6',
        'monto_cordobas' => 'decimal:8',
        'fecha_operacion' => 'datetime:Y-m-d H:i:s',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(Mesa_De_Cambio::class, 'id_mesa_cambio');
    }
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
    public function tasa_configurada(): BelongsTo
    {
        return $this->belongsTo(TasaCambioArqueo::class, 'id_tasa_arqueo');
    }
}
