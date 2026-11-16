<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetalleEnvioDhl extends Model
{
    protected $table = 'detalle_envios_dhl';

    protected $fillable = [
        'id_envio_dhl',
        'descripcion',
        'cantidad',
        'unidad',
        'peso',
        'observacion',
    ];

    protected $casts = [
        'id_envio_dhl' => 'integer',
        'cantidad' => 'integer',
        'peso' => 'decimal:3',
    ];

    public function envio(): BelongsTo
    {
        return $this->belongsTo(EnvioDhl::class, 'id_envio_dhl');
    }
}
