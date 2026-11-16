<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class CuentaLibroDiario extends Model
{
    protected $table = 'cuentas_libro_diario';

    protected $fillable = ['id_libro_diario', 'id_cuenta', 'canal', 'moneda', 'clave_activa', 'clave_cuenta_activa', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_libro_diario' => 'integer', 'id_cuenta' => 'integer'];

    public function libro_diario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LibroDiario::class, 'id_libro_diario');
    }

    public function cuenta(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaFinanciera::class, 'id_cuenta');
    }
}
