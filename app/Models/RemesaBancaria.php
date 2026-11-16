<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RemesaBancaria extends Model
{
    protected $table = 'remesas_bancarias';

    protected $fillable = [
        'id_arqueo',
        'banco',
        'tipo',
        'moneda',
        'numero_remesa',
        'monto',
        'medio',
        'fecha_operacion',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'monto' => 'decimal:2',
        'fecha_operacion' => 'datetime:Y-m-d H:i:s',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
}
