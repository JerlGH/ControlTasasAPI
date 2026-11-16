<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entradas_Y_Salidas extends Model
{
    protected $table = 'entradas_y_salidas';

    protected $fillable = [
        'id_arqueo',
        'categoria',
        'estado',
        'tipo',
        'moneda',
        'monto',
        'medio',
        'monto_efectivo',
        'monto_cheques',
        'detalle',
        'banco',
        'numero_cuenta',
        'referencia',
        'id_subagencia_contraparte',
        'id_movimiento_relacionado',
        'fecha_operacion',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'monto' => 'decimal:2',
        'monto_efectivo' => 'decimal:2',
        'monto_cheques' => 'decimal:2',
        'id_subagencia_contraparte' => 'integer',
        'id_movimiento_relacionado' => 'integer',
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
    public function subagencia_contraparte(): BelongsTo
    {
        return $this->belongsTo(SubAgencia::class, 'id_subagencia_contraparte');
    }
    public function relacionado(): BelongsTo
    {
        return $this->belongsTo(Entradas_Y_Salidas::class, 'id_movimiento_relacionado');
    }
}
