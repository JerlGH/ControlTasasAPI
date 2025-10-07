<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    //
    protected $table= 'clientes';

    protected $fillable = [
        'id_usuario',
        'nombre',
        'cedula',
        'telefono',
        'direccion',
        'estado_cliente',
    ];

    public function users(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }

    public function mesas_de_cambio(): HasMany
    {
        return $this->hasMany(Mesa_De_Cambio::class, 'id_cliente','id');
    }

}
