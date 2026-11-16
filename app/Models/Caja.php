<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Caja extends Model
{
    protected $table = 'cajas';

    protected $fillable = [
        'id_subagencia',
        'codigo',
        'nombre',
        'estado',
    ];

    protected $casts = [
        'id_subagencia' => 'integer',
    ];

    public function subagencia(): BelongsTo
    {
        return $this->belongsTo(SubAgencia::class, 'id_subagencia');
    }
    public function arqueos(): HasMany
    {
        return $this->hasMany(Arqueo::class, 'id_caja');
    }
}
