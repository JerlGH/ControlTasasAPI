<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mesa_De_Cambio extends Model
{
    protected $table='mesas_cambio';
    protected $fillable = [
        'id_usuario',
        'id_cliente',
        'fecha',
        'tipo_transaccion',
        'tasa',
        'monto_entrada',
        'monto_salida',
        'concepto',
        'archivo_adjunto',
        'estado_mesa',
    ];


    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(User::class,'id_usuario','id');
    }

    public function clientes(): BelongsTo
    {
        return $this->belongsTo(Cliente::class,'id_cliente','id');
    }
}
