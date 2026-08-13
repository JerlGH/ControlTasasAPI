<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Medicamentos extends Model
{
    protected $table='medicamentos';
    protected $fillable = [
        'nombre',
        'presentación',
        'Manufacturador',
    ];

    public function detalle_factura_medicamento(): HasMany
    {
        return $this->hasMany(Detalle_Factura_Medicamento::class,'id_medicamento','id');
    }
}
