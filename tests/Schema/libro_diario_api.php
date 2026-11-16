<?php

// Router/controladores reales, SQLite :memory:, sin .env ni conexiones de produccion.
require __DIR__.'/caja_api.php';

use App\Models\Administracion\LibroDiario;
use App\Support\Administracion\RecursosLibroDiario;

$startDiaryChecks = $checks;
function diario(string $method, string $uri, array $data = [], int $expected = 200): array
{
    global $admin;

    return api($method, 'administracion/'.$uri, $data, $admin, $expected);
}
function nuevoDiario(string $uri, array $data): array
{
    return diario('POST', $uri, $data, 201)['Registro'];
}
function resumenDiario(int $id): array
{
    return diario('GET', 'librosdiarios/'.$id.'/resumen')['Resultado'];
}
$excelBook = nuevoDiario('librosdiarios', ['fecha' => '2026-11-04', 'usuarios_esperados' => [$cashier->id], 'motivo_apertura' => 'Comprobacion de la hoja 08']);
$excelSource = resumenDiario($excelBook['id'])['fuentes'][0]['datos'];
expectApi($excelSource['metricas']['diferencia_equivalente_nio'] === '9.10200000'
    && $excelSource['resumen']['diferencia']['NIO'] === '32.59000000' && $excelSource['resumen']['diferencia']['USD'] === '-0.64000000',
    'P12 reproduce Evelyn 08 sin perder el faltante USD ni redondear su equivalente');
diario('DELETE', 'librosdiarios/'.$excelBook['id'], ['motivo' => 'Comprobacion terminada']);
foreach (RecursosLibroDiario::DEFINICIONES as $uri => $def) {
    diario('GET', $uri);
    api('GET', 'administracion/'.$uri, [], $cashier, 403);
    api('POST', 'administracion/'.$uri, [], null, 401);
}
$inactiveDiaryAdmin = clone $admin;
$inactiveDiaryAdmin->estado_usuario = 'inactivo';
api('POST', 'administracion/librosdiarios', [], $inactiveDiaryAdmin, 403);

