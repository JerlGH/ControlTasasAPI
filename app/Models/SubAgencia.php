<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubAgencia extends Model
{
    protected $table= 'subagencias';

    protected $fillable = [
        'nombre',
        'ubicacion',
        'fecha_apertura',
        'fecha_cierre',
        'direccion',
        'estado_agencia',
    ];
    public function trabajadores(): HasMany
    {
        return $this->hasMany(Trabajador::class, 'id_subagencia','id');
    }
}
