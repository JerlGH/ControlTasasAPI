<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Precio_Proveedor_Dolar extends Model
{
    protected $table = 'precio_proveedor_dolar';

    protected $fillable = [
        'id_usuario',
        'estado',
        'proveedor_actual',
        'precio_dolar',
    ];
    
    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(User::class,'id_usuario','id');
    }
}
