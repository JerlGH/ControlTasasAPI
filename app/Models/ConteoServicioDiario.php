<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConteoServicioDiario extends Model
{
    protected $table = 'conteos_servicios_diarios';

    protected $fillable = [
        'id_arqueo',
        'id_servicio',
        'cantidad',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'id_servicio' => 'integer',
        'cantidad' => 'integer',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioCaja::class, 'id_servicio');
    }
}
