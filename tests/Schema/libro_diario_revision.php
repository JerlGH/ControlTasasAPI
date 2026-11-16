<?php

// Revision adicional con escenarios adversos; cada caso revierte su propia transaccion en memoria.
require __DIR__.'/libro_diario_api.php';

$startReviewChecks = $checks;
$reviewFailures = [];
function reviewCase(string $name, callable $case): void
{
    global $db, $reviewFailures;
    $db->beginTransaction();
    try {
        $case();
    } catch (Throwable $e) {
        $reviewFailures[] = $name.': '.$e->getMessage();
    } finally {
        $db->rollBack();
    }
}
function reviewDraft(): int
{
    global $bookId, $diaryDate, $diaryOwners, $differentCentral, $late, $db;
    $draft = nuevoDiario('librosdiarios', ['fecha' => $diaryDate, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners),
        'motivo_apertura' => 'Revision de casos adversos', 'id_reporte_remesas' => $differentCentral['id']]);
    foreach ($db->table('cuentas_libro_diario')->where('id_libro_diario', $bookId)->where('estado', 'registrado')->get() as $row) {
        nuevoDiario('cuentasdiario', ['id_libro_diario' => $draft['id'], 'id_cuenta' => $row->id_cuenta, 'canal' => $row->canal]);
    }
    foreach ($db->table('partidas_libro_diario')->where('id_libro_diario', $bookId)->where('estado', 'registrado')->get() as $row) {
        $data = array_intersect_key((array) $row, array_flip(['id_operacion', 'bloque', 'id_contraparte', 'id_documento', 'receptor', 'referencia', 'observacion']));
        nuevoDiario('partidasdiario', $data + ['id_libro_diario' => $draft['id']]);
    }
    nuevoDiario('partidasdiario', ['id_libro_diario' => $draft['id'], 'id_operacion' => $late['id'], 'bloque' => 'adicional']);
    foreach ($db->table('detalles_cuenta_diario')->where('id_libro_diario', $bookId)->where('estado', 'registrado')->get() as $row) {
        $data = array_intersect_key((array) $row, array_flip(['id_entrada_salida', 'id_cuenta_auxiliar', 'id_contraparte', 'id_operacion', 'id_documento', 'observacion']));
        nuevoDiario('detallescuentasdiario', $data + ['id_libro_diario' => $draft['id']]);
    }
    expectApi(resumenDiario($draft['id'])['completo'], 'La base del escenario debe estar completa');

    return $draft['id'];
}
function reviewAll(int $id): void
{
    foreach (resumenDiario($id)['controles'] as $c) {
        if ($c['requiere_revision']) {
            nuevoDiario('revisionesdiario', ['id_libro_diario' => $id, 'clave_control' => $c['clave'], 'huella' => $c['huella'],
                'resultado' => $c['coincide'] ? 'revisado' : 'justificado', 'detalle' => 'Verificado en revision adicional', 'referencia' => 'REV-20261105']);
        }
    }
}

