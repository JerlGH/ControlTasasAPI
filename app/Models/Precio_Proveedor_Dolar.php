<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Precio_Proveedor_Dolar extends Model
{
    protected $table = 'precio_proveedor_dolar';

    protected $fillable = [
        'id_usuario',
        'id_subagencia',
        'estado',
        'proveedor_actual',
        'precio_dolar',
        'vigente_desde',
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'id_subagencia' => 'integer',
        'precio_dolar' => 'decimal:6',
        'vigente_desde' => 'datetime:Y-m-d H:i:s',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
    public function subagencia(): BelongsTo
    {
        return $this->belongsTo(SubAgencia::class, 'id_subagencia');
    }
}
