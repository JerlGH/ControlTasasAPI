<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ArqueoServicioExterno extends Model
{
    protected $table = 'arqueos_servicios_externos';

    protected $fillable = ['fecha', 'terminal', 'clave_activa', 'agencias_esperadas', 'id_responsable',
        'tarifario', 'resumen', 'id_documento', 'estado', 'cerrado_at', 'observacion', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'agencias_esperadas' => 'array', 'id_responsable' => 'integer',
        'tarifario' => 'array', 'resumen' => 'array', 'id_documento' => 'integer',
        'cerrado_at' => 'datetime:Y-m-d H:i:s', 'id_usuario_registro' => 'integer'];

    public function transacciones(): HasMany
    {
        return $this->hasMany(TransaccionServicioExterno::class, 'id_arqueo_externo');
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_responsable');
    }

    public function reporte_remesas(): HasOne
    {
        return $this->hasOne(ReporteRemesaCentral::class, 'id_arqueo_externo');
    }

    public function comisiones(): HasMany
    {
        return $this->hasMany(ComisionAdministrativa::class, 'id_arqueo_externo');
    }
}
