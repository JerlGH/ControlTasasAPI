<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cheques extends Model
{
    protected $table = 'cheques';

    protected $fillable = [
        'id_arqueo',
        'id_cliente',
        'estado',
        'moneda',
        'numero_cheque',
        'banco',
        'monto',
        'ganancia',
        'moneda_ganancia',
        'efectivo_entregado',
        'fecha_operacion',
        'fecha_liquidacion',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'id_cliente' => 'integer',
        'monto' => 'decimal:2',
        'ganancia' => 'decimal:2',
        'efectivo_entregado' => 'decimal:2',
        'fecha_operacion' => 'datetime:Y-m-d H:i:s',
        'fecha_liquidacion' => 'datetime:Y-m-d H:i:s',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
    public function custodias(): HasMany
    {
        return $this->hasMany(ArqueoCheque::class, 'id_cheque');
    }
}
