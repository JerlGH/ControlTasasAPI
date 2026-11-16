<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TasaCambioArqueo extends Model
{
    protected $table = 'tasas_cambio_arqueo';

    protected $fillable = [
        'id_arqueo',
        'tipo',
        'numero_precio',
        'tasa',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'numero_precio' => 'integer',
        'tasa' => 'decimal:6',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function operaciones(): HasMany
    {
        return $this->hasMany(OperacionCambioCaja::class, 'id_tasa_arqueo');
    }
}