$diaryDate = '2026-11-10';
$diaryOwners = [];
foreach ([0, 1, 2] as $i) {
    $worker = $db->table('trabajadores')->insertGetId(['nombre' => 'Diario '.$i, 'cedula' => 'LD'.$i,
        'fecha_nacimiento' => '1990-01-01', 'inss' => 'LD'.$i, 'nombre_banco' => 'Prueba', 'numero_cuenta_banco' => 'LD'.$i,
        'estadotrabajador' => 'activo', 'fecha_contratacion' => '2026-01-01', 'id_subagencia' => $agencies[$i === 2 ? 1 : 0], 'cargo' => 'Cajero',
        'salario_basico_inicial' => 0, 'salario_basico_actual' => 0, 'fecha_finalizacion' => '2099-12-31', 'concepto_finalizacion' => 'Prueba']);
    $id = $db->table('users')->insertGetId(['usuario' => 'diario'.$i, 'contraseña' => 'SIN_USO', 'rol_usuario' => 'Cajero',
        'correo_asignado_cajero' => 'ld'.$i.'@example.invalid', 'usuario_asignado_airpak' => 'LD'.$i, 'estado_usuario' => 'Activo', 'id_trabajador' => $worker]);
    $diaryOwners[] = \App\Models\User::findOrFail($id);
}
$diaryProtected = protectedApiSnapshot();
$journalIds = [];
foreach ($diaryOwners as $i => $owner) {
    $box = api('POST', 'cajas', ['codigo' => 'LD-'.$i, 'nombre' => 'Caja Diario '.$i, 'id_subagencia' => $agencies[$i === 2 ? 1 : 0]], $admin, 201)['Caja'];
    $journalIds[] = api('POST', 'arqueos', ['fecha' => $diaryDate, 'id_caja' => $box['id'], 'caja_inicial_cordobas' => 1000, 'caja_inicial_dolares' => 100,
        'cheques_inicial_cordobas' => 0, 'cheques_inicial_dolares' => 0, 'tasa_referencia' => '36.7', 'motivo_apertura_manual' => 'Apertura prueba Libro Diario'], $owner, 201)['Arqueo']['id'];
}
$book = nuevoDiario('librosdiarios', ['fecha' => $diaryDate, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners), 'motivo_apertura' => 'Saldos de inicio documentados']);
$bookId = $book['id'];
expectApi(! resumenDiario($bookId)['completo'], 'Las fuentes abiertas no se presentan como corte completo');
diario('POST', 'librosdiarios/'.$bookId.'/cerrar', ['confirmar_fuentes' => true], 422);
diario('PATCH', 'librosdiarios/'.$bookId, ['resultado' => ['falso' => 1]], 422);
$cashBank = [];
foreach ([['bancario', 'Salida', 'NIO', 100], ['bancario', 'Salida', 'NIO', 50], ['varios', 'Entrada', 'NIO', 20], ['varios', 'Salida', 'USD', 5], ['gasto', 'Salida', 'NIO', 5]] as [$cat, $type, $currency, $amount]) {
    $cashBank[] = api('POST', 'entradasysalidas', ['id_arqueo' => $journalIds[0], 'categoria' => $cat, 'tipo' => $type,
        'moneda' => $currency, 'monto' => $amount, 'medio' => 'efectivo', 'detalle' => 'Detalle '.$cat], $diaryOwners[0], 201)['Entrada salida']['id'];
}
$cashRemittances = [];
foreach ([['La Fise', 'Entrada', 'NIO', 10], ['La Fise', 'Salida', 'USD', 2], ['Banpro', 'Salida', 'NIO', 30], ['Banpro', 'Entrada', 'USD', 3]] as $i => [$bank, $type, $currency, $amount]) {
    $cashRemittances[] = api('POST', 'remesasbancarias', ['id_arqueo' => $journalIds[0], 'banco' => $bank, 'tipo' => $type,
        'moneda' => $currency, 'monto' => $amount, 'medio' => 'efectivo', 'numero_remesa' => '000LD'.$i], $diaryOwners[0], 201)['Remesa']['id'];
}
$passA = api('POST', 'pases', ['id_arqueo' => $journalIds[0], 'tipo' => 'Salida', 'moneda' => 'NIO', 'monto' => 40, 'medio' => 'efectivo'], $diaryOwners[0], 201)['Pase']['id'];
api('POST', 'pases', ['id_arqueo' => $journalIds[2], 'tipo' => 'Entrada', 'moneda' => 'NIO', 'monto' => 40, 'medio' => 'efectivo', 'id_pase_relacionado' => $passA], $admin, 422);
api('POST', 'pases', ['id_arqueo' => $journalIds[1], 'tipo' => 'Entrada', 'moneda' => 'NIO', 'monto' => 40, 'medio' => 'efectivo', 'id_pase_relacionado' => $passA], $admin, 201);
$agencyA = api('POST', 'entradasysalidas', ['id_arqueo' => $journalIds[0], 'categoria' => 'entre_agencias', 'tipo' => 'Salida', 'moneda' => 'USD',
    'monto' => 4, 'medio' => 'efectivo', 'id_subagencia_contraparte' => $agencies[1]], $diaryOwners[0], 201)['Entrada salida']['id'];
api('POST', 'entradasysalidas', ['id_arqueo' => $journalIds[2], 'categoria' => 'entre_agencias', 'tipo' => 'Entrada', 'moneda' => 'USD',
    'monto' => 4, 'medio' => 'efectivo', 'id_subagencia_contraparte' => $agencies[0], 'id_movimiento_relacionado' => $agencyA], $admin, 201);
