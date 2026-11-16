<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HistorialCaja extends Model
{
    protected $table = 'historial_caja';

    protected $fillable = [
        'id_usuario',
        'id_arqueo',
        'entidad',
        'id_registro',
        'accion',
        'antes',
        'despues',
        'motivo',
        'ip',
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'id_arqueo' => 'integer',
        'id_registro' => 'integer',
        'antes' => 'array',
        'despues' => 'array',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
