<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Factura_Medicamento extends Model
{
    protected $table='factura_medicamento';
    protected $fillable = [
        'id_arqueo',
        'estado',
        'awb',
        'nombre_expedidor',
        'direccion_expedidor',
        'ciudad_expedidor',
        'phn_expedidor',
        'nombre_consignatario',
        'direccion_consignatario',
        'ciudad_consignatario',
        'phn_consignatario',
        'unidades_total',
        'peso_total',
        'monto_total',
    ];

    public function detalle_factura_medicamento(): HasMany
    {
        return $this->hasMany(Detalle_Factura_Medicamento::class, 'id_factura', 'id');
    }

    public function arqueos(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class,'id_arqueo','id');
    }
}