foreach ($journalIds as $i => $id) {
    api('PATCH', 'arqueos/'.$id, ['biller_cordobas' => 0, 'biller_dolares' => 0, 'envios_cordobas' => 0, 'envios_dolares' => 0, 'pagos_cordobas' => 0, 'pagos_dolares' => 0], $diaryOwners[$i]);
    api('POST', 'detallearqueo', $i === 0 ? ['id_arqueo' => $id, 'moneda' => 'NIO', 'estado' => 'contado', 'cien' => 8, 'cinco' => 1]
        : ['id_arqueo' => $id, 'moneda' => 'NIO', 'estado' => 'contado', 'mil' => 1, 'veinte' => $i === 1 ? 2 : 0], $diaryOwners[$i], 201);
    api('POST', 'detallearqueo', $i === 0 ? ['id_arqueo' => $id, 'moneda' => 'USD', 'estado' => 'contado', 'cincuenta' => 1, 'veinte' => 2, 'dos' => 1]
        : ['id_arqueo' => $id, 'moneda' => 'USD', 'estado' => 'contado', 'cien' => 1, 'dos' => $i === 2 ? 2 : 0], $diaryOwners[$i], 201);
    api('POST', 'arqueos/'.$id.'/cerrar', ['confirmar_cheques' => true], $diaryOwners[$i]);
}
$party = nuevoDiario('contrapartes', ['codigo' => 'LD-P', 'nombre' => 'Persona/agente prueba', 'tipo' => 'persona']);
$diaryAccounts = [];
foreach (['bancentro' => 'banco', 'lafise' => 'pos', 'banpro' => 'pos'] as $channel => $type) {
    foreach (['NIO', 'USD'] as $currency) {
        $account = nuevoDiario('cuentas', ['codigo' => 'LD-'.$channel.'-'.$currency, 'nombre' => $channel.' '.$currency, 'tipo' => $type, 'moneda' => $currency,
            'fecha_apertura' => $diaryDate, 'saldo_inicial' => $currency === 'NIO' ? 10000 : 1000, 'rubro_capital' => 'banco']);
        $diaryAccounts[$channel.':'.$currency] = $account['id'];
        nuevoDiario('cuentasdiario', ['id_libro_diario' => $bookId, 'id_cuenta' => $account['id'], 'canal' => $channel]);
    }
}
diario('POST', 'cuentasdiario', ['id_libro_diario' => $bookId, 'id_cuenta' => $diaryAccounts['bancentro:NIO'], 'canal' => 'bancentro'], 409);
diario('POST', 'cuentasdiario', ['id_libro_diario' => $bookId, 'id_cuenta' => $diaryAccounts['bancentro:USD'], 'canal' => 'lafise'], 422);
diario('PATCH', 'cuentas/'.$diaryAccounts['bancentro:NIO'], ['saldo_inicial' => 0], 422);

$central = nuevoDiario('reportesremesascentrales', ['fecha' => $diaryDate, 'corte_at' => $diaryDate.' 18:00:00', 'referencia' => 'LD-CENTRAL-1',
    'fuente' => 'Registro central independiente de prueba', 'agencias_esperadas' => $agencies]);
diario('POST', 'reportesremesascentrales/'.$central['id'].'/confirmar', [], 422);
foreach ($agencies as $agency) {
    foreach (['lafise', 'banpro'] as $bank) {
        foreach (['NIO', 'USD'] as $currency) {
            foreach (['Entrada', 'Salida'] as $type) {
                $amount = $agency === $agencies[0] ? (['lafise:NIO:Salida' => 10, 'lafise:USD:Entrada' => 2, 'banpro:NIO:Entrada' => 30, 'banpro:USD:Salida' => 3][$bank.':'.$currency.':'.$type] ?? 0) : 0;
                nuevoDiario('detallesremesascentrales', ['id_reporte' => $central['id'], 'id_subagencia' => $agency, 'banco' => $bank, 'moneda' => $currency, 'tipo' => $type, 'monto' => $amount]);
            }
        }
    }
}
diario('POST', 'detallesremesascentrales', ['id_reporte' => $central['id'], 'id_subagencia' => $agencies[0], 'banco' => 'lafise', 'moneda' => 'NIO', 'tipo' => 'Salida', 'monto' => 10], 409);
diario('POST', 'reportesremesascentrales/'.$central['id'].'/confirmar');
diario('PATCH', 'detallesremesascentrales/'.$db->table('detalles_remesa_central')->where('id_reporte', $central['id'])->value('id'), ['monto' => 999], 422);
diario('PATCH', 'librosdiarios/'.$bookId, ['id_reporte_remesas' => $central['id']]);

