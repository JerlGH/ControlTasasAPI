<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pases extends Model
{
    protected $table = 'pases';

    protected $fillable = [
        'id_arqueo',
        'id_usuario',
        'id_caja_contraparte',
        'id_pase_relacionado',
        'estado',
        'tipo',
        'moneda',
        'monto',
        'medio',
        'detalle',
        'referencia',
        'fecha_operacion',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'id_usuario' => 'integer',
        'id_caja_contraparte' => 'integer',
        'id_pase_relacionado' => 'integer',
        'monto' => 'decimal:2',
        'fecha_operacion' => 'datetime:Y-m-d H:i:s',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function arqueos(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
    public function caja_contraparte(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'id_caja_contraparte');
    }
    public function relacionado(): BelongsTo
    {
        return $this->belongsTo(Pases::class, 'id_pase_relacionado');
    }
}
