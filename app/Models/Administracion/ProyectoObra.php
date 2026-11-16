<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ProyectoObra extends Model
{
    protected $table = 'proyectos_obra';

    protected $fillable = ['codigo', 'nombre', 'fecha', 'id_subagencia', 'id_contraparte', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'id_subagencia' => 'integer', 'id_contraparte' => 'integer', 'id_usuario_registro' => 'integer'];

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ContraparteFinanciera::class, 'id_contraparte');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
