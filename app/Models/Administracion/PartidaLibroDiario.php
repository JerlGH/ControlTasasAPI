<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class PartidaLibroDiario extends Model
{
    protected $table = 'partidas_libro_diario';

    protected $fillable = ['id_libro_diario', 'id_operacion', 'bloque', 'id_contraparte', 'id_documento', 'receptor', 'referencia', 'observacion', 'clave_activa', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_libro_diario' => 'integer', 'id_operacion' => 'integer'];

    public function libro_diario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LibroDiario::class, 'id_libro_diario');
    }

    public function operacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OperacionFinanciera::class, 'id_operacion');
    }

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContraparteFinanciera::class, 'id_contraparte');
    }

    public function documento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentoFinanciero::class, 'id_documento');
    }
}
