<?php

// Rutas/controladores reales, SQLite :memory:. No carga .env ni utiliza datos reales.
require __DIR__.'/caja_api.php';

use App\Models\Administracion\ArqueoServicioExterno;
use App\Support\Administracion\Finanzas as F;

$startExternalChecks = $checks;
$externalProtected = protectedApiSnapshot();
function externo(string $method, string $uri, array $data = [], int $expected = 200): array
{
    global $admin;

    return api($method, 'administracion/'.$uri, $data, $admin, $expected);
}
function nuevoExterno(string $uri, array $data): array
{
    return externo('POST', $uri, $data, 201)['Registro'];
}
function resumenExterno(int $id): array
{
    return externo('GET', 'arqueosserviciosexternos/'.$id.'/resumen')['Resultado'];
}
function filaExterna(int $id, string $key, array $data = []): array
{
    global $agencies;

    return nuevoExterno('transaccionesexternas', array_replace(['id_arqueo_externo' => $id, 'clave' => $key,
        'id_subagencia' => $agencies[0], 'nombre_cajero' => 'Referencia de la terminal', 'banco' => 'banpro',
        'servicio' => 'transaccion_cuenta', 'tipo' => 'Entrada', 'moneda' => 'NIO',
        'numero_referencia' => 'CUENTA-REPETIBLE', 'monto' => '100'], $data));
}
function cerrarExterno(int $id): array
{
    return externo('POST', 'arqueosserviciosexternos/'.$id.'/cerrar', [
        'huella' => resumenExterno($id)['huella'], 'confirmar_registros' => true])['Resultado'];
}

foreach (['arqueosserviciosexternos', 'transaccionesexternas', 'serviciosexternos/catalogo'] as $uri) {
    externo('GET', $uri);
    api('GET', 'administracion/'.$uri, [], $cashier, 403);
    api('GET', 'administracion/'.$uri, [], null, 401);
}
$inactiveExternalAdmin = clone $admin;
$inactiveExternalAdmin->estado_usuario = 'Inactivo';
api('GET', 'administracion/serviciosexternos/catalogo', [], $inactiveExternalAdmin, 403);
$tariff = externo('GET', 'serviciosexternos/catalogo')['Resultado'];
expectApi($tariff['alcance_tramo'] === 'terminal_dia' && $tariff['moneda_ganancia'] === 'NIO', 'El tarifario pertenece a una unica terminal administrativa');

$root = nuevoExterno('arqueosserviciosexternos', ['fecha' => '2026-11-15', 'agencias_esperadas' => $agencies]);
$id = $root['id'];
expectApi($root['terminal'] === 'principal' && $root['id_responsable'] === $admin->id, 'El responsable se obtiene de la sesion administrativa');
externo('POST', 'arqueosserviciosexternos', ['fecha' => '2026-11-15', 'agencias_esperadas' => $agencies], 409);
externo('PATCH', 'arqueosserviciosexternos/'.$id, ['fecha' => '2026-11-16'], 422);
externo('PATCH', 'arqueosserviciosexternos/'.$id, ['id_responsable' => $cashier->id], 422);
externo('PATCH', 'arqueosserviciosexternos/'.$id, ['tarifario' => []], 422);
externo('PATCH', 'arqueosserviciosexternos/'.$id, ['terminal' => 'otra'], 422);
$zero = resumenExterno($id);
expectApi($zero['totales']['cantidad'] === 0 && $zero['totales']['ganancia_nio'] === '0.00000000', 'El dia vacio declarado conserva cero y ocho lineas por agencia');
expectApi(count($zero['lineas_reporte']) === 8 * count($agencies), 'Las combinaciones sin movimiento se declaran explicitamente');

$boundaries = [1 => '5.00000000', 10 => '5.00000000', 11 => '5.50000000', 20 => '5.50000000',
    21 => '6.00000000', 30 => '6.00000000', 31 => '7.00000000'];
