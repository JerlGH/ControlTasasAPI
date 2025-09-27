<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tasa_realizada extends Model
{
    protected $table='tasas_realizadas';
    protected $fillable = [
        'id_usuario',
        'id_cliente',
        'fecha',
        'tipo_tasa',
        'precio',
        'monto_entrada',
        'monto_salida',
        'concepto',
        'archivo_adjunto',
        'estado_tasa',
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
