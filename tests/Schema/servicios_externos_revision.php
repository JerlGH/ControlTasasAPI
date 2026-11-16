<?php

// Casos adversos aislados. Solo SQLite :memory:; no usa .env ni datos reales.
require __DIR__.'/servicios_externos_api.php';

use App\Models\Administracion\CuentaFinanciera;
use App\Services\Administracion\ServicioExternoService;

$startExternalReview = $checks;
$externalReviewFailures = [];
function revisarExterno(string $name, callable $case): void
{
    global $db, $externalReviewFailures;
    $db->beginTransaction();
    try {
        $case();
    } catch (Throwable $e) {
        $externalReviewFailures[] = $name.': '.$e->getMessage();
    } finally {
        $db->rollBack();
    }
}

function diaRevisionExterna(bool $bothBanks = true): array
{
    global $agencies;
    $root = nuevoExterno('arqueosserviciosexternos', ['fecha' => '2026-11-20', 'agencias_esperadas' => [$agencies[0]]]);
    if ($bothBanks) {
        filaExterna($root['id'], 'LAFISE-REV', ['banco' => 'lafise', 'servicio' => 'remesa']);
        filaExterna($root['id'], 'BANPRO-REV', ['banco' => 'banpro', 'servicio' => 'remesa_local']);
        cerrarExterno($root['id']);
    }

    return $root;
}

revisarExterno('Cuenta excluida no puede ocultar el devengo en el capital', function () {
    global $mappings;
    $root = diaRevisionExterna();
    $provider = CuentaFinanciera::findOrFail($mappings[0]['id_cuenta'])->id_contraparte;
    $excluded = nuevoExterno('cuentas', ['codigo' => 'REV-EXCLUIDA', 'nombre' => 'Prueba excluida', 'tipo' => 'por_cobrar',
        'moneda' => 'NIO', 'rubro_capital' => 'excluido', 'fecha_apertura' => '2026-01-01', 'saldo_inicial' => '0', 'id_contraparte' => $provider]);
    externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/registrarcomisiones', [
        'fecha' => '2026-11-20', 'cuentas' => [['banco' => 'lafise', 'id_cuenta' => $excluded['id']]]], 422);
});

revisarExterno('Proveedores separados tambien en solicitudes sucesivas', function () {
    global $mappings;
    $root = diaRevisionExterna();
    externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/registrarcomisiones', [
        'fecha' => '2026-11-20', 'cuentas' => [['banco' => 'lafise', 'id_cuenta' => $mappings[0]['id_cuenta']]]]);
    externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/registrarcomisiones', [
        'fecha' => '2026-11-20', 'cuentas' => [['banco' => 'banpro', 'id_cuenta' => $mappings[0]['id_cuenta']]]], 422);
});

revisarExterno('No publicar un total que supera DECIMAL(24,8)', function () {
    global $db;
    $root = diaRevisionExterna(false);
    foreach (['GRANDE-1', 'GRANDE-2'] as $key) {
        filaExterna($root['id'], $key, ['banco' => 'lafise', 'servicio' => 'remesa', 'monto' => '9000000000000000']);
    }
    cerrarExterno($root['id']);
    $before = $db->table('reportes_remesa_central')->count();
    externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/generarreporte', ['corte_at' => '2026-11-20 18:00:00'], 422);
    expectApi($db->table('reportes_remesa_central')->count() === $before, 'No debe quedar reporte parcial tras rechazar el importe');
});

revisarExterno('Una fecha ausente se distingue del cero declarado y del borrador', function () {
    $period = externo('GET', 'serviciosexternos/resumen?fecha_desde=2026-11-15&fecha_hasta=2026-11-17')['Resultado'];
    expectApi(isset($period['fechas_sin_registro']) && $period['fechas_sin_registro'] === ['2026-11-17'],
        'El periodo debe identificar la fecha 17 ausente sin confundirla con el borrador del 16');
});

revisarExterno('El capital detecta una comision previamente reconocida fuera del rubro correcto', function () {
    global $mappings, $id;
    CuentaFinanciera::whereKey($mappings[1]['id_cuenta'])->update(['rubro_capital' => 'excluido']);
    $coverage = ServicioExternoService::financialCoverage('2026-11-15');
    expectApi(collect($coverage['pendientes'])->contains(fn ($p) => $p['tipo'] === 'comision_externa_inconsistente'
        && $p['id_arqueo_externo'] === $id && $p['banco'] === 'banpro'),
        'El control debe senalar la comision reconocida en cuenta excluida, no darla por resuelta');
});

