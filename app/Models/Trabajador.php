<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

class Trabajador extends Model
{
    use HasFactory;
    protected $table = 'trabajadores';

    protected $fillable = [
        'nombre',
        'cedula',
        'fecha_nacimiento',
        'inss',
        'nombre_banco',
        'numero_cuenta_banco',
        'estadotrabajador',
        'fecha_contratacion',
        'id_subagencia',
        'cargo',
        'salario_basico_inicial',
        'salario_basico_actual',
        'fecha_finalizacion',
        'concepto_finalizacion',
    ];



    public function users(): HasOne
    {
        return $this->hasOne(User::class, 'id_trabajador', 'id');
    }

    public function subagencias(): BelongsTo
    {
        return $this->belongsTo(SubAgencia::class);
    }
}
