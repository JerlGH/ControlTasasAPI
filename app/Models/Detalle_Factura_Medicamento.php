<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Detalle_Factura_Medicamento extends Model
{
    protected $table='detalle_factura_medicamento';
    protected $fillable = [
        'id_factura',
        'id_medicamento',
        'estado',
        'uso_tratamiento',
        'prescripcion',
        'dosis_diaria',
        'cantidad',
        'valor',
    ];

    public function factura_medicamento(): BelongsTo
    {
        return $this->belongsTo(Factura_Medicamento::class,'id_factura','id');
    }

    public function medicamentos(): BelongsTo
    {
        return $this->belongsTo(Medicamentos::class,'id_medicamento','id');
    }

}