revisarExterno('El corte se recibe con la misma precision con la que se conserva', function () {
    $root = diaRevisionExterna();
    externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/generarreporte', ['corte_at' => '2026-11-20T18:00:00.123456Z'], 422);
    $report = externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/generarreporte', ['corte_at' => '2026-11-20 18:00:00'])['Registro'];
    $repeat = externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/generarreporte', ['corte_at' => '2026-11-20 18:00:00'])['Registro'];
    expectApi($report['id'] === $repeat['id'], 'Un corte en el formato admitido se puede reintentar sin crear otro reporte');
});

revisarExterno('El reconocimiento de dos bancos es atomico ante una cuenta incorrecta', function () {
    global $mappings, $db;
    $root = diaRevisionExterna();
    $provider = CuentaFinanciera::findOrFail($mappings[1]['id_cuenta'])->id_contraparte;
    $excluded = nuevoExterno('cuentas', ['codigo' => 'REV-SEGUNDA-EXCLUIDA', 'nombre' => 'Prueba excluida', 'tipo' => 'por_cobrar',
        'moneda' => 'NIO', 'rubro_capital' => 'excluido', 'fecha_apertura' => '2026-01-01', 'saldo_inicial' => '0', 'id_contraparte' => $provider]);
    $before = $db->table('comisiones_administrativas')->count();
    $entries = $db->table('movimientos_cuenta_financiera')->count();
    externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/registrarcomisiones', ['fecha' => '2026-11-20', 'cuentas' => [
        ['banco' => 'lafise', 'id_cuenta' => $mappings[0]['id_cuenta']], ['banco' => 'banpro', 'id_cuenta' => $excluded['id']]]], 422);
    expectApi($db->table('comisiones_administrativas')->count() === $before
        && $db->table('movimientos_cuenta_financiera')->count() === $entries, 'El fallo del segundo banco no deja un devengo parcial del primero');
});

revisarExterno('El calendario ausente respeta anos bisiestos y el limite inclusivo de 366 dias', function () {
    $period = externo('GET', 'serviciosexternos/resumen?fecha_desde=2024-01-01&fecha_hasta=2024-12-31')['Resultado'];
    expectApi(count($period['fechas_sin_registro']) === 366 && in_array('2024-02-29', $period['fechas_sin_registro'], true)
        && $period['dias'] === [] && $period['borradores'] === [], 'El calendario devuelve todas las fechas ausentes sin inventar registros');
    externo('GET', 'serviciosexternos/resumen?fecha_desde=2024-01-01&fecha_hasta=2025-01-01', [], 422);
});

revisarExterno('Una mezcla de proveedores creada con el contrato anterior se senala al calcular capital', function () {
    global $mappings, $db;
    $root = diaRevisionExterna();
    externo('POST', 'arqueosserviciosexternos/'.$root['id'].'/registrarcomisiones', [
        'fecha' => '2026-11-20', 'cuentas' => [['banco' => 'lafise', 'id_cuenta' => $mappings[0]['id_cuenta']]]]);
    // Representa el dato que la version anterior permitia generar para el otro banco.
    $legacy = nuevoExterno('comisiones', ['fecha' => '2026-11-20', 'periodo_desde' => '2026-11-20', 'periodo_hasta' => '2026-11-20',
        'tipo' => 'proveedor', 'naturaleza' => 'cobrar', 'id_cuenta' => $mappings[0]['id_cuenta'], 'moneda' => 'NIO', 'monto' => '12']);
    $db->table('comisiones_administrativas')->where('id', $legacy['id'])->update([
        'id_arqueo_externo' => $root['id'], 'banco_externo' => 'banpro', 'clave_externa_activa' => $root['id'].':banpro']);
    $coverage = ServicioExternoService::financialCoverage('2026-11-20');
    expectApi(collect($coverage['pendientes'])->contains(fn ($p) => $p['tipo'] === 'comision_externa_inconsistente'
        && $p['id_arqueo_externo'] === $root['id'] && $p['banco'] === 'banpro'), 'No dar por correcta la mezcla previa por el mero hecho de existir dos devengos');
});

if ($externalReviewFailures !== []) {
    throw new RuntimeException("Revision de servicios externos:\n".implode("\n", $externalReviewFailures));
}
echo 'OK segunda revision servicios externos: '.($checks - $startExternalReview)." comprobaciones nuevas; escenarios adversos aislados.\n";
