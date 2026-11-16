<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class GestionIncidencia extends Model
{
    protected $table = 'gestiones_incidencia';

    protected $fillable = ['id_incidencia', 'fecha', 'gestion', 'detalle', 'id_documento', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_incidencia' => 'integer', 'fecha' => 'date:Y-m-d', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function incidencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\IncidenciaFinanciera::class, 'id_incidencia');
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
