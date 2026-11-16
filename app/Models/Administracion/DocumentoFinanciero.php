<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class DocumentoFinanciero extends Model
{
    protected $table = 'documentos_financieros';

    protected $fillable = ['nombre', 'referencia', 'ubicacion', 'sha256', 'tipo', 'fecha_documento', 'observacion', 'estado', 'id_usuario_registro'];

    protected $casts = ['fecha_documento' => 'date:Y-m-d', 'id_usuario_registro' => 'integer'];

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