for ($n = 1; $n <= 31; $n++) {
    $row = filaExterna($id, 'TRX-'.$n, ['id_subagencia' => $agencies[$n % 2], 'tipo' => $n % 2 ? 'Entrada' : 'Salida',
        'moneda' => $n % 3 ? 'NIO' : 'USD']);
    if (isset($boundaries[$n])) {
        $summary = resumenExterno($id);
        expectApi($summary['transacciones_cuenta_banpro'] === $n && $summary['tarifa_transacciones_banpro_nio'] === $boundaries[$n],
            'El rango diario incluye ambas agencias, direcciones y monedas: '.$n);
        expectApi($summary['totales']['ganancia_nio'] === F::mul($n, $boundaries[$n]), 'La tarifa del tramo se aplica a todas las transacciones del dia: '.$n);
    }
}
$lastRow = $row;
externo('POST', 'transaccionesexternas', array_replace($lastRow, ['id' => null]), 422);
externo('POST', 'transaccionesexternas', ['id_arqueo_externo' => $id, 'clave' => 'TRX-31', 'id_subagencia' => $agencies[0],
    'nombre_cajero' => 'Central', 'banco' => 'banpro', 'servicio' => 'transaccion_cuenta', 'tipo' => 'Entrada', 'moneda' => 'NIO',
    'numero_referencia' => 'REPETIDA', 'monto' => '100'], 409);
externo('PATCH', 'transaccionesexternas/'.$lastRow['id'], ['ganancia_nio' => '999'], 422);
externo('DELETE', 'transaccionesexternas/'.$lastRow['id'], ['motivo' => 'Correccion antes del cierre']);
expectApi(resumenExterno($id)['totales']['ganancia_nio'] === '180.00000000', 'Anular la 31 recalcula las 30 restantes al tramo de C$6');
filaExterna($id, 'TRX-REEMPLAZO');

$expectedFees = '217';
foreach ([['lafise', 'transaccion_cuenta', '7'], ['lafise', 'remesa', '20'], ['banpro', 'recepcion_empresa', '5'],
    ['banpro', 'servicio_publico', '4'], ['banpro', 'remesa_local', '12']] as [$bank, $service, $fee]) {
    filaExterna($id, $bank.'-'.$service, ['banco' => $bank, 'servicio' => $service]);
    $expectedFees = F::add($expectedFees, $fee);
}
filaExterna($id, 'EXTRANJERA-USD', ['servicio' => 'remesa_extranjera', 'moneda' => 'USD', 'monto' => '2000']);
$expectedFees = F::add($expectedFees, 13);
foreach ([['0.01', '13'], ['29.99', '13'], ['30', '24'], ['100.99', '24'], ['101', '28'],
    ['200.99', '28'], ['201', '33'], ['400.99', '33'], ['401', '38'], ['1500', '38']] as [$base, $fee]) {
    $foreign = filaExterna($id, 'BASE-'.$base, ['servicio' => 'remesa_extranjera', 'monto_remesa_usd' => $base]);
    $calculated = collect(resumenExterno($id)['transacciones'])->firstWhere('id', $foreign['id']);
    expectApi($calculated['ganancia_nio'] === F::amount($fee), 'Tarifa extranjera NIO en el limite USD '.$base);
    $expectedFees = F::add($expectedFees, $fee);
}
expectApi(resumenExterno($id)['totales']['ganancia_nio'] === $expectedFees, 'Todas las ganancias son NIO y no se multiplican por el principal');
expectApi(resumenExterno($id)['transacciones_cuenta_banpro'] === 31, 'Las remesas y pagos de servicios no alteran el rango de cuentas');

$missing = filaExterna($id, 'FALTA-BASE', ['servicio' => 'remesa_extranjera']);
$pending = resumenExterno($id);
expectApi(! $pending['completo'] && $pending['totales']['ganancia_nio'] === null
    && $pending['totales']['ganancia_calculada_nio'] === $expectedFees, 'Una base faltante muestra resultado pendiente, sin simular cero');
