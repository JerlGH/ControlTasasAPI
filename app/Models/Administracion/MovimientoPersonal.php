<?php

namespace App\Models\Administracion;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoPersonal extends Model
{
    protected $table = 'movimientos_personales';

    protected $fillable = [
        'clave', 'id_cuenta', 'id_operacion', 'fecha', 'tipo', 'monto', 'moneda_origen', 'tasa_cambio', 'moneda',
        'monto_cuenta', 'saldo_anterior', 'saldo_posterior', 'fondos_utilizados', 'fondos_incrementados',
        'deuda_generada', 'deuda_cancelada', 'id_movimiento_generado', 'concepto', 'id_documento',
        'observacion', 'estado', 'id_usuario_registro',
    ];

    protected $casts = [
        'id_cuenta' => 'integer', 'id_operacion' => 'integer', 'fecha' => 'date:Y-m-d',
        'monto' => 'decimal:8', 'tasa_cambio' => 'decimal:6', 'monto_cuenta' => 'decimal:8',
        'saldo_anterior' => 'decimal:8', 'saldo_posterior' => 'decimal:8',
        'fondos_utilizados' => 'decimal:8', 'fondos_incrementados' => 'decimal:8',
        'deuda_generada' => 'decimal:8', 'deuda_cancelada' => 'decimal:8',
        'id_movimiento_generado' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer',
    ];

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(CuentaFinanciera::class, 'id_cuenta');
    }

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(OperacionFinanciera::class, 'id_operacion');
    }

    public function movimiento_generado(): BelongsTo
    {
        return $this->belongsTo(MovimientoCuenta::class, 'id_movimiento_generado');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoFinanciero::class, 'id_documento');
    }

    public function usuario_registro(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_registro');
    }
}
