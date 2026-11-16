<?php

namespace App\Support\Administracion;

final class RecursosLibroDiario
{
    public const DEFINICIONES = [
        'librosdiarios' => ['model' => 'LibroDiario', 'rules' => [
            'fecha' => 'required|date_format:Y-m-d', 'usuarios_esperados' => 'required|array|min:1',
            'usuarios_esperados.*' => 'required|integer|distinct|min:1|exists:users,id',
            'id_reporte_remesas' => 'nullable|integer|exists:reportes_remesa_central,id',
            'motivo_apertura' => 'nullable|string|max:10000', 'observacion' => 'nullable|string|max:10000']],
        'reportesremesascentrales' => ['model' => 'ReporteRemesaCentral', 'rules' => [
            'fecha' => 'required|date_format:Y-m-d', 'corte_at' => 'required|date', 'referencia' => 'required|string|max:100',
            'fuente' => 'required|string|max:255', 'agencias_esperadas' => 'required|array|min:1',
            'agencias_esperadas.*' => 'required|integer|distinct|min:1|exists:subagencias,id',
            'id_documento' => 'nullable|integer|exists:documentos_financieros,id', 'observacion' => 'nullable|string|max:10000']],
        'detallesremesascentrales' => ['model' => 'DetalleRemesaCentral', 'rules' => [
            'id_reporte' => 'required|integer|exists:reportes_remesa_central,id', 'id_subagencia' => 'required|integer|exists:subagencias,id',
            'banco' => 'required|in:lafise,banpro', 'moneda' => 'required|in:NIO,USD', 'tipo' => 'required|in:Entrada,Salida',
            'monto' => ['required', 'numeric', 'regex:/^\d{1,16}(?:\.\d{1,8})?$/D', 'min:0'], 'observacion' => 'nullable|string|max:10000']],
        'cuentasdiario' => ['model' => 'CuentaLibroDiario', 'rules' => [
            'id_libro_diario' => 'required|integer|exists:libros_diarios,id', 'id_cuenta' => 'required|integer|exists:cuentas_financieras,id',
            'canal' => 'required|in:bancentro,lafise,banpro']],
        'partidasdiario' => ['model' => 'PartidaLibroDiario', 'rules' => [
            'id_libro_diario' => 'required|integer|exists:libros_diarios,id', 'id_operacion' => 'required|integer|exists:operaciones_financieras,id',
            'bloque' => 'required|in:banco_cajeros,cambio_tesoreria,adicional,gasto,cuenta_particular,remesa_pos,efectivo_airpak',
            'id_contraparte' => 'nullable|integer|exists:contrapartes_financieras,id', 'id_documento' => 'nullable|integer|exists:documentos_financieros,id',
            'receptor' => 'nullable|string|max:255', 'referencia' => 'nullable|string|max:100', 'observacion' => 'nullable|string|max:10000']],
        'detallescuentasdiario' => ['model' => 'DetalleCuentaDiario', 'rules' => [
            'id_libro_diario' => 'required|integer|exists:libros_diarios,id', 'id_entrada_salida' => 'required|integer|exists:entradas_y_salidas,id',
            'id_cuenta_auxiliar' => 'nullable|integer|exists:cuentas_financieras,id', 'id_contraparte' => 'nullable|integer|exists:contrapartes_financieras,id',
            'id_operacion' => 'nullable|integer|exists:operaciones_financieras,id', 'id_documento' => 'nullable|integer|exists:documentos_financieros,id',
            'observacion' => 'nullable|string|max:10000']],
        'revisionesdiario' => ['model' => 'RevisionLibroDiario', 'rules' => [
            'id_libro_diario' => 'required|integer|exists:libros_diarios,id', 'clave_control' => 'required|string|max:150',
            'huella' => 'required|string|regex:/^[a-f0-9]{64}$/D', 'resultado' => 'required|in:revisado,observado,justificado',
            'detalle' => 'required|string|max:10000', 'id_documento' => 'nullable|integer|exists:documentos_financieros,id', 'referencia' => 'nullable|string|max:100']],
    ];

    public static function model(string $resource): string
    {
        abort_unless(isset(self::DEFINICIONES[$resource]), 404);

        return 'App\\Models\\Administracion\\'.self::DEFINICIONES[$resource]['model'];
    }
}