externo('POST', 'arqueosserviciosexternos/'.$id.'/cerrar', ['confirmar_registros' => true, 'huella' => $pending['huella']], 422);
externo('PATCH', 'transaccionesexternas/'.$missing['id'], ['monto_remesa_usd' => '1500.01'], 422);
externo('PATCH', 'transaccionesexternas/'.$missing['id'], ['monto_remesa_usd' => '29.999'], 422);
externo('PATCH', 'transaccionesexternas/'.$missing['id'], ['monto_remesa_usd' => '0'], 422);
externo('PATCH', 'transaccionesexternas/'.$missing['id'], ['monto_remesa_usd' => '30']);
$expectedFees = F::add($expectedFees, 24);
externo('POST', 'arqueosserviciosexternos/'.$id.'/cerrar', ['confirmar_registros' => true, 'huella' => $pending['huella']], 422);
$unknown = filaExterna($id, 'POR-CLASIFICAR', ['servicio' => 'sin_clasificar']);
expectApi(! resumenExterno($id)['completo'], 'La hoja original no permite inferir la clase de servicio');
externo('PATCH', 'transaccionesexternas/'.$unknown['id'], ['servicio' => 'remesa'], 422);
externo('PATCH', 'transaccionesexternas/'.$unknown['id'], ['servicio' => 'servicio_publico']);
$expectedFees = F::add($expectedFees, 4);
externo('PATCH', 'transaccionesexternas/'.$unknown['id'], ['monto_remesa_usd' => '30'], 422);
externo('PATCH', 'transaccionesexternas/'.$unknown['id'], ['id_cajero' => $admin->id], 422);
externo('PATCH', 'arqueosserviciosexternos/'.$id, ['agencias_esperadas' => [$agencies[0]]], 422);

$beforeClose = resumenExterno($id);
$operationCount = $db->table('operaciones_financieras')->count();
$closedExternal = cerrarExterno($id);
expectApi($closedExternal === $beforeClose && $closedExternal['totales']['ganancia_nio'] === $expectedFees, 'El cierre congela exactamente el resumen revisado');
expectApi(resumenExterno($id) === $closedExternal, 'La consulta cerrada conserva el snapshot');
expectApi(externo('GET', 'transaccionesexternas/'.$unknown['id'])['Registro']['ganancia_nio'] === '4.00000000', 'El cierre persiste tarifa y ganancia por fila');
externo('PATCH', 'transaccionesexternas/'.$unknown['id'], ['monto' => '999'], 422);
externo('DELETE', 'transaccionesexternas/'.$unknown['id'], ['motivo' => 'Intento de alterar cierre'], 422);
externo('DELETE', 'arqueosserviciosexternos/'.$id, ['motivo' => 'Intento de alterar cierre'], 422);
externo('PATCH', 'arqueosserviciosexternos/'.$id, ['observacion' => 'Alterar'], 422);
externo('POST', 'arqueosserviciosexternos/'.$id.'/cerrar', ['huella' => $closedExternal['huella'], 'confirmar_registros' => true], 422);
expectApi($db->table('operaciones_financieras')->count() === $operationCount, 'Capturar y cerrar servicios no registra dinero otra vez');

$report = externo('POST', 'arqueosserviciosexternos/'.$id.'/generarreporte', ['corte_at' => '2026-11-15 18:00:00'])['Registro'];
expectApi($report['estado'] === 'confirmado' && $report['id_arqueo_externo'] === $id, 'El reporte central conserva el origen y queda confirmado');
expectApi($db->table('detalles_remesa_central')->where('id_reporte', $report['id'])->count() === 16, 'Reporte completo: ocho combinaciones por agencia');
$reportAgain = externo('POST', 'arqueosserviciosexternos/'.$id.'/generarreporte', ['corte_at' => '2026-11-15 18:00:00'])['Registro'];
expectApi($reportAgain['id'] === $report['id'], 'Reintentar no duplica el reporte');
expectApi(externo('GET', 'reportesremesascentrales?id_arqueo_externo='.$id)['Registros'][0]['id'] === $report['id'], 'El reporte emitido se puede recuperar por su origen');
externo('POST', 'arqueosserviciosexternos/'.$id.'/generarreporte', ['corte_at' => '2026-11-15 19:00:00'], 422);
externo('POST', 'arqueosserviciosexternos/'.$id.'/generarreporte', ['corte_at' => '2026-11-16 18:00:00'], 422);
foreach ($closedExternal['lineas_reporte'] as $line) {
    $saved = $db->table('detalles_remesa_central')->where('id_reporte', $report['id'])->where('id_subagencia', $line['id_subagencia'])
        ->where('banco', $line['banco'])->where('moneda', $line['moneda'])->where('tipo', $line['tipo'])->first();
    expectApi(F::cmp($saved->monto, $line['monto']) === 0, 'El reporte conserva importes y perspectiva central/POS');
}
externo('PATCH', 'reportesremesascentrales/'.$report['id'], ['observacion' => 'Alterar'], 422);

