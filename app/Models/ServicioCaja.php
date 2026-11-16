<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicioCaja extends Model
{
    protected $table = 'servicios_caja';

    protected $fillable = [
        'codigo',
        'nombre',
        'categoria',
        'estado',
    ];

    protected $casts = [

    ];

    public function conteos(): HasMany
    {
        return $this->hasMany(ConteoServicioDiario::class, 'id_servicio');
    }
    public function enrolamientos(): HasMany
    {
        return $this->hasMany(Enrolamiento::class, 'id_servicio');
    }
    public function papeleria(): HasMany
    {
        return $this->hasMany(ConteoPapeleria::class, 'id_servicio');
    }
}