function opDiario(string $key, string $type, string $currency, string $amount, array $extra = []): array
{
    global $diaryDate;

    return nuevoDiario('operaciones', array_merge(['fecha' => $diaryDate, 'clave' => 'LD-'.$key, 'tipo' => $type, 'moneda' => $currency, 'monto' => $amount, 'concepto' => 'Operacion '.$key], $extra));
}
function partidaDiario(array $op, string $block, array $extra = []): array
{
    global $bookId;

    return nuevoDiario('partidasdiario', array_merge(['id_libro_diario' => $bookId, 'id_operacion' => $op['id'], 'bloque' => $block], $extra));
}
$physicalBank = opDiario('banco', 'transferencia', 'NIO', '100', ['origen_tipo' => 'entrada_salida', 'id_origen' => $cashBank[0], 'id_cuenta_destino' => $diaryAccounts['bancentro:NIO']]);
diario('POST', 'partidasdiario', ['id_libro_diario' => $bookId, 'id_operacion' => $physicalBank['id'], 'bloque' => 'adicional'], 422);
partidaDiario($physicalBank, 'banco_cajeros');
diario('DELETE', 'operaciones/'.$physicalBank['id'], ['motivo' => 'No debe retirarse una operacion clasificada'], 422);
$directCash = opDiario('airpak', 'egreso', 'NIO', '50', ['origen_tipo' => 'entrada_salida', 'id_origen' => $cashBank[1]]);
diario('POST', 'partidasdiario', ['id_libro_diario' => $bookId, 'id_operacion' => $directCash['id'], 'bloque' => 'efectivo_airpak'], 422);
partidaDiario($directCash, 'efectivo_airpak', ['receptor' => 'Receptor identificado', 'referencia' => 'RECIBO-0001']);
foreach ($cashRemittances as $i => $sourceId) {
    [$channel, $currency, $direction, $amount] = [['lafise', 'NIO', 'Entrada', '10'], ['lafise', 'USD', 'Salida', '2'], ['banpro', 'NIO', 'Salida', '30'], ['banpro', 'USD', 'Entrada', '3']][$i];
    $op = opDiario('remesa-'.$i, 'transferencia', $currency, $amount, ['origen_tipo' => 'remesa', 'id_origen' => $sourceId,
        $direction === 'Entrada' ? 'id_cuenta_origen' : 'id_cuenta_destino' => $diaryAccounts[$channel.':'.$currency]]);
    partidaDiario($op, 'remesa_pos');
}
$expenseOp = opDiario('gasto', 'egreso', 'NIO', '7', ['id_cuenta_origen' => $diaryAccounts['bancentro:NIO']]);
$beforeClassification = $db->table('movimientos_cuenta_financiera')->count();
partidaDiario($expenseOp, 'gasto');
expectApi($db->table('movimientos_cuenta_financiera')->count() === $beforeClassification, 'Clasificar no vuelve a contabilizar el dinero');
$fxNio = opDiario('tesoreria-nio', 'ingreso', 'NIO', '367', ['id_cuenta_destino' => $diaryAccounts['bancentro:NIO']]);
$fxUsd = opDiario('tesoreria-usd', 'egreso', 'USD', '10', ['id_cuenta_origen' => $diaryAccounts['bancentro:USD']]);
foreach ([$fxNio, $fxUsd] as $op) {
    partidaDiario($op, 'cambio_tesoreria', ['id_contraparte' => $party['id'], 'referencia' => 'FX-001']);
}
$detailOne = nuevoDiario('detallescuentasdiario', ['id_libro_diario' => $bookId, 'id_entrada_salida' => $cashBank[2], 'id_contraparte' => $party['id']]);
$incomplete = resumenDiario($bookId);
expectApi(! $incomplete['completo'] && $incomplete['cuadre_cuentas']['salida_usd'] === '-5.00000000', 'El detalle parcial no cumple la cobertura completa');
diario('POST', 'librosdiarios/'.$bookId.'/cerrar', ['confirmar_fuentes' => true], 422);
nuevoDiario('detallescuentasdiario', ['id_libro_diario' => $bookId, 'id_entrada_salida' => $cashBank[3], 'id_contraparte' => $party['id']]);
diario('POST', 'detallescuentasdiario', ['id_libro_diario' => $bookId, 'id_entrada_salida' => $cashBank[4], 'id_contraparte' => $party['id']], 422);
diario('POST', 'detallescuentasdiario', ['id_libro_diario' => $bookId, 'id_entrada_salida' => $cashBank[2], 'id_contraparte' => $party['id']], 409);
$result = resumenDiario($bookId);
expectApi($result['completo'] && ! $result['con_diferencias'], 'El corte completo cuadra por cajeros, pares, remesas, CxC/CxP, banco y POS');
expectApi(count($result['metricas']) === 31 && count($result['por_agencia']) === 2 && $result['cobertura']['cerrados'] === 3, 'Matriz de 31 indicadores y cobertura por agencia');
expectApi($result['saldos']['bancentro:NIO']['saldo_final'] === '10460.00000000' && $result['saldos']['bancentro:USD']['saldo_final'] === '990.00000000', 'Banco toma los movimientos reales una sola vez');
expectApi($result['saldos']['lafise:NIO']['saldo_final'] === '9990.00000000' && $result['saldos']['banpro:USD']['saldo_final'] === '997.00000000', 'POS conserva la perspectiva inversa y la moneda');
expectApi($result['cuadre_banco_cajeros']['NIO']['Salida']['reconocido_banco'] === '100.00000000'
    && $result['cuadre_banco_cajeros']['NIO']['Salida']['efectivo_airpak'] === '50.00000000', 'Entrega a AirPak y deposito bancario se distinguen');
