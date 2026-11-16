<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class MovimientoObra extends Model
{
    protected $table = 'movimientos_obra';

    protected $fillable = ['id_proyecto', 'fecha', 'tipo', 'partida', 'concepto', 'moneda', 'monto', 'cantidad', 'precio_unitario', 'id_contraparte', 'id_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_proyecto' => 'integer', 'fecha' => 'date:Y-m-d', 'monto' => 'decimal:8', 'cantidad' => 'decimal:6', 'precio_unitario' => 'decimal:8', 'id_contraparte' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function proyecto(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ProyectoObra::class, 'id_proyecto');
    }

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ContraparteFinanciera::class, 'id_contraparte');
    }

    public function documento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\DocumentoFinanciero::class, 'id_documento');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
