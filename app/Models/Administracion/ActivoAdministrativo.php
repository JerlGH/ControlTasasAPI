<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ActivoAdministrativo extends Model
{
    protected $table = 'activos_administrativos';

    protected $fillable = ['codigo', 'nombre', 'categoria', 'fecha', 'id_subagencia', 'id_contraparte', 'moneda', 'cantidad', 'precio_unitario', 'monto', 'id_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'id_subagencia' => 'integer', 'id_contraparte' => 'integer', 'cantidad' => 'decimal:6', 'precio_unitario' => 'decimal:8', 'monto' => 'decimal:8', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
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
