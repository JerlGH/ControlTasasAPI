<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Entradas_Y_Salidas extends Model
{
    protected $table = 'entradas_y_salidas';

    protected $fillable = [
        'id_arqueo',
        'estado',
        'tipo',
        'moneda',
        'monto',
        'observacion',
    ];

    public function arqueos(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class,'id_arqueo','id');
    }
}
