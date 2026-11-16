<?php

// Regresion de rutas/controladores reales, exclusivamente contra SQLite :memory:.
require __DIR__.'/administracion_api.php';

use App\Models\Administracion\MovimientoPersonal;
use App\Models\Administracion\MovimientoCuenta;
use App\Models\Administracion\OperacionFinanciera;
use App\Support\Administracion\Finanzas as F;

$personalChecks = $checks;
$personalDate = '2026-11-10';
$personalNextDate = '2026-11-11';
$personalProtected = protectedApiSnapshot();
$personalParty = makeFinance('contrapartes', ['codigo' => 'PERSONAL-P', 'nombre' => 'Particular de prueba', 'tipo' => 'persona']);
function personalAccount(string $code, string $type, string $currency = 'NIO', string $initial = '0', bool $owner = true): array
{
    global $personalDate, $personalParty;

    return makeFinance('cuentas', ['codigo' => $code, 'nombre' => $code, 'tipo' => $type, 'moneda' => $currency,
        'id_contraparte' => $owner ? $personalParty['id'] : null, 'fecha_apertura' => $personalDate,
        'saldo_inicial' => $initial, 'rubro_capital' => in_array($type, ['banco', 'pos'], true) ? 'banco' : 'pendiente']);
}
$personalBank = personalAccount('PERSONAL-BANCO', 'banco', 'NIO', '10000');
$personalUsdBank = personalAccount('PERSONAL-USD', 'banco', 'USD', '1000');
function personalMoney(string $key, string $direction, string $amount, string $currency = 'NIO', ?string $date = null): array
{
    global $personalDate, $personalBank, $personalUsdBank;
    $bank = $currency === 'NIO' ? $personalBank : $personalUsdBank;

    return makeFinance('operaciones', ['clave' => $key, 'fecha' => $date ?? $personalDate, 'tipo' => $direction,
        'moneda' => $currency, 'monto' => $amount, 'concepto' => $key,
        $direction === 'ingreso' ? 'id_cuenta_destino' : 'id_cuenta_origen' => $bank['id']]);
}
function personalInput(string $key, array $account, array $money, string $type, ?string $amount = null, ?string $date = null): array
{
    global $personalDate;

    return ['clave' => $key, 'id_cuenta' => $account['id'], 'id_operacion' => $money['id'], 'tipo' => $type,
        'fecha' => $date ?? $personalDate, 'monto' => $amount ?? $money['monto'], 'concepto' => $key];
}
function personalBalance(array $account, ?string $date = null): array
{
    global $personalDate;

    return finance('GET', 'cuentas/'.$account['id'].'/saldo?fecha='.($date ?? $personalDate))['Resultado'];
}
$fund = personalAccount('PERSONAL-FONDO', 'fondo');
$initialDeposit = personalMoney('PERSONAL-ENTRADA', 'ingreso', '100');
$bankAfterMoney = personalBalance($personalBank)['saldo'];
$depositInput = personalInput('PERSONAL-DEPOSITO', $fund, $initialDeposit, 'deposito');
api('POST', 'administracion/movimientospersonales', $depositInput, $cashier, 403);
api('POST', 'administracion/movimientospersonales', $depositInput, null, 401);
api('POST', 'administracion/movimientospersonales', $depositInput, $inactiveAdmin, 403);
$deposit = makeFinance('movimientospersonales', $depositInput);
expectApi($deposit['saldo_anterior'] === '0.00000000' && $deposit['saldo_posterior'] === '100.00000000'
    && $deposit['fondos_incrementados'] === '100.00000000', 'El deposito crea fondos del titular vinculados al ingreso');
expectApi(personalBalance($personalBank)['saldo'] === $bankAfterMoney, 'Clasificar el deposito no registra otro ingreso bancario');
finance('POST', 'movimientospersonales', $depositInput, 409);
finance('POST', 'movimientospersonales', array_replace($depositInput, ['clave' => 'PERSONAL-DUP-DINERO']), 422);
finance('DELETE', 'operaciones/'.$initialDeposit['id'], ['motivo' => 'Origen con clasificacion activa'], 422);
finance('PATCH', 'movimientospersonales/'.$deposit['id'], ['monto' => '101'], 422);
finance('PATCH', 'movimientospersonales/'.$deposit['id'], ['saldo_posterior' => '999'], 422);