$mappings = [];
foreach (['lafise', 'banpro'] as $bank) {
    $counterparty = nuevoExterno('contrapartes', ['codigo' => 'EXTERNO-'.$bank, 'nombre' => strtoupper($bank), 'tipo' => 'empresa']);
    $account = nuevoExterno('cuentas', ['codigo' => 'COMISION-'.$bank, 'nombre' => 'Comisiones '.$bank, 'tipo' => 'por_cobrar',
        'moneda' => 'NIO', 'id_contraparte' => $counterparty['id'], 'rubro_capital' => 'pendiente', 'fecha_apertura' => '2026-01-01', 'saldo_inicial' => '0']);
    $mappings[] = ['banco' => $bank, 'id_cuenta' => $account['id']];
}
$commissionInput = ['fecha' => '2026-11-15', 'cuentas' => $mappings];
externo('POST', 'arqueosserviciosexternos/'.$id.'/registrarcomisiones', ['fecha' => '2026-11-14', 'cuentas' => $mappings], 422);
$commissions = externo('POST', 'arqueosserviciosexternos/'.$id.'/registrarcomisiones', $commissionInput)['Resultado']['comisiones'];
expectApi(count($commissions) === 2 && F::sum($commissions, 'monto') === $expectedFees, 'Reconocimiento crea solo la ganancia calculada de cada proveedor');
foreach ($commissions as $commission) {
    $balance = externo('GET', 'cuentas/'.$commission['id_cuenta'].'/saldo?fecha=2026-11-15')['Resultado'];
    expectApi($balance['saldo'] === $commission['monto'] && $commission['id_arqueo_externo'] === $id, 'La comision genera su cuenta por cobrar con trazabilidad');
}
expectApi($db->table('operaciones_financieras')->count() === $operationCount, 'Reconocer la comision tampoco registra una entrada de efectivo');
$again = externo('POST', 'arqueosserviciosexternos/'.$id.'/registrarcomisiones', $commissionInput)['Resultado']['comisiones'];
expectApi(array_column($again, 'id') === array_column($commissions, 'id'), 'Reintentar no duplica el ingreso devengado');
expectApi(count(externo('GET', 'comisiones?id_arqueo_externo='.$id.'&banco_externo=lafise')['Registros']) === 1, 'El proveedor y origen filtran las comisiones emitidas');
externo('POST', 'arqueosserviciosexternos/'.$id.'/registrarcomisiones', ['fecha' => '2026-11-16', 'cuentas' => $mappings], 422);
externo('PATCH', 'comisiones/'.$commissions[0]['id'], ['monto' => '999'], 422);

$collectionBank = nuevoExterno('cuentas', ['codigo' => 'BANCO-COMISIONES-EXTERNAS', 'nombre' => 'Banco de cobro', 'tipo' => 'banco',
    'moneda' => 'NIO', 'rubro_capital' => 'banco', 'fecha_apertura' => '2026-01-01', 'saldo_inicial' => '0']);
$money = nuevoExterno('operaciones', ['fecha' => '2026-11-15', 'clave' => 'COBRO-COMISION-EXTERNA', 'tipo' => 'ingreso',
    'moneda' => 'NIO', 'monto' => '10', 'concepto' => 'Cobro parcial al proveedor', 'id_cuenta_destino' => $collectionBank['id']]);
nuevoExterno('aplicaciones', ['fecha' => '2026-11-15', 'id_operacion' => $money['id'], 'monto' => '10', 'id_comision' => $commissions[0]['id']]);
expectApi(externo('GET', 'cuentas/'.$commissions[0]['id_cuenta'].'/saldo?fecha=2026-11-15')['Resultado']['saldo'] === '17.00000000', 'El cobro parcial usa las aplicaciones existentes');
externo('DELETE', 'comisiones/'.$commissions[0]['id'], ['motivo' => 'Ya aplicada'], 422);

$draftExternal = nuevoExterno('arqueosserviciosexternos', ['fecha' => '2026-11-16', 'agencias_esperadas' => [$agencies[0]]]);
$draftRow = filaExterna($draftExternal['id'], 'BORRADOR');
$period = externo('GET', 'serviciosexternos/resumen?fecha_desde=2026-11-15&fecha_hasta=2026-11-16')['Resultado'];
expectApi(count($period['dias']) === 1 && count($period['borradores']) === 1 && $period['ganancia_nio'] === $expectedFees, 'El periodo suma cierres diarios e identifica los borradores pendientes');
$listed = externo('GET', 'transaccionesexternas?fecha_desde=2026-11-16&fecha_hasta=2026-11-16')['Registros'];
expectApi(count($listed) === 1 && $listed[0]['id'] === $draftRow['id'], 'La fecha del filtro es la fecha operativa del padre, no created_at');
expectApi(externo('GET', 'transaccionesexternas?id_arqueo_externo='.$draftExternal['id'].'&clave=BORRADOR')['Registros'][0]['id'] === $draftRow['id'],
    'La clave de captura permite recuperar un envio cuando se pierde la respuesta HTTP');