reviewCase('Filtro superior independiente', function () {
    diario('GET', 'librosdiarios', ['fecha_hasta' => '2026-11-10']);
    diario('GET', 'librosdiarios', ['fecha_desde' => '2026-11-11', 'fecha_hasta' => '2026-11-10'], 422);
});
reviewCase('Vinculo en arqueo madre borrador', function () {
    global $diaryDate, $diaryOwners, $bookId, $mother;
    $root = nuevoDiario('arqueosmadre', ['fecha' => $diaryDate, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners), 'tasa_referencia' => '36.7']);
    diario('PATCH', 'arqueosmadre/'.$root['id'], ['id_libro_diario' => $bookId]);
    expectApi(diario('GET', 'arqueosmadre/'.$root['id'].'/resumen')['Resultado']['Totales']['libro_diario']['id'] === $bookId, 'Debe aceptar el vinculo anunciado');
    diario('PATCH', 'arqueosmadre/'.$root['id'], ['id_libro_diario' => null]);
    diario('PATCH', 'arqueosmadre/'.$mother['id'], ['id_libro_diario' => null], 422);
});
reviewCase('Cambio posterior de canal POS', function () {
    global $db, $diaryAccounts;
    $id = reviewDraft();
    foreach (['lafise', 'banpro'] as $channel) {
        $mapping = $db->table('cuentas_libro_diario')->where('id_libro_diario', $id)->where('canal', $channel)->where('moneda', 'NIO')->where('estado', 'registrado')->first();
        diario('DELETE', 'cuentasdiario/'.$mapping->id, ['motivo' => 'Intercambiar canales para comprobar validacion']);
    }
    nuevoDiario('cuentasdiario', ['id_libro_diario' => $id, 'id_cuenta' => $diaryAccounts['lafise:NIO'], 'canal' => 'banpro']);
    nuevoDiario('cuentasdiario', ['id_libro_diario' => $id, 'id_cuenta' => $diaryAccounts['banpro:NIO'], 'canal' => 'lafise']);
    expectApi(! resumenDiario($id)['completo'], 'Una partida validada para otro POS debe impedir el cierre, aunque se justifique el total');
});
reviewCase('Titular desactivado despues del detalle', function () {
    global $party;
    $id = reviewDraft();
    diario('PATCH', 'contrapartes/'.$party['id'], ['estado' => 'inactivo']);
    expectApi(! resumenDiario($id)['completo'], 'Las referencias inactivas deben detectarse antes del cierre');
});
reviewCase('Reconocimientos posteriores de neto cero', function () {
    global $diaryAccounts, $diaryDate, $mother, $settlement, $capitalBefore, $nextBook;
    opDiario('revision-compensa-tardio', 'egreso', 'NIO', '1', ['id_cuenta_origen' => $diaryAccounts['bancentro:NIO']]);
    $result = diario('GET', 'capital', ['fecha' => $diaryDate, 'periodo_desde' => $diaryDate, 'id_arqueo_madre' => $mother['id'], 'id_liquidacion' => $settlement['id']])['Resultado'];
    expectApi($result['componentes']['bancos'] === $capitalBefore['componentes']['bancos'], 'El neto vuelve al cierre original');
    expectApi(collect($result['pendientes'])->contains('tipo', 'movimientos_distintos_libro_diario'), 'Dos movimientos posteriores no se pueden ocultar porque se compensen');
    $opening = collect(resumenDiario($nextBook['id'])['controles'])->firstWhere('clave', 'apertura:bancentro:NIO');
    expectApi($opening['datos']['diferencia_continuidad'] === '0.00000000' && ! $opening['coincide'], 'La continuidad tambien revisa los movimientos, aunque el saldo no cambie');
});
reviewCase('Cuentas excluidas del control financiero', function () {
    global $diaryDate;
    $id = reviewDraft();
    $excluded = nuevoDiario('cuentas', ['codigo' => 'REV-EXCLUIDA', 'nombre' => 'POS excluido', 'tipo' => 'pos', 'moneda' => 'USD',
        'fecha_apertura' => $diaryDate, 'saldo_inicial' => 100, 'rubro_capital' => 'excluido']);
    diario('POST', 'cuentasdiario', ['id_libro_diario' => $id, 'id_cuenta' => $excluded['id'], 'canal' => 'lafise'], 422);
});
reviewCase('Arqueo recibido despues del corte', function () {
    global $admin, $diaryDate, $mother, $settlement, $diaryOwners, $bookId;
    api('POST', 'arqueos', ['fecha' => $diaryDate, 'caja_inicial_cordobas' => 0, 'caja_inicial_dolares' => 0,
        'tasa_referencia' => '36.7', 'motivo_apertura_manual' => 'Arqueo recibido tarde'], $admin, 201);
    $capital = diario('GET', 'capital', ['fecha' => $diaryDate, 'periodo_desde' => $diaryDate, 'id_arqueo_madre' => $mother['id'], 'id_liquidacion' => $settlement['id']])['Resultado'];
    expectApi(collect($capital['pendientes'])->contains('tipo', 'fuentes_distintas_libro_diario'), 'El cierre financiero no puede ignorar un nuevo arqueo de la fecha');
    $newMother = nuevoDiario('arqueosmadre', ['fecha' => $diaryDate, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners),
        'tasa_referencia' => '36.7', 'id_libro_diario' => $bookId]);
    expectApi(! $newMother['totales']['completo'], 'La consolidacion detecta que el corte enlazado ya no cubre todas las fuentes');
    diario('POST', 'arqueosmadre/'.$newMother['id'].'/cerrar', [], 422);
});
reviewCase('Reduccion de deuda automatica y limite de pago', function () {
    global $diaryDate, $diaryAccounts;
    $account = nuevoDiario('cuentas', ['codigo' => 'REV-CXP', 'nombre' => 'Deuda por reducir', 'tipo' => 'por_pagar', 'moneda' => 'NIO',
        'fecha_apertura' => $diaryDate, 'saldo_inicial' => 0, 'rubro_capital' => 'pendiente']);
    $expense = nuevoDiario('gastos', ['id_cuenta' => $account['id'], 'fecha' => $diaryDate, 'periodo_desde' => $diaryDate, 'periodo_hasta' => $diaryDate,
        'ambito' => 'operativo', 'categoria' => 'materiales', 'concepto' => 'Obligacion de cien', 'moneda' => 'NIO', 'monto' => 100]);
    $credit = nuevoDiario('movimientoscuenta', ['id_cuenta' => $account['id'], 'fecha' => $diaryDate, 'tipo' => 'abono', 'monto' => 20,
        'clave' => 'REV-REDUCCION', 'concepto' => 'Reduccion sin efectivo', 'motivo' => 'Acuerdo documentado']);
    $op = opDiario('revision-pago-reducido', 'egreso', 'NIO', '100', ['id_cuenta_origen' => $diaryAccounts['bancentro:NIO']]);
    diario('POST', 'aplicaciones', ['id_operacion' => $op['id'], 'fecha' => $diaryDate, 'monto' => 100, 'id_gasto' => $expense['id']], 422);
    $allocation = nuevoDiario('aplicacionescuenta', ['id_cargo' => $expense['id_movimiento_origen'], 'id_abono' => $credit['id'], 'fecha' => $diaryDate, 'monto' => 20]);
    nuevoDiario('aplicaciones', ['id_operacion' => $op['id'], 'fecha' => $diaryDate, 'monto' => 80, 'id_gasto' => $expense['id']]);
    $balance = diario('GET', 'cuentas/'.$account['id'].'/saldo', ['fecha' => $diaryDate])['Resultado'];
    expectApi($balance['saldo'] === '0.00000000' && $balance['movimientos'][0]['pendiente_aplicacion'] === '0.00000000', 'Reduccion y pago cancelan la misma obligacion sin dejar pendientes ficticios');
    diario('DELETE', 'aplicacionescuenta/'.$allocation['id'], ['motivo' => 'Comprobar guardia de saldo neto']);
    diario('POST', 'aplicaciones', ['id_operacion' => $op['id'], 'fecha' => $diaryDate, 'monto' => 1, 'id_gasto' => $expense['id']], 422);
});
reviewCase('Relevo de cuenta bancaria inactiva', function () {
    global $diaryAccounts, $diaryOwners, $db;
    diario('PATCH', 'cuentas/'.$diaryAccounts['bancentro:NIO'], ['estado' => 'inactivo']);
    $root = nuevoDiario('librosdiarios', ['fecha' => '2026-11-12', 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners)]);
    expectApi($db->table('cuentas_libro_diario')->where('id_libro_diario', $root['id'])->where('estado', 'registrado')->count() === 5, 'La cuenta inactiva queda pendiente de sustitucion; no bloquea la apertura completa');
    expectApi(! resumenDiario($root['id'])['completo'], 'No se puede cerrar sin sustituir la cuenta');
});
reviewCase('Cierre anterior que aparece despues de abrir', function () {
    global $nextBook, $diaryOwners, $journalIds, $agencies;
    $future = nuevoDiario('librosdiarios', ['fecha' => '2026-11-12', 'usuarios_esperados' => array_map(fn ($u) => $u->id, $diaryOwners)]);
    foreach ($diaryOwners as $i => $owner) {
        $day = api('POST', 'arqueos', ['fecha' => '2026-11-11'], $owner, 201)['Arqueo'];
        api('PATCH', 'arqueos/'.$day['id'], ['biller_cordobas' => 0, 'biller_dolares' => 0, 'envios_cordobas' => 0, 'envios_dolares' => 0, 'pagos_cordobas' => 0, 'pagos_dolares' => 0], $owner);
        api('POST', 'detallearqueo', $i === 0 ? ['id_arqueo' => $day['id'], 'moneda' => 'NIO', 'estado' => 'contado', 'cien' => 8, 'cinco' => 1]
            : ['id_arqueo' => $day['id'], 'moneda' => 'NIO', 'estado' => 'contado', 'mil' => 1, 'veinte' => $i === 1 ? 2 : 0], $owner, 201);
        api('POST', 'detallearqueo', $i === 0 ? ['id_arqueo' => $day['id'], 'moneda' => 'USD', 'estado' => 'contado', 'cincuenta' => 1, 'veinte' => 2, 'dos' => 1]
            : ['id_arqueo' => $day['id'], 'moneda' => 'USD', 'estado' => 'contado', 'cien' => 1, 'dos' => $i === 2 ? 2 : 0], $owner, 201);
        api('POST', 'arqueos/'.$day['id'].'/cerrar', ['confirmar_cheques' => true], $owner);
    }
    $report = nuevoDiario('reportesremesascentrales', ['fecha' => '2026-11-11', 'corte_at' => '2026-11-11 18:00:00', 'referencia' => 'REV-CENTRAL-11', 'fuente' => 'Revision', 'agencias_esperadas' => $agencies]);
    foreach ($agencies as $agency) {
        foreach (['lafise', 'banpro'] as $bank) {
            foreach (['NIO', 'USD'] as $currency) {
                foreach (['Entrada', 'Salida'] as $type) {
                    nuevoDiario('detallesremesascentrales', ['id_reporte' => $report['id'], 'id_subagencia' => $agency, 'banco' => $bank, 'moneda' => $currency, 'tipo' => $type, 'monto' => 0]);
                }
            }
        }
    }
    diario('POST', 'reportesremesascentrales/'.$report['id'].'/confirmar');
    diario('PATCH', 'librosdiarios/'.$nextBook['id'], ['id_reporte_remesas' => $report['id']]);
    reviewAll($nextBook['id']);
    diario('POST', 'librosdiarios/'.$nextBook['id'].'/cerrar', ['confirmar_fuentes' => true]);
    $control = collect(resumenDiario($future['id'])['controles'])->firstWhere('clave', 'apertura:bancentro:NIO');
    expectApi($control['datos']['id_diario_anterior'] === $nextBook['id'], 'Debe usar el ultimo cierre disponible, no una referencia anterior obsoleta');
});

if ($reviewFailures !== []) {
    echo implode(PHP_EOL, $reviewFailures).PHP_EOL;
    exit(1);
}
expectApi(protectedApiSnapshot() === $diaryProtected, 'La revision no altera datos protegidos');
echo 'OK segunda revision: '.($checks - $startReviewChecks).' comprobaciones adicionales; casos aislados en SQLite en memoria.'.PHP_EOL;
