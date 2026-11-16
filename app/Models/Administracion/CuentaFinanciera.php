<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class CuentaFinanciera extends Model
{
    protected $table = 'cuentas_financieras';

    protected $fillable = ['codigo', 'nombre', 'tipo', 'moneda', 'id_contraparte', 'id_subagencia', 'rubro_capital', 'fecha_apertura', 'saldo_inicial', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_contraparte' => 'integer', 'id_subagencia' => 'integer', 'fecha_apertura' => 'date:Y-m-d', 'saldo_inicial' => 'decimal:8', 'id_usuario_registro' => 'integer'];

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ContraparteFinanciera::class, 'id_contraparte');
    }

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