diario('POST', 'librosdiarios/'.$bookId.'/cerrar', ['confirmar_fuentes' => true], 422);
$c = collect($result['controles'])->firstWhere('clave', 'detalle_cuentas');
nuevoDiario('revisionesdiario', ['id_libro_diario' => $bookId, 'clave_control' => $c['clave'], 'huella' => $c['huella'], 'resultado' => 'revisado', 'detalle' => 'Detalle comprobado']);
diario('PATCH', 'detallescuentasdiario/'.$detailOne['id'], ['observacion' => 'Actualizacion que exige nueva revision']);
diario('POST', 'revisionesdiario', ['id_libro_diario' => $bookId, 'clave_control' => $c['clave'], 'huella' => $c['huella'], 'resultado' => 'revisado', 'detalle' => 'Huella vieja'], 422);
expectApi(in_array('detalle_cuentas', resumenDiario($bookId)['revisiones_pendientes'], true), 'Una revision anterior no aprueba un detalle distinto');
diario('PATCH', 'librosdiarios/'.$bookId, ['usuarios_esperados' => [$diaryOwners[0]->id]]);
expectApi(collect(resumenDiario($bookId)['pendientes'])->contains('tipo', 'arqueos_fuera_alcance'), 'No se puede ocultar un cajero del control global recortando la lista');
diario('PATCH', 'librosdiarios/'.$bookId, ['usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners)]);
$differentCentral = nuevoDiario('reportesremesascentrales', ['fecha' => $diaryDate, 'corte_at' => $diaryDate.' 19:00:00', 'referencia' => 'LD-CENTRAL-2',
    'fuente' => 'Segunda fuente con diferencia documentada', 'agencias_esperadas' => $agencies]);
foreach ($db->table('detalles_remesa_central')->where('id_reporte', $central['id'])->get() as $line) {
    nuevoDiario('detallesremesascentrales', ['id_reporte' => $differentCentral['id'], 'id_subagencia' => $line->id_subagencia, 'banco' => $line->banco,
        'moneda' => $line->moneda, 'tipo' => $line->tipo, 'monto' => ($line->id_subagencia === $agencies[0] && $line->banco === 'lafise' && $line->moneda === 'NIO' && $line->tipo === 'Salida') ? '11' : (string) $line->monto]);
}
diario('POST', 'reportesremesascentrales/'.$differentCentral['id'].'/confirmar');
diario('PATCH', 'librosdiarios/'.$bookId, ['id_reporte_remesas' => $differentCentral['id']]);
$differenceControl = collect(resumenDiario($bookId)['controles'])->firstWhere('clave', 'pos:lafise:NIO');
expectApi(! $differenceControl['coincide'] && $differenceControl['datos']['conciliacion_central']['diferencia_salidas'] === '-1.00000000', 'El POS compara la fuente central independiente y conserva el signo');
diario('POST', 'revisionesdiario', ['id_libro_diario' => $bookId, 'clave_control' => $differenceControl['clave'], 'huella' => $differenceControl['huella'], 'resultado' => 'revisado', 'detalle' => 'Sin resolver diferencia'], 422);
foreach (resumenDiario($bookId)['controles'] as $c) {
    if ($c['requiere_revision']) {
        nuevoDiario('revisionesdiario', ['id_libro_diario' => $bookId, 'clave_control' => $c['clave'], 'huella' => $c['huella'],
            'resultado' => $c['coincide'] ? 'revisado' : 'justificado', 'detalle' => 'Verificado contra comprobantes', 'referencia' => 'CASO-LD-01']);
    }
}
$observed = collect(resumenDiario($bookId)['controles'])->firstWhere('clave', 'cuenta:bancentro:NIO');
nuevoDiario('revisionesdiario', ['id_libro_diario' => $bookId, 'clave_control' => $observed['clave'], 'huella' => $observed['huella'], 'resultado' => 'observado', 'detalle' => 'Revision adicional pendiente']);
diario('POST', 'librosdiarios/'.$bookId.'/cerrar', ['confirmar_fuentes' => true], 422);
nuevoDiario('revisionesdiario', ['id_libro_diario' => $bookId, 'clave_control' => $observed['clave'], 'huella' => $observed['huella'], 'resultado' => 'revisado', 'detalle' => 'Revision adicional completada']);
diario('POST', 'librosdiarios/'.$bookId.'/recalcular');
$closed = diario('POST', 'librosdiarios/'.$bookId.'/cerrar', ['confirmar_fuentes' => true])['Resultado'];
expectApi($closed === resumenDiario($bookId) && LibroDiario::findOrFail($bookId)->huella !== null, 'El cierre conserva su resultado y huella');
expectApi($closed['con_diferencias'] && $closed['revisiones_pendientes'] === [], 'El cierre justificado conserva la diferencia; no la transforma en cero');
diario('PATCH', 'librosdiarios/'.$bookId, ['observacion' => 'No permitido'], 422);
diario('DELETE', 'librosdiarios/'.$bookId, ['motivo' => 'No permitido'], 422);
diario('POST', 'librosdiarios/'.$bookId.'/recalcular', [], 422);
diario('PATCH', 'detallescuentasdiario/'.$detailOne['id'], ['observacion' => 'No permitido'], 422);
diario('POST', 'revisionesdiario', ['id_libro_diario' => $bookId, 'clave_control' => $c['clave'], 'huella' => $c['huella'], 'resultado' => 'revisado', 'detalle' => 'No permitido'], 422);
$mother = nuevoDiario('arqueosmadre', ['fecha' => $diaryDate, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners), 'tasa_referencia' => '36.74', 'id_libro_diario' => $bookId]);
expectApi($mother['totales']['libro_diario']['saldos'] === $closed['saldos'], 'Arqueo madre recibe los seis saldos del mismo corte cerrado');
diario('POST', 'arqueosmadre', ['fecha' => $diaryDate, 'usuarios_esperados' => [$diaryOwners[0]->id], 'tasa_referencia' => '36.74', 'id_libro_diario' => $bookId], 422);
diario('POST', 'arqueosmadre/'.$mother['id'].'/cerrar');
$settlement = nuevoDiario('liquidaciones', ['id_arqueo_madre' => $mother['id'], 'saldo_anterior_usd' => 0, 'motivo_saldo_inicial' => 'Primera liquidacion de prueba']);
diario('POST', 'liquidaciones/'.$settlement['id'].'/cerrar');
$capitalBefore = diario('GET', 'capital', ['fecha' => $diaryDate, 'periodo_desde' => $diaryDate, 'id_arqueo_madre' => $mother['id'], 'id_liquidacion' => $settlement['id'],
    'cuentas_capital_trabajo' => array_values($diaryAccounts)])['Resultado'];
expectApi($capitalBefore['pendientes'] === [] && $capitalBefore['libro_diario']['id'] === $bookId, 'El control financiero recibe el corte del Libro Diario');

$late = opDiario('reconocimiento-posterior', 'ingreso', 'NIO', '1', ['id_cuenta_destino' => $diaryAccounts['bancentro:NIO']]);
expectApi(resumenDiario($bookId) === $closed, 'Una fuente financiera posterior no reescribe el corte archivado');
$capitalAfter = diario('GET', 'capital', ['fecha' => $diaryDate, 'periodo_desde' => $diaryDate, 'id_arqueo_madre' => $mother['id'], 'id_liquidacion' => $settlement['id'],
    'cuentas_capital_trabajo' => array_values($diaryAccounts)])['Resultado'];
expectApi($capitalAfter['componentes']['bancos'] === $capitalBefore['componentes']['bancos']
    && $capitalAfter['capital_trabajo_excel'] === $capitalBefore['capital_trabajo_excel']
    && collect($capitalAfter['pendientes'])->contains('tipo', 'saldo_distinto_libro_diario'), 'El capital conserva el saldo recibido y exige resolver el reconocimiento posterior');
$revisionBook = nuevoDiario('librosdiarios', ['fecha' => $diaryDate, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners), 'motivo_apertura' => 'Nueva version']);
expectApi($revisionBook['version'] === 2, 'La nueva version no reemplaza el corte anterior');
diario('DELETE', 'librosdiarios/'.$revisionBook['id'], ['motivo' => 'Version descartada']);
$nextBook = nuevoDiario('librosdiarios', ['fecha' => '2026-11-11', 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners)]);
expectApi($nextBook['id_anterior'] === $bookId && $db->table('cuentas_libro_diario')->where('id_libro_diario', $nextBook['id'])->count() === 6, 'La apertura siguiente reutiliza cuentas y referencia al cierre anterior');
$nextResult = resumenDiario($nextBook['id']);
$openingControl = collect($nextResult['controles'])->firstWhere('clave', 'apertura:bancentro:NIO');
expectApi(! $openingControl['coincide'] && $openingControl['datos']['diferencia_continuidad'] === '1.00000000', 'La operacion reconocida tarde queda como diferencia de continuidad explicita');
diario('POST', 'revisionesdiario', ['id_libro_diario' => $nextBook['id'], 'clave_control' => $openingControl['clave'], 'huella' => $openingControl['huella'], 'resultado' => 'revisado', 'detalle' => 'No debe ocultarse'], 422);
diario('POST', 'revisionesdiario', ['id_libro_diario' => $nextBook['id'], 'clave_control' => $openingControl['clave'], 'huella' => $openingControl['huella'], 'resultado' => 'justificado', 'detalle' => 'Falta evidencia'], 422);
nuevoDiario('revisionesdiario', ['id_libro_diario' => $nextBook['id'], 'clave_control' => $openingControl['clave'], 'huella' => $openingControl['huella'], 'resultado' => 'justificado', 'detalle' => 'Reconocimiento posterior identificado', 'referencia' => 'LD-'.$late['id']]);
expectApi(collect(resumenDiario($nextBook['id'])['controles'])->firstWhere('clave', $openingControl['clave'])['coincide'] === false, 'Justificar conserva la diferencia y su evidencia');
expectApi(protectedApiSnapshot() === $diaryProtected, 'El Libro Diario no altera tablas protegidas');
expectApi($db->select('PRAGMA foreign_key_check') === [], 'El Diario no deja referencias huerfanas');
echo 'OK Libro Diario: '.($checks - $startDiaryChecks).' comprobaciones adicionales; SQLite en memoria.'.PHP_EOL;
