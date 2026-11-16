<?php

namespace App\Support\Administracion;

final class RecursosServiciosExternos
{
    public const DEFINICIONES = [
        'arqueosserviciosexternos' => ['model' => 'ArqueoServicioExterno', 'rules' => [
            'fecha' => 'required|date_format:Y-m-d', 'agencias_esperadas' => 'required|array|min:1',
            'agencias_esperadas.*' => 'required|integer|min:1|distinct|exists:subagencias,id',
            'id_responsable' => 'nullable|integer|min:1|exists:users,id',
            'id_documento' => 'nullable|integer|min:1|exists:documentos_financieros,id',
            'observacion' => 'nullable|string|max:10000']],
        'transaccionesexternas' => ['model' => 'TransaccionServicioExterno', 'rules' => [
            'id_arqueo_externo' => 'required|integer|min:1|exists:arqueos_servicios_externos,id',
            'clave' => 'required|string|max:100', 'id_subagencia' => 'required|integer|min:1|exists:subagencias,id',
            'id_cajero' => 'nullable|integer|min:1|exists:users,id', 'nombre_cajero' => 'nullable|string|max:255',
            'banco' => 'required|in:lafise,banpro',
            'servicio' => 'required|in:sin_clasificar,sin_comision,transaccion_cuenta,remesa,recepcion_empresa,servicio_publico,remesa_local,remesa_extranjera',
            'motivo_sin_comision' => 'nullable|string|max:10000',
            'tipo' => 'required|in:Entrada,Salida', 'moneda' => 'required|in:NIO,USD',
            'numero_referencia' => 'required|string|max:100',
            'monto' => ['required', 'numeric', 'regex:/^\d{1,16}(?:\.\d{1,8})?$/D', 'gt:0'],
            'monto_remesa_usd' => ['nullable', 'numeric', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D', 'gt:0'],
            'id_remesa_caja' => 'nullable|integer|min:1|exists:remesas_bancarias,id',
            'id_documento' => 'nullable|integer|min:1|exists:documentos_financieros,id',
            'observacion' => 'nullable|string|max:10000']],
    ];

    public static function model(string $resource): string
    {
        abort_unless(isset(self::DEFINICIONES[$resource]), 404);

        return 'App\\Models\\Administracion\\'.self::DEFINICIONES[$resource]['model'];
    }
}
