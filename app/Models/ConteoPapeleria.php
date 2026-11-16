<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConteoPapeleria extends Model
{
    protected $table = 'conteos_papeleria';

    protected $fillable = [
        'id_usuario',
        'id_subagencia',
        'id_arqueo',
        'id_servicio',
        'fecha',
        'concepto',
        'cantidad',
        'observacion',
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'id_subagencia' => 'integer',
        'id_arqueo' => 'integer',
        'id_servicio' => 'integer',
        'fecha' => 'date:Y-m-d',
        'cantidad' => 'integer',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
    public function subagencia(): BelongsTo
    {
        return $this->belongsTo(SubAgencia::class, 'id_subagencia');
    }
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioCaja::class, 'id_servicio');
    }
}