externo('GET', 'transaccionesexternas?no_admitido=1', [], 422);
externo('GET', 'transaccionesexternas?per_page=101', [], 422);
externo('GET', 'serviciosexternos/resumen?fecha_desde=2026-11-16&fecha_hasta=2026-11-15', [], 422);
externo('POST', 'arqueosserviciosexternos/'.$draftExternal['id'].'/generarreporte', ['corte_at' => '2026-11-16 18:00:00'], 422);
externo('DELETE', 'arqueosserviciosexternos/'.$draftExternal['id'], ['motivo' => 'Captura descartada']);
expectApi(externo('GET', 'transaccionesexternas/'.$draftRow['id'])['Registro']['estado'] === 'anulado', 'La anulacion de borrador conserva y anula sus filas');
$replacement = nuevoExterno('arqueosserviciosexternos', ['fecha' => '2026-11-16', 'agencias_esperadas' => [$agencies[0]]]);
expectApi($replacement['id'] !== $draftExternal['id'], 'Un borrador anulado libera la fecha sin eliminar historia');

// Enlace opcional a caja: la captura sigue siendo administrativa e independiente.
$source = $db->table('remesas_bancarias')->where('id_arqueo', $day)->where('banco', 'Lafise')->first();
$linked = nuevoExterno('arqueosserviciosexternos', ['fecha' => '2026-11-04', 'agencias_esperadas' => [$agencies[0]]]);
$linkRow = filaExterna($linked['id'], 'ENLACE', ['banco' => 'lafise', 'servicio' => 'remesa', 'id_cajero' => $cashier->id,
    'tipo' => $source->tipo === 'Entrada' ? 'Salida' : 'Entrada', 'moneda' => $source->moneda, 'monto' => (string) $source->monto,
    'numero_referencia' => $source->numero_remesa, 'id_remesa_caja' => $source->id]);
externo('PATCH', 'transaccionesexternas/'.$linkRow['id'], ['tipo' => $source->tipo], 422);
externo('PATCH', 'transaccionesexternas/'.$linkRow['id'], ['monto' => '99999'], 422);
externo('PATCH', 'transaccionesexternas/'.$linkRow['id'], ['numero_referencia' => 'OTRO'], 422);
externo('POST', 'transaccionesexternas', ['id_arqueo_externo' => $linked['id'], 'clave' => 'ENLACE-DUPLICADO',
    'id_subagencia' => $agencies[0], 'id_cajero' => $cashier->id, 'banco' => 'lafise', 'servicio' => 'remesa',
    'tipo' => $linkRow['tipo'], 'moneda' => $source->moneda, 'monto' => (string) $source->monto,
    'numero_referencia' => $source->numero_remesa, 'id_remesa_caja' => $source->id], 409);
cerrarExterno($linked['id']);

$db->beginTransaction();
try {
    $db->table('users')->where('id', $admin->id)->update(['rol_usuario' => 'Cajero']);
    $headerPending = resumenExterno($replacement['id']);
    expectApi(! $headerPending['completo'] && $headerPending['pendientes'][0]['id_transaccion'] === null
        && isset($headerPending['pendientes'][0]['errores']['id_responsable']), 'Un responsable que perdio su rol aparece como pendiente de cabecera');
    expectApi(resumenExterno($id) === $closedExternal, 'Un cambio posterior de responsables no recalcula el cierre historico');
} finally {
    $db->rollBack();
}

expectApi(protectedApiSnapshot() === $externalProtected, 'Los servicios externos no modifican los maestros protegidos');
expectApi($db->table('historial_financiero')->where('entidad', 'arqueos_servicios_externos')->where('accion', 'cerrar')->count() === 2,
    'Cada cierre conserva su evento de auditoria');
echo 'OK servicios externos: '.($checks - $startExternalChecks)." comprobaciones nuevas; terminal unica, tarifas, cierre, reportes y comisiones; SQLite en memoria.\n";
