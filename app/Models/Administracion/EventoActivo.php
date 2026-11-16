<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class EventoActivo extends Model
{
    protected $table = 'eventos_activo';

    protected $fillable = ['id_activo', 'fecha', 'tipo', 'cantidad', 'id_subagencia_origen', 'id_subagencia_destino', 'concepto', 'id_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_activo' => 'integer', 'fecha' => 'date:Y-m-d', 'cantidad' => 'decimal:6', 'id_subagencia_origen' => 'integer', 'id_subagencia_destino' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function activo(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ActivoAdministrativo::class, 'id_activo');
    }

    public function subagencia_origen(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia_origen');
    }

    public function subagencia_destino(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia_destino');
    }

    public function documento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\DocumentoFinanciero::class, 'id_documento');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
