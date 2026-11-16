<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ContraparteFinanciera extends Model
{
    protected $table = 'contrapartes_financieras';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'id_cliente', 'id_trabajador', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_cliente' => 'integer', 'id_trabajador' => 'integer', 'id_usuario_registro' => 'integer'];

    public function cliente(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Cliente::class, 'id_cliente');
    }

    public function trabajador(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Trabajador::class, 'id_trabajador');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
