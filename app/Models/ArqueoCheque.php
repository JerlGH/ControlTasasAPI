<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArqueoCheque extends Model
{
    protected $table = 'arqueo_cheques';

    protected $fillable = [
        'id_arqueo',
        'id_cheque',
        'momento',
        'moneda',
        'monto',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'id_cheque' => 'integer',
        'monto' => 'decimal:2',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function cheque(): BelongsTo
    {
        return $this->belongsTo(Cheques::class, 'id_cheque');
    }
}
