<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Detalle_De_Arqueo extends Model
{
    protected $table='detalle_arqueo';
    protected $fillable = [
        'id_arqueo',
        'estado',
        'moneda',
        'total',
        'mil',
        'quinientos',
        'doscientos',
        'cien',
        'cincuenta',
        'veinte',
        'diez',
        'cinco',
        'dos',
        'uno',
    ];

    public function arqueos(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class,'id_arqueo','id');
    }
}
