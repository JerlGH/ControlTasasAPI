<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Arqueo extends Model
{
    protected $table='arqueo';
    protected $fillable = [
        'id_usuario',
        'estado',
        'fecha',
        'caja_inicial_cordobas',
        'caja_final_cordobas',
        'caja_inicial_dolares',
        'caja_final_dolares',
        'biller_cordobas',
        'biller_dolares',
        'envios_cordobas',
        'envios_dolares',
        'pagos_cordobas',
        'pagos_dolares',
        'cantidad_cheques',
        'monto_total_cheques',
        'ganancia_total_cheques',
        'diferencia_cordobas',
        'diferencia_dolares'
    ];


    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(User::class,'id_usuario','id');
    }

    public function detalle_arqueo(): HasMany
    {
        return $this->hasMany(Detalle_De_Arqueo::class,'id_arqueo','id');
    }

    public function cheques(): HasMany
    {
        return $this->hasMany(Cheques::class,'id_arqueo','id');
    }

    public function factura_medicamento(): HasMany
    {
        return $this->hasMany(Factura_Medicamento::class,'id_arqueo','id');
    }

    public function entradas_y_salidas(): HasMany
    {
        return $this->hasMany(Entradas_Y_Salidas::class,'id_arqueo','id');
    }
}
