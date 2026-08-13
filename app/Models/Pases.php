<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Pases extends Model
{
    protected $table='pases';
    protected $fillable = [
        'id_arqueo',
        'id_usuario',
        'estado',
        'tipo',
        'moneda',
        'monto'
    ];


    public function arqueos(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class,'id_arqueo','id');
    }

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(User::class,'id_usuario','id');
    }
}
