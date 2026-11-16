<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibroDiario extends Model
{
    protected $table = 'libros_diarios';

    protected $fillable = ['fecha', 'version', 'id_anterior', 'id_reporte_remesas', 'usuarios_esperados', 'motivo_apertura',
        'observacion', 'estado', 'resultado', 'huella', 'cerrado_at', 'id_usuario_cierre', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'version' => 'integer', 'id_anterior' => 'integer', 'id_reporte_remesas' => 'integer',
        'usuarios_esperados' => 'array', 'resultado' => 'array', 'cerrado_at' => 'datetime:Y-m-d H:i:s'];

    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_anterior');
    }

    public function reporte_remesas(): BelongsTo
    {
        return $this->belongsTo(ReporteRemesaCentral::class, 'id_reporte_remesas');
    }

    public function fuentes(): HasMany
    {
        return $this->hasMany(FuenteLibroDiario::class, 'id_libro_diario');
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(CuentaLibroDiario::class, 'id_libro_diario');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaLibroDiario::class, 'id_libro_diario');
    }

    public function detalles_cuenta(): HasMany
    {
        return $this->hasMany(DetalleCuentaDiario::class, 'id_libro_diario');
    }

    public function revisiones(): HasMany
    {
        return $this->hasMany(RevisionLibroDiario::class, 'id_libro_diario');
    }
}
