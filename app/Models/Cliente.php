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
        'fecha_nacimiento',
        'direccion',
        'estado_cliente',
    ];

    public function users(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }

    public function tasas_realizadas(): HasMany
    {
        return $this->hasMany(Tasa_realizada::class, 'id_cliente','id');
    }

}