$withdrawMoney = personalMoney('PERSONAL-EGRESO', 'egreso', '150');
$bankAfterWithdrawal = personalBalance($personalBank)['saldo'];
$withdrawal = makeFinance('movimientospersonales', personalInput('PERSONAL-RETIRO', $fund, $withdrawMoney, 'retiro'));
expectApi($withdrawal['fondos_utilizados'] === '100.00000000' && $withdrawal['deuda_generada'] === '50.00000000'
    && $withdrawal['saldo_posterior'] === '-50.00000000', 'El retiro consume fondos y el excedente genera deuda del titular');
expectApi(personalBalance($fund)['posicion_personal'] === ['fondos_del_titular' => '0.00000000', 'deuda_del_titular' => '50.00000000'],
    'La consulta distingue fondos y deuda sin cambiar de cuenta');
expectApi(personalBalance($personalBank)['saldo'] === $bankAfterWithdrawal, 'El retiro personal no duplica el egreso');
finance('DELETE', 'movimientospersonales/'.$deposit['id'], ['motivo' => 'Hay un retiro posterior'], 422);

$repayment = makeFinance('movimientospersonales', personalInput('PERSONAL-REINTEGRO', $fund,
    personalMoney('PERSONAL-REINGRESO', 'ingreso', '20'), 'deposito'));
expectApi($repayment['deuda_cancelada'] === '20.00000000' && $repayment['fondos_incrementados'] === '0.00000000'
    && $repayment['saldo_posterior'] === '-30.00000000', 'Un deposito amortiza primero la deuda del fondo');
$excessDeposit = makeFinance('movimientospersonales', personalInput('PERSONAL-REINTEGRO-EXTRA', $fund,
    personalMoney('PERSONAL-REINGRESO-EXTRA', 'ingreso', '50'), 'deposito'));
expectApi($excessDeposit['deuda_cancelada'] === '30.00000000' && $excessDeposit['fondos_incrementados'] === '20.00000000'
    && $excessDeposit['saldo_posterior'] === '20.00000000', 'El remanente del reintegro vuelve a ser fondo del titular');
finance('DELETE', 'movimientospersonales/'.$excessDeposit['id'], ['motivo' => 'Corregir el ultimo deposito']);
expectApi(personalBalance($fund)['saldo'] === '-30.00000000', 'Anular revierte el auxiliar conservando el ingreso real');
$reclassifiedDeposit = makeFinance('movimientospersonales', personalInput('PERSONAL-DEPOSITO-REVISADO', $fund,
    OperacionFinanciera::findOrFail($excessDeposit['id_operacion'])->toArray(), 'deposito'));
expectApi(personalBalance($fund)['saldo'] === '20.00000000', 'El origen liberado se puede reclasificar una sola vez');

$initialFund = personalAccount('PERSONAL-INICIAL', 'fondo', 'NIO', '75');
$fromInitial = makeFinance('movimientospersonales', personalInput('PERSONAL-RETIRO-INICIAL', $initialFund,
    personalMoney('PERSONAL-SALIDA-INICIAL', 'egreso', '100'), 'retiro'));
expectApi($fromInitial['fondos_utilizados'] === '75.00000000' && $fromInitial['deuda_generada'] === '25.00000000',
    'El retiro toma en cuenta el saldo de apertura sin duplicarlo como cargo');
$negativeFund = personalAccount('PERSONAL-INICIAL-NEG', 'fondo', 'NIO', '-40');
$negativeDeposit = makeFinance('movimientospersonales', personalInput('PERSONAL-NEG-REINTEGRO', $negativeFund,
    personalMoney('PERSONAL-NEG-INGRESO', 'ingreso', '60'), 'deposito'));
expectApi($negativeDeposit['deuda_cancelada'] === '40.00000000' && $negativeDeposit['fondos_incrementados'] === '20.00000000',
    'El reintegro reconoce tambien una deuda inicial del titular');

$receivablePerson = personalAccount('PERSONAL-CXC', 'por_cobrar');
$loanMoneyPerson = personalMoney('PERSONAL-PRESTAMO-EGRESO', 'egreso', '300');
$loanPerson = makeFinance('movimientospersonales', personalInput('PERSONAL-PRESTAMO', $receivablePerson, $loanMoneyPerson, 'prestamo_entregado'));
expectApi($loanPerson['deuda_generada'] === '300.00000000' && personalBalance($receivablePerson)['saldo'] === '300.00000000',
    'Entregar un prestamo genera un cargo CxC por el mismo dinero');
