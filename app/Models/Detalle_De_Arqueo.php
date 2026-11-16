<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Detalle_De_Arqueo extends Model
{
    protected $table = 'detalle_arqueo';

    protected $fillable = [
        'id_arqueo',
        'estado',
        'moneda',
        'total',
        'mil',
        'quinientos',
        'doscientos',
        'cien',
        'cincuenta',
        'veinte',
        'diez',
        'cinco',
        'dos',
        'uno',
        'contado_at',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'total' => 'decimal:2',
        'mil' => 'integer',
        'quinientos' => 'integer',
        'doscientos' => 'integer',
        'cien' => 'integer',
        'cincuenta' => 'integer',
        'veinte' => 'integer',
        'diez' => 'integer',
        'cinco' => 'integer',
        'dos' => 'integer',
        'uno' => 'integer',
        'contado_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function arqueos(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
}
