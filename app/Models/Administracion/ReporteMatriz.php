<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class ReporteMatriz extends Model
{
    protected $table = 'reportes_matriz';

    protected $fillable = ['fecha', 'corte_at', 'proveedor', 'referencia', 'id_subagencia', 'id_documento', 'estado', 'observacion', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'corte_at' => 'datetime:Y-m-d H:i:s', 'id_subagencia' => 'integer', 'id_documento' => 'integer', 'id_usuario_registro' => 'integer'];

    public function subagencia(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SubAgencia::class, 'id_subagencia');
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