$loanCollectionMoney = personalMoney('PERSONAL-COBRO', 'ingreso', '120');
$loanCollection = makeFinance('aplicaciones', ['id_operacion' => $loanCollectionMoney['id'], 'fecha' => $personalDate,
    'monto' => '120', 'id_cargo' => $loanPerson['id_movimiento_generado']]);
expectApi(personalBalance($receivablePerson)['saldo'] === '180.00000000'
    && F::cmp((new \App\Services\Administracion\LedgerService)->pending(MovimientoCuenta::findOrFail($loanPerson['id_movimiento_generado'])), '180') === 0,
    'El prestamo se cobra parcialmente mediante las aplicaciones existentes');
finance('DELETE', 'movimientospersonales/'.$loanPerson['id'], ['motivo' => 'Todavia tiene un cobro'], 422);
finance('DELETE', 'aplicaciones/'.$loanCollection['id'], ['motivo' => 'Corregir el cobro']);
expectApi(personalBalance($receivablePerson)['saldo'] === '300.00000000', 'Anular el cobro restaura el principal');
finance('DELETE', 'movimientospersonales/'.$loanPerson['id'], ['motivo' => 'Reclasificar prestamo sin cobros']);
expectApi(personalBalance($receivablePerson)['saldo'] === '0.00000000', 'El prestamo sin aplicaciones puede anularse sin otro egreso');
$loanPerson = makeFinance('movimientospersonales', personalInput('PERSONAL-PRESTAMO-REVISADO', $receivablePerson, $loanMoneyPerson, 'prestamo_entregado'));

$splitMoney = personalMoney('PERSONAL-SALIDA-COMPARTIDA', 'egreso', '100');
makeFinance('movimientospersonales', personalInput('PERSONAL-PRESTAMO-PARCIAL', $receivablePerson, $splitMoney, 'prestamo_entregado', '60'));
$splitExpense = makeFinance('gastos', ['fecha' => $personalDate, 'periodo_desde' => $personalDate, 'periodo_hasta' => $personalDate,
    'ambito' => 'operativo', 'categoria' => 'prueba', 'concepto' => 'Resto del egreso', 'moneda' => 'NIO', 'monto' => '40']);
finance('POST', 'aplicaciones', ['id_operacion' => $splitMoney['id'], 'fecha' => $personalDate, 'monto' => '41', 'id_gasto' => $splitExpense['id']], 422);
makeFinance('aplicaciones', ['id_operacion' => $splitMoney['id'], 'fecha' => $personalDate, 'monto' => '40', 'id_gasto' => $splitExpense['id']]);
finance('POST', 'movimientospersonales', personalInput('PERSONAL-SOBRE-APLICADO', $receivablePerson, $splitMoney, 'prestamo_entregado', '1'), 422);

$convertedSource = personalMoney('PERSONAL-USD-INGRESO', 'ingreso', '10', 'USD');
$convertedInput = personalInput('PERSONAL-DEPOSITO-CONVERSION', $fund, $convertedSource, 'deposito');
finance('POST', 'movimientospersonales', $convertedInput, 422);
$convertedPersonal = makeFinance('movimientospersonales', $convertedInput + ['tasa_cambio' => '36.74']);
expectApi($convertedPersonal['monto'] === '10.00000000' && $convertedPersonal['monto_cuenta'] === '367.40000000'
    && $convertedPersonal['moneda_origen'] === 'USD' && $convertedPersonal['moneda'] === 'NIO', 'El movimiento conserva monto de origen y equivalente en la moneda de la cuenta');

$ownerless = personalAccount('PERSONAL-SIN-TITULAR', 'fondo', 'NIO', '0', false);
$unusedIncome = personalMoney('PERSONAL-VALIDAR-INGRESO', 'ingreso', '10');
finance('POST', 'movimientospersonales', personalInput('PERSONAL-SIN-PERSONA', $ownerless, $unusedIncome, 'deposito'), 422);
finance('POST', 'movimientospersonales', personalInput('PERSONAL-TIPO-CUENTA', $receivablePerson, $unusedIncome, 'deposito'), 422);
finance('POST', 'movimientospersonales', personalInput('PERSONAL-DIRECCION', $fund, $unusedIncome, 'retiro'), 422);
finance('POST', 'movimientospersonales', personalInput('PERSONAL-ANTERIOR-ORIGEN', $fund, $unusedIncome, 'deposito', null, '2026-11-09'), 422);
$beforeFailure = MovimientoPersonal::count();
finance('POST', 'movimientospersonales', personalInput('PERSONAL-CAMPO-SERVIDOR', $fund, $unusedIncome, 'deposito') + ['deuda_generada' => '1'], 422);
expectApi(MovimientoPersonal::count() === $beforeFailure, 'Una captura rechazada no deja registros parciales');

