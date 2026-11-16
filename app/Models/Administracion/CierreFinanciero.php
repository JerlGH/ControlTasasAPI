<?php

namespace App\Models\Administracion;

use Illuminate\Database\Eloquent\Model;

class CierreFinanciero extends Model
{
    protected $table = 'cierres_financieros';

    protected $fillable = ['fecha', 'periodo_desde', 'id_arqueo_madre', 'id_liquidacion', 'cuentas_capital_trabajo', 'datos', 'huella', 'cerrado_at', 'motivo', 'estado', 'id_usuario_registro'];

    protected $casts = ['fecha' => 'date:Y-m-d', 'periodo_desde' => 'date:Y-m-d', 'id_arqueo_madre' => 'integer', 'id_liquidacion' => 'integer', 'cuentas_capital_trabajo' => 'array', 'datos' => 'array', 'cerrado_at' => 'datetime:Y-m-d H:i:s', 'id_usuario_registro' => 'integer'];

    public function arqueo_madre(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\ArqueoMadre::class, 'id_arqueo_madre');
    }

    public function liquidacion(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Administracion\LiquidacionMatriz::class, 'id_liquidacion');
    }

    public function usuario_registro(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'id_usuario_registro');
    }
}
