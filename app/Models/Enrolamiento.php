<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrolamiento extends Model
{
    protected $table = 'enrolamientos';

    protected $fillable = [
        'id_arqueo',
        'id_cliente',
        'id_servicio',
        'fecha_enrolamiento',
        'fecha_original',
        'nombre_completo',
        'tipo_documento',
        'numero_documento',
        'usuario_servicio',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'id_cliente' => 'integer',
        'id_servicio' => 'integer',
        'fecha_enrolamiento' => 'date:Y-m-d',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(ServicioCaja::class, 'id_servicio');
    }
}