$reservedMoney = personalMoney('PERSONAL-RESERVA-ANTICIPO', 'egreso', '10');
$reservedAccount = personalAccount('PERSONAL-ANTICIPO-CXC', 'por_cobrar');
makeFinance('obligacioneslaborales', ['fecha' => $personalDate, 'periodo_desde' => $personalDate, 'periodo_hasta' => $personalDate,
    'id_trabajador' => $workerId, 'id_cuenta' => $reservedAccount['id'], 'concepto' => 'anticipo', 'moneda' => 'NIO', 'monto' => '10',
    'id_operacion_anticipo' => $reservedMoney['id']]);
finance('POST', 'movimientospersonales', personalInput('PERSONAL-RESERVA-RECHAZO', $receivablePerson, $reservedMoney, 'prestamo_entregado'), 422);
finance('POST', 'obligacioneslaborales', ['fecha' => $personalDate, 'periodo_desde' => $personalDate, 'periodo_hasta' => $personalDate,
    'id_trabajador' => $workerId, 'id_cuenta' => $reservedAccount['id'], 'concepto' => 'anticipo', 'moneda' => 'NIO', 'monto' => '300',
    'id_operacion_anticipo' => $loanMoneyPerson['id']], 422);

$timelineAccount = personalAccount('PERSONAL-CRONOLOGIA', 'fondo');
$manualEarlier = makeFinance('movimientoscuenta', ['id_cuenta' => $timelineAccount['id'], 'fecha' => $personalDate, 'tipo' => 'cargo',
    'monto' => '50', 'concepto' => 'Ajuste anterior', 'clave' => 'PERSONAL-ANTERIOR', 'motivo' => 'Saldo heredado']);
$later = makeFinance('movimientospersonales', personalInput('PERSONAL-POSTERIOR', $timelineAccount,
    personalMoney('PERSONAL-POSTERIOR-EGRESO', 'egreso', '60', 'NIO', $personalNextDate), 'retiro', null, $personalNextDate));
finance('DELETE', 'movimientoscuenta/'.$manualEarlier['id'], ['motivo' => 'Alteraria un saldo posterior'], 422);
finance('PATCH', 'movimientoscuenta/'.$manualEarlier['id'], ['monto' => '55'], 422);
finance('POST', 'movimientoscuenta', ['id_cuenta' => $timelineAccount['id'], 'fecha' => $personalDate, 'tipo' => 'cargo',
    'monto' => '1', 'concepto' => 'Retroactivo', 'clave' => 'PERSONAL-RETRO', 'motivo' => 'No debe pasar'], 422);
expectApi(personalBalance($timelineAccount, $personalDate)['saldo'] === '50.00000000'
    && personalBalance($timelineAccount, $personalNextDate)['saldo'] === '-10.00000000', 'Los saldos al corte respetan la fecha del retiro');

// La entrega puede provenir de un movimiento ya registrado en una jornada cerrada.
$personalCashier = $financeCashiers[0];
$cashDay = api('POST', 'arqueos', ['fecha' => $personalDate], $personalCashier, 201)['Arqueo']['id'];
api('PATCH', 'arqueos/'.$cashDay, array_fill_keys(['biller_cordobas', 'biller_dolares', 'envios_cordobas', 'envios_dolares', 'pagos_cordobas', 'pagos_dolares'], 0), $personalCashier);
$cashOut = api('POST', 'entradasysalidas', ['id_arqueo' => $cashDay, 'categoria' => 'gasto', 'tipo' => 'Salida', 'moneda' => 'NIO',
    'monto' => '25', 'medio' => 'efectivo', 'detalle' => 'Entrega por clasificar como prestamo'], $personalCashier, 201)['Entrada salida']['id'];
foreach (['NIO', 'USD'] as $currency) {
    api('POST', 'detallearqueo', ['id_arqueo' => $cashDay, 'moneda' => $currency, 'estado' => 'contado'], $personalCashier, 201);
}
api('POST', 'arqueos/'.$cashDay.'/cerrar', ['confirmar_cheques' => true], $personalCashier);
$cashSnapshot = \App\Models\Arqueo::findOrFail($cashDay)->getAttributes();
$cashOrigin = makeFinance('operaciones', ['clave' => 'PERSONAL-CAJA-ORIGEN', 'fecha' => $personalDate, 'tipo' => 'egreso',
    'moneda' => 'NIO', 'monto' => '25', 'concepto' => 'Prestamo desde caja', 'origen_tipo' => 'entrada_salida', 'id_origen' => $cashOut]);
