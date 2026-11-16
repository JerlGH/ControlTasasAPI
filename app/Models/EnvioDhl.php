<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnvioDhl extends Model
{
    protected $table = 'envios_dhl';

    protected $fillable = [
        'id_arqueo',
        'id_cliente',
        'fecha_envio',
        'fecha_original',
        'guia',
        'nombre_remitente',
        'nombre_beneficiario',
        'nombre_contacto',
        'telefono_contacto',
        'tipo',
        'descripcion',
        'destino',
        'estado_envio',
        'confirmacion_original',
        'fecha_entrega',
        'fecha_entrega_original',
        'estado',
        'observacion',
    ];

    protected $casts = [
        'id_arqueo' => 'integer',
        'id_cliente' => 'integer',
        'fecha_envio' => 'date:Y-m-d',
        'fecha_entrega' => 'datetime:Y-m-d H:i:s',
    ];

    public function arqueo(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo');
    }
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleEnvioDhl::class, 'id_envio_dhl');
    }
}
