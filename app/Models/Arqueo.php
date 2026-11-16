<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Arqueo extends Model
{
    protected $table = 'arqueo';

    protected $fillable = [
        'id_usuario',
        'id_subagencia',
        'id_caja',
        'id_arqueo_anterior',
        'fecha',
        'estado',
        'abierto_at',
        'cerrado_at',
        'cheques_confirmados_at',
        'id_usuario_cierre',
        'caja_inicial_cordobas',
        'caja_inicial_dolares',
        'cheques_inicial_cordobas',
        'cheques_inicial_dolares',
        'tasa_referencia',
        'motivo_apertura_manual',
        'biller_cordobas',
        'biller_dolares',
        'envios_cordobas',
        'envios_dolares',
        'pagos_cordobas',
        'pagos_dolares',
        'caja_final_cordobas',
        'caja_final_dolares',
        'saldo_esperado_cordobas',
        'saldo_esperado_dolares',
        'diferencia_cordobas',
        'diferencia_dolares',
        'cantidad_cheques',
        'monto_total_cheques',
        'monto_total_cheques_dolares',
        'ganancia_total_cheques',
        'ganancia_total_cheques_dolares',
        'observacion',
    ];

    protected $casts = [
        'id_usuario' => 'integer',
        'id_subagencia' => 'integer',
        'id_caja' => 'integer',
        'id_arqueo_anterior' => 'integer',
        'fecha' => 'date:Y-m-d',
        'abierto_at' => 'datetime:Y-m-d H:i:s',
        'cerrado_at' => 'datetime:Y-m-d H:i:s',
        'cheques_confirmados_at' => 'datetime:Y-m-d H:i:s',
        'id_usuario_cierre' => 'integer',
        'caja_inicial_cordobas' => 'decimal:2',
        'caja_inicial_dolares' => 'decimal:2',
        'cheques_inicial_cordobas' => 'decimal:2',
        'cheques_inicial_dolares' => 'decimal:2',
        'tasa_referencia' => 'decimal:6',
        'biller_cordobas' => 'decimal:2',
        'biller_dolares' => 'decimal:2',
        'envios_cordobas' => 'decimal:2',
        'envios_dolares' => 'decimal:2',
        'pagos_cordobas' => 'decimal:2',
        'pagos_dolares' => 'decimal:2',
        'caja_final_cordobas' => 'decimal:2',
        'caja_final_dolares' => 'decimal:2',
        'saldo_esperado_cordobas' => 'decimal:8',
        'saldo_esperado_dolares' => 'decimal:8',
        'diferencia_cordobas' => 'decimal:8',
        'diferencia_dolares' => 'decimal:8',
        'cantidad_cheques' => 'integer',
        'monto_total_cheques' => 'decimal:2',
        'monto_total_cheques_dolares' => 'decimal:2',
        'ganancia_total_cheques' => 'decimal:2',
        'ganancia_total_cheques_dolares' => 'decimal:2',
    ];

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
    public function subagencia(): BelongsTo
    {
        return $this->belongsTo(SubAgencia::class, 'id_subagencia');
    }
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class, 'id_caja');
    }
    public function anterior(): BelongsTo
    {
        return $this->belongsTo(Arqueo::class, 'id_arqueo_anterior');
    }
    public function detalle_arqueo(): HasMany
    {
        return $this->hasMany(Detalle_De_Arqueo::class, 'id_arqueo');
    }
    public function cheques(): HasMany
    {
        return $this->hasMany(Cheques::class, 'id_arqueo');
    }
    public function custodias(): HasMany
    {
        return $this->hasMany(ArqueoCheque::class, 'id_arqueo');
    }
    public function pases(): HasMany
    {
        return $this->hasMany(Pases::class, 'id_arqueo');
    }
    public function entradas_y_salidas(): HasMany
    {
        return $this->hasMany(Entradas_Y_Salidas::class, 'id_arqueo');
    }
    public function tasas(): HasMany
    {
        return $this->hasMany(TasaCambioArqueo::class, 'id_arqueo');
    }
    public function operaciones_cambio(): HasMany
    {
        return $this->hasMany(OperacionCambioCaja::class, 'id_arqueo');
    }
    public function remesas(): HasMany
    {
        return $this->hasMany(RemesaBancaria::class, 'id_arqueo');
    }
    public function envios_dhl(): HasMany
    {
        return $this->hasMany(EnvioDhl::class, 'id_arqueo');
    }
    public function enrolamientos(): HasMany
    {
        return $this->hasMany(Enrolamiento::class, 'id_arqueo');
    }
    public function conteos_servicios(): HasMany
    {
        return $this->hasMany(ConteoServicioDiario::class, 'id_arqueo');
    }
    public function papeleria(): HasMany
    {
        return $this->hasMany(ConteoPapeleria::class, 'id_arqueo');
    }
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialCaja::class, 'id_arqueo');
    }
    public function factura_medicamento(): HasMany
    {
        return $this->hasMany(Factura_Medicamento::class, 'id_arqueo');
    }
}