makeFinance('movimientospersonales', personalInput('PERSONAL-PRESTAMO-CAJA', $receivablePerson, $cashOrigin, 'prestamo_entregado'));
expectApi(\App\Models\Arqueo::findOrFail($cashDay)->getAttributes() === $cashSnapshot, 'El prestamo de caja conserva la jornada cerrada sin descontar dos veces');

$personalLoanPayable = personalAccount('PERSONAL-NUEVA-DEUDA', 'por_pagar');
finance('POST', 'financiamientos', ['referencia' => 'PERSONAL-INGRESO-YA-USADO', 'id_cuenta' => $personalLoanPayable['id'],
    'id_contraparte' => $personalParty['id'], 'fecha' => $personalDate, 'moneda' => 'NIO', 'principal' => '100',
    'periodicidad' => 'mensual', 'id_operacion_desembolso' => $initialDeposit['id']], 422);

// Integra las nuevas cuentas con el consolidado y el cierre financiero del corte.
finance('POST', 'liquidaciones/'.$nextSettlement['id'].'/cerrar');
$personalMother = makeFinance('arqueosmadre', ['fecha' => $personalDate, 'usuarios_esperados' => [$personalCashier->id], 'tasa_referencia' => '36.74']);
$personalTotals = finance('POST', 'arqueosmadre/'.$personalMother['id'].'/cerrar')['Resultado']['Totales']['metricas'];
$personalSettlement = makeFinance('liquidaciones', ['id_arqueo_madre' => $personalMother['id']]);
finance('POST', 'depositosliquidacion', ['id_liquidacion' => $personalSettlement['id'], 'fecha' => $personalDate,
    'id_operacion' => $withdrawMoney['id'], 'id_cuenta_transito' => $transit['id']], 422);
finance('POST', 'liquidaciones/'.$personalSettlement['id'].'/cerrar');
$personalCapitalInput = ['fecha' => $personalDate, 'periodo_desde' => $personalDate,
    'id_arqueo_madre' => $personalMother['id'], 'id_liquidacion' => $personalSettlement['id']];
$personalCapital = finance('GET', 'capital?'.http_build_query($personalCapitalInput))['Resultado'];
$capitalAccounts = array_column($personalCapital['fuentes']['cuentas'], null, 'id');
expectApi($capitalAccounts[$initialFund['id']]['valor_agregado'] === '25.00000000'
    && $capitalAccounts[$fund['id']]['valor_agregado'] === '-387.40000000', 'El capital reconoce deuda del titular y fondos en custodia con signos opuestos');
expectApi($capitalAccounts[$receivablePerson['id']]['saldo'] === personalBalance($receivablePerson)['saldo'],
    'El capital toma la CxC de prestamos entregados sin repetir su principal');
$personalReport = matrixReport('PERSONAL-MATRIZ', $personalDate, $personalTotals);
makeFinance('conciliaciones', ['id_arqueo_madre' => $personalMother['id'], 'id_reporte' => $personalReport['id']]);
$personalClose = makeFinance('cierres', $personalCapitalInput + ['motivo' => 'Prueba del cierre con cuentas personales', 'confirmar_fuentes' => true]);
finance('DELETE', 'movimientospersonales/'.$reclassifiedDeposit['id'], ['motivo' => 'Periodo ya cerrado'], 422);
$historicalBalance = personalBalance($fund)['saldo'];
makeFinance('movimientospersonales', personalInput('PERSONAL-DIA-SIGUIENTE', $fund,
    personalMoney('PERSONAL-DIA-SIGUIENTE-INGRESO', 'ingreso', '5', 'NIO', $personalNextDate), 'deposito', null, $personalNextDate));
expectApi(personalBalance($fund)['saldo'] === $historicalBalance
    && finance('GET', 'cierres/'.$personalClose['id'])['Registro']['datos'] === $personalClose['datos'],
    'El movimiento del dia siguiente conserva el saldo y cierre historicos');

expectApi(protectedApiSnapshot() === $personalProtected, 'Los procesos personales no modifican tablas protegidas');
expectApi($db->select('PRAGMA foreign_key_check') === [], 'Las cuentas personales conservan todas sus referencias');
echo 'OK cuentas personales: '.($checks - $personalChecks)." comprobaciones adicionales; SQLite en memoria.\n";
