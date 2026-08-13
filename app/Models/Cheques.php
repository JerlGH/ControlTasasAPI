<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheques extends Model
{
    protected $table='cheques';
    protected $fillable = [
        'id_arqueo',
        'estado',
        'moneda',
        'monto',
        'ganancia',
    ];


    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class,'id_arqueo','id');
    }
}
    