<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class DetalleCuentaDiario extends Model
{
    protected $table = 'detalles_cuenta_diario';

    protected $fillable = ['id_libro_diario', 'id_entrada_salida', 'id_cuenta_auxiliar', 'id_contraparte', 'id_operacion', 'id_documento', 'observacion', 'clave_activa', 'estado', 'id_usuario_registro'];

    protected $casts = ['id_libro_diario' => 'integer', 'id_entrada_salida' => 'integer', 'id_cuenta_auxiliar' => 'integer', 'id_contraparte' => 'integer', 'id_operacion' => 'integer'];

    public function libro_diario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LibroDiario::class, 'id_libro_diario');
    }

    public function entrada_salida(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Entradas_Y_Salidas::class, 'id_entrada_salida');
    }

    public function cuenta_auxiliar(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CuentaFinanciera::class, 'id_cuenta_auxiliar');
    }

    public function contraparte(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContraparteFinanciera::class, 'id_contraparte');
    }

    public function documento(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(DocumentoFinanciero::class, 'id_documento');
    }

    public function operacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OperacionFinanciera::class, 'id_operacion');
    }
}
