<?php

// Usa el mismo router/controladores y base SQLite :memory: de la regresion de caja.
// Nunca carga .env ni se conecta a una base real.
require __DIR__.'/caja_api.php';

use App\Models\Administracion\ArqueoMadre;
use App\Models\Administracion\CuentaFinanciera;
use App\Models\Administracion\GastoAdministrativo;
use App\Models\Administracion\MovimientoCuenta;
use App\Support\Administracion\Finanzas as F;
use App\Support\Administracion\Recursos;

$startFinanceChecks = $checks;
function finance(string $method, string $uri, array $data = [], int $expected = 200): array
{
    global $admin;

    return api($method, 'administracion/'.$uri, $data, $admin, $expected);
}
function makeFinance(string $uri, array $data): array
{
    return finance('POST', $uri, $data, 201)['Registro'];
}
foreach (Recursos::DEFINICIONES as $uri => $definition) {
    finance('GET', $uri);
    api('GET', 'administracion/'.$uri, [], $cashier, 403);
    api('GET', 'administracion/'.$uri, [], null, 401);
}
$inactiveAdmin = clone $admin;
$inactiveAdmin->estado_usuario = 'inactivo';
api('POST', 'administracion/contrapartes', ['codigo' => 'PROHIBIDO'], $inactiveAdmin, 403);

$date = '2026-11-05';
$nextDate = '2026-11-06';
$financeCashiers = [];
foreach ([0, 1] as $number) {
    $worker = $db->table('trabajadores')->insertGetId(['nombre' => 'Finanzas '.$number, 'cedula' => 'FIN'.$number,
        'fecha_nacimiento' => '1990-01-01', 'inss' => 'FIN'.$number, 'nombre_banco' => 'Prueba',
        'numero_cuenta_banco' => 'FIN'.$number, 'estadotrabajador' => 'activo', 'fecha_contratacion' => '2026-01-01',
        'id_subagencia' => $agencies[$number], 'cargo' => 'Cajero', 'salario_basico_inicial' => 0,
        'salario_basico_actual' => 0, 'fecha_finalizacion' => '2099-12-31', 'concepto_finalizacion' => 'Prueba']);
    $id = $db->table('users')->insertGetId(['usuario' => 'finanzas'.$number, 'contraseña' => 'SIN_USO',
        'rol_usuario' => 'Cajero', 'correo_asignado_cajero' => 'fin'.$number.'@example.invalid',
        'usuario_asignado_airpak' => 'FIN'.$number, 'estado_usuario' => 'Activo', 'id_trabajador' => $worker]);
    $financeCashiers[] = \App\Models\User::findOrFail($id);
}
$financeProtected = protectedApiSnapshot();
$days = [];
foreach ($financeCashiers as $number => $owner) {
    $days[] = api('POST', 'arqueos', ['fecha' => $date, 'caja_inicial_cordobas' => $number === 0 ? '1000' : '200',
        'caja_inicial_dolares' => $number === 0 ? '100' : '20', 'cheques_inicial_cordobas' => 0,
        'cheques_inicial_dolares' => 0, 'tasa_referencia' => '36.7', 'motivo_apertura_manual' => 'Prueba financiera'], $owner, 201)['Arqueo']['id'];
    api('PATCH', 'arqueos/'.$days[$number], ['biller_cordobas' => $number === 0 ? '50' : '0',
        'biller_dolares' => $number === 0 ? '2' : '0', 'envios_cordobas' => $number === 0 ? '100' : '0',
        'envios_dolares' => $number === 0 ? '10' : '0', 'pagos_cordobas' => $number === 0 ? '20' : '0',
        'pagos_dolares' => $number === 0 ? '30' : '0'], $owner);
}
$cashExpense = api('POST', 'entradasysalidas', ['id_arqueo' => $days[0], 'categoria' => 'gasto',
    'tipo' => 'Salida', 'moneda' => 'NIO', 'monto' => '10', 'medio' => 'efectivo', 'detalle' => 'Gasto origen financiero'], $financeCashiers[0], 201)['Entrada salida']['id'];
api('POST', 'operacionescambiocaja', ['id_arqueo' => $days[0], 'tipo' => 'Compra', 'monto_dolares' => '5.83', 'tasa' => '36.5'], $financeCashiers[0], 201);
$mother = makeFinance('arqueosmadre', ['fecha' => $date, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $financeCashiers), 'tasa_referencia' => '36.74']);
expectApi($mother['totales']['completo'] === false && $mother['totales']['cobertura']['cerrados'] === 0, 'El preliminar expone fuentes abiertas');
finance('POST', 'arqueosmadre/'.$mother['id'].'/cerrar', [], 422);
finance('POST', 'operaciones', ['fecha' => $date, 'tipo' => 'egreso', 'moneda' => 'NIO', 'monto' => '10',
    'concepto' => 'Origen abierto no valido', 'clave' => 'origen-abierto', 'origen_tipo' => 'entrada_salida', 'id_origen' => $cashExpense], 422);
foreach ($financeCashiers as $number => $owner) {
    api('POST', 'detallearqueo', $number === 0 ? ['id_arqueo' => $days[0], 'moneda' => 'NIO', 'estado' => 'contado', 'cien' => 9, 'cinco' => 1, 'uno' => 2]
        : ['id_arqueo' => $days[1], 'moneda' => 'NIO', 'estado' => 'contado', 'cien' => 2], $owner, 201);
    api('POST', 'detallearqueo', $number === 0 ? ['id_arqueo' => $days[0], 'moneda' => 'USD', 'estado' => 'contado', 'cincuenta' => 1, 'veinte' => 1, 'diez' => 1, 'cinco' => 1, 'dos' => 1]
        : ['id_arqueo' => $days[1], 'moneda' => 'USD', 'estado' => 'contado', 'veinte' => 1], $owner, 201);
    api('POST', 'arqueos/'.$days[$number].'/cerrar', ['confirmar_cheques' => true], $owner);
}
$closedMother = finance('POST', 'arqueosmadre/'.$mother['id'].'/cerrar')['Resultado'];
$totals = $closedMother['Totales']['metricas'];
expectApi($totals['contado_cordobas'] === '1107.00000000' && $totals['contado_dolares'] === '107.00000000', 'Consolidacion sin duplicar subtotales');
expectApi($totals['diferencia_dolares'] === '-0.83000000' && $totals['diferencia_cordobas'] === '-0.20500000', 'Las dos diferencias se suman completas por moneda');
expectApi($totals['wu_cordobas'] === '80.00000000' && $totals['wu_dolares'] === '20.00000000', 'Se conserva la perspectiva de WU de matriz');
expectApi($totals['compras_dolares'] === '5.83000000' && $totals['utilidad_dolares'] === '0.03177112', 'Utilidad desde importes aplicados y referencia individual');
finance('PATCH', 'arqueosmadre/'.$mother['id'], ['tasa_referencia' => '37'], 422);
$secondVersion = makeFinance('arqueosmadre', ['fecha' => $date, 'usuarios_esperados' => [$financeCashiers[0]->id], 'tasa_referencia' => '36.74']);
expectApi($secondVersion['version'] === 2, 'Los cortes conservan versiones');
finance('DELETE', 'arqueosmadre/'.$secondVersion['id'], ['motivo' => 'Version de prueba']);

function matrixReport(string $reference, string $date, array $totals, bool $different = false): array
{
    $report = makeFinance('reportesmatriz', ['fecha' => $date, 'corte_at' => $date.' 18:00:00', 'proveedor' => 'AirPak', 'referencia' => $reference]);
    finance('POST', 'reportesmatriz/'.$report['id'].'/confirmar', [], 422);
    foreach (['wu', 'biller'] as $service) {
        foreach (['NIO' => 'cordobas', 'USD' => 'dolares'] as $currency => $suffix) {
            if ($service === 'wu' && $currency === 'NIO') {
                $discard = makeFinance('detallesreporte', ['id_reporte' => $report['id'], 'servicio' => $service, 'moneda' => $currency, 'monto' => '1']);
                finance('POST', 'detallesreporte', ['id_reporte' => $report['id'], 'servicio' => $service, 'moneda' => $currency, 'monto' => '2'], 422);
                finance('DELETE', 'detallesreporte/'.$discard['id'], ['motivo' => 'Corregir el total antes de confirmar']);
            }
            makeFinance('detallesreporte', ['id_reporte' => $report['id'], 'servicio' => $service, 'moneda' => $currency,
                'monto' => $different && $service === 'biller' && $currency === 'NIO' ? F::add($totals[$service.'_'.$suffix], 1) : $totals[$service.'_'.$suffix]]);
        }
    }
    finance('POST', 'reportesmatriz/'.$report['id'].'/confirmar');

    return $report;
}
$report = matrixReport('FIN-R1', $date, $totals);
$reconciliation = makeFinance('conciliaciones', ['id_arqueo_madre' => $mother['id'], 'id_reporte' => $report['id']]);
expectApi($reconciliation['estado'] === 'conciliado', 'Cuatro totales iguales se concilian');
$otherReport = matrixReport('FIN-R2', $date, $totals, true);
$difference = makeFinance('conciliaciones', ['id_arqueo_madre' => $mother['id'], 'id_reporte' => $otherReport['id']]);
expectApi($difference['estado'] === 'con_diferencias', 'La conciliacion preserva diferencias');
$review = finance('POST', 'conciliaciones/'.$difference['id'].'/revisar', ['motivo' => 'Diferencia conocida pendiente de ajuste'])['Resultado'];
expectApi($review['estado'] === 'revisado' && $review['resultado']['coincide'] === false, 'Revisar no borra la diferencia');

$party = makeFinance('contrapartes', ['codigo' => 'FIN-P', 'nombre' => 'Contraparte de prueba', 'tipo' => 'persona']);
$document = makeFinance('documentos', ['nombre' => 'Documento prueba', 'ubicacion' => 'documentos/prueba.pdf', 'sha256' => str_repeat('a', 64), 'tipo' => 'contrato']);
finance('POST', 'documentos', ['nombre' => 'Hash malo', 'ubicacion' => 'x', 'sha256' => '123', 'tipo' => 'contrato'], 422);
$center = makeFinance('centroscosto', ['codigo' => 'FIN-C', 'nombre' => 'Centro de prueba', 'id_subagencia' => $agencies[0]]);
function account(string $code, string $type, string $currency, string $initial, string $rubro): array
{
    global $date, $party;

    return makeFinance('cuentas', ['codigo' => $code, 'nombre' => $code, 'tipo' => $type, 'moneda' => $currency,
        'fecha_apertura' => $date, 'saldo_inicial' => $initial, 'rubro_capital' => $rubro, 'id_contraparte' => $party['id']]);
}
$bankNio = account('FIN-BN', 'banco', 'NIO', '10000', 'banco');
$bankUsd = account('FIN-BU', 'banco', 'USD', '1000', 'banco');
$receivable = account('FIN-CXC', 'por_cobrar', 'NIO', '0', 'pendiente');
$payable = account('FIN-CXP', 'por_pagar', 'NIO', '0', 'pendiente');
$debt = account('FIN-DEUDA', 'por_pagar', 'USD', '0', 'deuda');
$transit = account('FIN-TRANSITO', 'por_cobrar', 'NIO', '0', 'pendiente');
function moneyOperation(string $key, string $type, string $currency, string $amount, ?int $origin, ?int $destination): array
{
    global $date;

    return makeFinance('operaciones', ['fecha' => $date, 'tipo' => $type, 'moneda' => $currency, 'monto' => $amount,
        'concepto' => $key, 'clave' => $key, 'id_cuenta_origen' => $origin, 'id_cuenta_destino' => $destination]);
}
$cashBefore = \App\Models\Arqueo::findOrFail($days[0])->getAttributes();
$origin = makeFinance('operaciones', ['fecha' => $date, 'tipo' => 'egreso', 'moneda' => 'NIO', 'monto' => '10',
    'concepto' => 'Clasificar gasto de caja', 'clave' => 'FIN-CAJA-1', 'origen_tipo' => 'entrada_salida', 'id_origen' => $cashExpense]);
finance('POST', 'operaciones', ['fecha' => $date, 'tipo' => 'egreso', 'moneda' => 'NIO', 'monto' => '10',
    'concepto' => 'Duplicado', 'clave' => 'FIN-CAJA-2', 'origen_tipo' => 'entrada_salida', 'id_origen' => $cashExpense], 409);
expectApi(\App\Models\Arqueo::findOrFail($days[0])->getAttributes() === $cashBefore, 'Clasificar no vuelve a mover el arqueo cerrado');
finance('DELETE', 'operaciones/'.$origin['id'], ['motivo' => 'Corregir clasificacion antes de aplicarla']);
$origin = makeFinance('operaciones', ['fecha' => $date, 'tipo' => 'egreso', 'moneda' => 'NIO', 'monto' => '10',
    'concepto' => 'Origen reclasificado', 'clave' => 'FIN-CAJA-REEMPLAZO', 'origen_tipo' => 'entrada_salida', 'id_origen' => $cashExpense]);
expectApi(\App\Models\Administracion\OperacionFinanciera::where('id_entrada_salida', $cashExpense)->count() === 2,
    'Reclasificar conserva el origen anulado y un solo reconocimiento activo');
$expense = makeFinance('gastos', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'ambito' => 'operativo', 'categoria' => 'materiales', 'concepto' => 'Gasto clasificado', 'moneda' => 'NIO', 'monto' => '10', 'id_centro_costo' => $center['id']]);
$expenseApplication = makeFinance('aplicaciones', ['id_operacion' => $origin['id'], 'fecha' => $date, 'monto' => '10', 'id_gasto' => $expense['id']]);
finance('POST', 'aplicaciones', ['id_operacion' => $origin['id'], 'fecha' => $date, 'monto' => '1', 'id_gasto' => $expense['id']], 422);
finance('PATCH', 'gastos/'.$expense['id'], ['monto' => '11'], 422);

$charge = makeFinance('movimientoscuenta', ['id_cuenta' => $receivable['id'], 'fecha' => $date, 'tipo' => 'cargo', 'monto' => '100',
    'concepto' => 'Saldo por cobrar', 'clave' => 'FIN-CARGO', 'motivo' => 'Cargo historico verificado']);
$receipt = moneyOperation('FIN-COBRO', 'ingreso', 'NIO', '60', null, $bankNio['id']);
makeFinance('aplicaciones', ['id_operacion' => $receipt['id'], 'fecha' => $date, 'monto' => '60', 'id_cargo' => $charge['id']]);
$balance = finance('GET', 'cuentas/'.$receivable['id'].'/saldo?fecha='.$date)['Resultado'];
expectApi($balance['saldo'] === '40.00000000' && $balance['movimientos'][0]['pendiente_aplicacion'] === '40.00000000', 'Cobro parcial actualiza saldo y aplicacion');

$loanMoney = moneyOperation('FIN-PRESTAMO', 'ingreso', 'USD', '1000', null, $bankUsd['id']);
$loan = makeFinance('financiamientos', ['referencia' => 'FIN-LOAN', 'id_cuenta' => $debt['id'], 'id_contraparte' => $party['id'],
    'fecha' => $date, 'moneda' => 'USD', 'principal' => '1000', 'periodicidad' => 'mensual', 'numero_cuotas' => 10, 'id_operacion_desembolso' => $loanMoney['id']]);
$quota = makeFinance('cuotas', ['id_financiamiento' => $loan['id'], 'numero' => 1, 'fecha' => $date,
    'capital' => '100', 'interes' => '10', 'seguro' => '2', 'comision' => '1']);
finance('DELETE', 'cuotas/'.$quota['id'], ['motivo' => 'Revisar el calendario antes de pagar']);
$quota = makeFinance('cuotas', ['id_financiamiento' => $loan['id'], 'numero' => 1, 'fecha' => $date,
    'capital' => '100', 'interes' => '10', 'seguro' => '2', 'comision' => '1']);
finance('POST', 'cuotas', ['id_financiamiento' => $loan['id'], 'numero' => 1, 'fecha' => $date, 'capital' => '1'], 422);
expectApi($quota['monto'] === '113.00000000', 'El total de cuota lo calcula el servidor');
$quotaPayment = moneyOperation('FIN-PAGO-CUOTA', 'egreso', 'USD', '113', $bankUsd['id'], null);
finance('POST', 'aplicaciones', ['id_operacion' => $quotaPayment['id'], 'fecha' => $date, 'monto' => '50', 'id_cuota' => $quota['id']], 422);
$partial = makeFinance('aplicaciones', ['id_operacion' => $quotaPayment['id'], 'fecha' => $date, 'monto' => '50', 'id_cuota' => $quota['id'],
    'componentes' => ['capital' => '40', 'interes' => '8', 'seguro' => '1', 'comision' => '1']]);
makeFinance('aplicaciones', ['id_operacion' => $quotaPayment['id'], 'fecha' => $date, 'monto' => '63', 'id_cuota' => $quota['id'],
    'componentes' => ['capital' => '60', 'interes' => '2', 'seguro' => '1', 'comision' => '0']]);
expectApi(finance('GET', 'cuentas/'.$debt['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '900.00000000', 'Solo el principal reduce la deuda');
expectApi(F::sum(GastoAdministrativo::where('categoria', 'gastos_financiamiento')->where('estado', 'registrado')->get(), 'monto') === '13.00000000', 'Interes, seguro y comision se clasifican una vez');
finance('DELETE', 'aplicaciones/'.$partial['id'], ['motivo' => 'Correccion de distribucion']);
expectApi(finance('GET', 'cuentas/'.$debt['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '940.00000000', 'Anular restaura el principal y libera la aplicacion');
makeFinance('aplicaciones', ['id_operacion' => $quotaPayment['id'], 'fecha' => $date, 'monto' => '50', 'id_cuota' => $quota['id'],
    'componentes' => ['capital' => '40', 'interes' => '8', 'seguro' => '1', 'comision' => '1']]);
expectApi(finance('GET', 'cuentas/'.$debt['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '900.00000000', 'Reaplicar no duplica pagos bancarios');

$commissionAccount = account('FIN-COMISION', 'por_cobrar', 'NIO', '0', 'pendiente');
$commission = makeFinance('comisiones', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'tipo' => 'proveedor', 'naturaleza' => 'cobrar', 'id_cuenta' => $commissionAccount['id'], 'moneda' => 'NIO', 'monto' => '100']);
$commissionMoney = moneyOperation('FIN-COMI', 'ingreso', 'NIO', '100', null, $bankNio['id']);
makeFinance('aplicaciones', ['fecha' => $date, 'id_operacion' => $commissionMoney['id'], 'monto' => '40', 'id_comision' => $commission['id']]);
expectApi(finance('GET', 'cuentas/'.$commissionAccount['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '60.00000000', 'La comision recibida puede ser parcial');
$workerId = $financeCashiers[0]->id_trabajador;
$employeeClaim = account('FIN-ANT', 'por_cobrar', 'NIO', '0', 'pendiente');
$employeeDebt = account('FIN-SALARIO', 'por_pagar', 'NIO', '0', 'pendiente');
$advanceMoney = moneyOperation('FIN-ANTICIPO-EGRESO', 'egreso', 'NIO', '100', $bankNio['id'], null);
$advance = makeFinance('obligacioneslaborales', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'id_trabajador' => $workerId, 'id_cuenta' => $employeeClaim['id'], 'concepto' => 'anticipo', 'moneda' => 'NIO', 'monto' => '100',
    'id_operacion_anticipo' => $advanceMoney['id']]);
finance('POST', 'aplicaciones', ['id_operacion' => $advanceMoney['id'], 'fecha' => $date, 'monto' => '10', 'id_gasto' => $expense['id']], 422);
finance('DELETE', 'operaciones/'.$advanceMoney['id'], ['motivo' => 'Ya esta reservado'], 422);
$salary = makeFinance('obligacioneslaborales', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'id_trabajador' => $workerId, 'id_cuenta' => $employeeDebt['id'], 'concepto' => 'salario', 'moneda' => 'NIO', 'monto' => '500']);
finance('PATCH', 'obligacioneslaborales/'.$salary['id'], ['concepto' => 'anticipo'], 422);
$compensation = makeFinance('compensacioneslaborales', ['id_anticipo' => $advance['id'], 'id_obligacion' => $salary['id'], 'fecha' => $date, 'monto' => '60']);
expectApi(finance('GET', 'cuentas/'.$employeeClaim['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '40.00000000'
    && finance('GET', 'cuentas/'.$employeeDebt['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '440.00000000', 'El descuento reduce deuda y anticipo sin nuevo dinero');
finance('POST', 'compensacioneslaborales', ['id_anticipo' => $advance['id'], 'id_obligacion' => $salary['id'], 'fecha' => $date, 'monto' => '50'], 422);
finance('DELETE', 'compensacioneslaborales/'.$compensation['id'], ['motivo' => 'Revisar importe de descuento']);
expectApi(finance('GET', 'cuentas/'.$employeeClaim['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '100.00000000', 'Anular compensacion restaura el anticipo');
makeFinance('compensacioneslaborales', ['id_anticipo' => $advance['id'], 'id_obligacion' => $salary['id'], 'fecha' => $date, 'monto' => '60']);
$salaryMoney = moneyOperation('FIN-SALARIO-PAGO', 'egreso', 'NIO', '440', $bankNio['id'], null);
makeFinance('aplicaciones', ['id_operacion' => $salaryMoney['id'], 'fecha' => $date, 'monto' => '440', 'id_obligacion' => $salary['id']]);
expectApi(finance('GET', 'cuentas/'.$employeeDebt['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '0.00000000', 'El salario pendiente se paga una sola vez');
expectApi(GastoAdministrativo::where('origen_automatico', 'obligacioneslaborales:'.$salary['id'])->where('estado', 'registrado')->count() === 1,
    'La obligacion salarial genera una sola clasificacion de gasto');

$grossSalary = makeFinance('obligacioneslaborales', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'id_trabajador' => $workerId, 'id_cuenta' => $employeeDebt['id'], 'concepto' => 'salario', 'moneda' => 'NIO', 'monto' => '500']);
$inssAccount = account('FIN-INSS', 'por_pagar', 'NIO', '0', 'pendiente');
$inss = makeFinance('obligacioneslaborales', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'id_trabajador' => $workerId, 'id_cuenta' => $inssAccount['id'], 'concepto' => 'inss_laboral', 'moneda' => 'NIO', 'monto' => '50']);
finance('POST', 'compensacioneslaborales', ['id_anticipo' => $advance['id'], 'id_retencion' => $inss['id'],
    'id_obligacion' => $grossSalary['id'], 'fecha' => $date, 'monto' => '1'], 422);
$withholding = makeFinance('compensacioneslaborales', ['id_retencion' => $inss['id'], 'id_obligacion' => $grossSalary['id'], 'fecha' => $date, 'monto' => '50']);
expectApi(finance('GET', 'cuentas/'.$employeeDebt['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '450.00000000'
    && finance('GET', 'cuentas/'.$inssAccount['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '50.00000000',
    'La retencion deja salario neto por pagar y conserva la deuda de INSS');
finance('POST', 'compensacioneslaborales', ['id_retencion' => $inss['id'], 'id_obligacion' => $grossSalary['id'], 'fecha' => $date, 'monto' => '1'], 422);
finance('DELETE', 'compensacioneslaborales/'.$withholding['id'], ['motivo' => 'Distribuir el descuento en dos aplicaciones']);
makeFinance('compensacioneslaborales', ['id_retencion' => $inss['id'], 'id_obligacion' => $grossSalary['id'], 'fecha' => $date, 'monto' => '20']);
makeFinance('compensacioneslaborales', ['id_retencion' => $inss['id'], 'id_obligacion' => $grossSalary['id'], 'fecha' => $date, 'monto' => '30']);
$netPay = moneyOperation('FIN-NETO-SALARIAL', 'egreso', 'NIO', '450', $bankNio['id'], null);
makeFinance('aplicaciones', ['id_operacion' => $netPay['id'], 'fecha' => $date, 'monto' => '450', 'id_obligacion' => $grossSalary['id']]);
$inssPay = moneyOperation('FIN-PAGO-INSS', 'egreso', 'NIO', '50', $bankNio['id'], null);
makeFinance('aplicaciones', ['id_operacion' => $inssPay['id'], 'fecha' => $date, 'monto' => '50', 'id_obligacion' => $inss['id']]);
expectApi(! GastoAdministrativo::where('origen_automatico', 'obligacioneslaborales:'.$inss['id'])->exists(), 'La retencion laboral no duplica el gasto salarial bruto');

$project = makeFinance('proyectos', ['codigo' => 'FIN-OBRA', 'nombre' => 'Obra de prueba', 'fecha' => $date]);
$workAdvance = makeFinance('movimientosobra', ['id_proyecto' => $project['id'], 'fecha' => $date,
    'tipo' => 'anticipo', 'partida' => 'mano_obra', 'concepto' => 'Anticipo contratista', 'moneda' => 'NIO', 'monto' => '100']);
$workCost = makeFinance('movimientosobra', ['id_proyecto' => $project['id'], 'fecha' => $date,
    'tipo' => 'costo', 'partida' => 'mano_obra', 'concepto' => 'Costo certificado', 'moneda' => 'NIO', 'cantidad' => '2', 'precio_unitario' => '100']);
expectApi($workCost['monto'] === '200.00000000', 'El costo se calcula a partir de cantidad y precio');
finance('POST', 'aplicacionesobra', ['id_anticipo' => $workAdvance['id'], 'id_costo' => $workCost['id'], 'fecha' => $date, 'monto' => '100'], 422);
$workMoney = moneyOperation('FIN-OBRA-PAGO', 'egreso', 'NIO', '100', $bankNio['id'], null);
makeFinance('aplicaciones', ['id_operacion' => $workMoney['id'], 'fecha' => $date, 'monto' => '100', 'id_obra' => $workAdvance['id']]);
makeFinance('aplicacionesobra', ['id_anticipo' => $workAdvance['id'], 'id_costo' => $workCost['id'], 'fecha' => $date, 'monto' => '40']);
makeFinance('aplicacionesobra', ['id_anticipo' => $workAdvance['id'], 'id_costo' => $workCost['id'], 'fecha' => $date, 'monto' => '60']);
makeFinance('movimientosobra', ['id_proyecto' => $project['id'], 'fecha' => $date, 'tipo' => 'recuperacion', 'partida' => 'otro', 'concepto' => 'Alquiler recuperado', 'moneda' => 'NIO', 'monto' => '20']);
$workSummary = finance('GET', 'proyectos/'.$project['id'].'/resumen?fecha='.$date)['Resultado']['Totales'];
expectApi($workSummary['neto']['NIO'] === '180.00000000', 'El anticipo aplicado no duplica el costo y la recuperacion se distingue');

$asset = makeFinance('activos', ['codigo' => 'FIN-PC', 'nombre' => 'Computadoras', 'categoria' => 'equipo',
    'fecha' => $date, 'id_subagencia' => $agencies[0], 'moneda' => 'NIO', 'cantidad' => '2', 'precio_unitario' => '100']);
makeFinance('eventosactivos', ['id_activo' => $asset['id'], 'fecha' => $date, 'tipo' => 'traslado', 'cantidad' => '1',
    'id_subagencia_origen' => $agencies[0], 'id_subagencia_destino' => $agencies[1], 'concepto' => 'Traslado de equipo']);
finance('POST', 'eventosactivos', ['id_activo' => $asset['id'], 'fecha' => $date, 'tipo' => 'baja', 'cantidad' => '2',
    'id_subagencia_origen' => $agencies[0], 'concepto' => 'Cantidad excedida'], 422);
makeFinance('eventosactivos', ['id_activo' => $asset['id'], 'fecha' => $date, 'tipo' => 'baja', 'cantidad' => '1',
    'id_subagencia_origen' => $agencies[1], 'concepto' => 'Baja de equipo']);
$inventory = finance('GET', 'activos/'.$asset['id'].'/inventario?fecha='.$date)['Resultado']['ubicaciones'];
expectApi(F::cmp($inventory[$agencies[0]], 1) === 0 && F::cmp($inventory[$agencies[1]], 0) === 0, 'Ubicaciones, traslados y bajas conservan cantidades');

$caseAccount = account('FIN-CASO', 'por_cobrar', 'NIO', '0', 'pendiente');
$case = makeFinance('incidencias', ['fecha' => $date, 'referencia' => 'FIN-INC', 'servicio' => 'biller', 'id_arqueo' => $days[0],
    'moneda' => 'NIO', 'monto' => '30', 'id_cuenta' => $caseAccount['id'], 'motivo_incidencia' => 'Biller duplicado']);
makeFinance('gestionesincidencia', ['id_incidencia' => $case['id'], 'fecha' => $date, 'gestion' => 'confirmado', 'detalle' => 'Anulacion confirmada por el proveedor']);
finance('PATCH', 'incidencias/'.$case['id'], ['ticket' => 'TICKET-01', 'caso' => 'CASO-01']);
finance('POST', 'gestionesincidencia', ['id_incidencia' => $case['id'], 'fecha' => $date, 'gestion' => 'cerrado', 'detalle' => 'Cerrar sin reembolso'], 422);
$refund = moneyOperation('FIN-REEMBOLSO', 'ingreso', 'NIO', '30', null, $bankNio['id']);
$refundApplication = makeFinance('aplicaciones', ['id_operacion' => $refund['id'], 'fecha' => $date, 'monto' => '30', 'id_incidencia' => $case['id']]);
makeFinance('gestionesincidencia', ['id_incidencia' => $case['id'], 'fecha' => $date, 'gestion' => 'cerrado', 'detalle' => 'Reembolso aplicado y conciliado']);
finance('DELETE', 'aplicaciones/'.$refundApplication['id'], ['motivo' => 'La incidencia sigue cerrada'], 422);
expectApi(finance('GET', 'incidencias/'.$case['id'].'/resumen?fecha='.$date)['Resultado']['Pendiente'] === '0.00000000', 'Confirmacion y recuperacion financiera son eventos diferentes');

$convertedExpense = makeFinance('gastos', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'ambito' => 'operativo', 'categoria' => 'conversion', 'concepto' => 'Gasto expresado en USD', 'moneda' => 'USD', 'monto' => '10']);
$convertedMoney = moneyOperation('FIN-PAGO-CONVERTIDO', 'egreso', 'NIO', '367.4', $bankNio['id'], null);
$conversionInput = ['fecha' => $date, 'id_operacion' => $convertedMoney['id'], 'monto' => '367.4', 'id_gasto' => $convertedExpense['id']];
finance('POST', 'aplicaciones', $conversionInput, 422);
$converted = makeFinance('aplicaciones', $conversionInput + ['tasa_cambio' => '36.74']);
expectApi($converted['monto_destino'] === '10.00000000', 'Una aplicacion en otra moneda exige tasa y conserva los dos importes');
finance('POST', 'aplicaciones', $conversionInput + ['monto_destino' => '1'], 422);
$unpaidExpense = makeFinance('gastos', ['fecha' => $date, 'periodo_desde' => $date, 'periodo_hasta' => $date,
    'ambito' => 'operativo', 'categoria' => 'devengado', 'concepto' => 'Gasto del corte aun sin pago', 'moneda' => 'NIO', 'monto' => '10',
    'id_cuenta' => $payable['id']]);
$expensePayment = moneyOperation('FIN-GASTO-PARCIAL', 'egreso', 'NIO', '6', $bankNio['id'], null);
$auxExpenseApplication = makeFinance('aplicaciones', ['id_operacion' => $expensePayment['id'], 'fecha' => $date, 'monto' => '6', 'id_gasto' => $unpaidExpense['id']]);
expectApi(finance('GET', 'cuentas/'.$payable['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '4.00000000', 'El gasto por pagar crea su cargo y un pago parcial reduce la deuda');
finance('DELETE', 'aplicaciones/'.$auxExpenseApplication['id'], ['motivo' => 'Revisar la imputacion del pago']);
expectApi(finance('GET', 'cuentas/'.$payable['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '10.00000000', 'La correccion restaura la cuenta del gasto sin otro movimiento bancario');
makeFinance('aplicaciones', ['id_operacion' => $expensePayment['id'], 'fecha' => $date, 'monto' => '6', 'id_gasto' => $unpaidExpense['id']]);

$settlement = makeFinance('liquidaciones', ['id_arqueo_madre' => $mother['id'], 'saldo_anterior_usd' => '100', 'motivo_saldo_inicial' => 'Primer arrastre de prueba']);
finance('DELETE', 'liquidaciones/'.$settlement['id'], ['motivo' => 'Revisar el arrastre inicial antes de cerrar']);
$settlement = makeFinance('liquidaciones', ['id_arqueo_madre' => $mother['id'], 'saldo_anterior_usd' => '100', 'motivo_saldo_inicial' => 'Arrastre inicial revisado']);
$depositMoney = moneyOperation('FIN-DEPOSITO-MATRIZ', 'egreso', 'NIO', '1000', $bankNio['id'], null);
finance('POST', 'depositosliquidacion', ['id_liquidacion' => $settlement['id'], 'id_operacion' => $depositMoney['id'], 'fecha' => $date], 422);
$deposit = makeFinance('depositosliquidacion', ['id_liquidacion' => $settlement['id'], 'id_operacion' => $depositMoney['id'],
    'id_cuenta_transito' => $transit['id'], 'fecha' => $date]);
expectApi(finance('GET', 'cuentas/'.$transit['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '1000.00000000', 'El deposito no acreditado permanece como fondos en transito');
finance('POST', 'aplicaciones', ['id_operacion' => $depositMoney['id'], 'fecha' => $date, 'monto' => '1', 'id_gasto' => $expense['id']], 422);
$settlementClosed = finance('POST', 'liquidaciones/'.$settlement['id'].'/cerrar')['Resultado']['Resumen'];
expectApi($settlementClosed['depositos_acreditados_usd'] === '0.00000000' && count($settlementClosed['depositos_pendientes']) === 1, 'La liquidacion no acredita dinero pendiente');
$capitalQuery = 'capital?fecha='.$date.'&periodo_desde='.$date.'&id_arqueo_madre='.$mother['id'].'&id_liquidacion='.$settlement['id'];
$capital = finance('GET', $capitalQuery)['Resultado'];
expectApi($capital['componentes']['comisiones_recibidas']['NIO'] === '40.00000000', 'El capital descuenta comisiones efectivamente cobradas');
expectApi($capital['componentes']['obra_neta']['NIO'] === '180.00000000' && $capital['componentes']['activos']['NIO'] === '100.00000000', 'Capital incorpora obra neta y activos vigentes');
expectApi($capital['capital_grupo_ajustado_usd'] === F::add($capital['neto_usd'], F::div($capital['neto_nio'], '36.74')), 'El indicador reproduce la formula de capital ajustado');
expectApi($capital['capital_trabajo_excel'] === null, 'El capital de trabajo no supone cuales bancos usaba la plantilla');
$workingCapital = finance('GET', $capitalQuery.'&cuentas_capital_trabajo[]='.$bankNio['id'].'&cuentas_capital_trabajo[]='.$bankUsd['id'])['Resultado']['capital_trabajo_excel'];
expectApi($workingCapital['valor_usd'] === F::add(F::add(F::div(F::add('1107', $workingCapital['bancos']['NIO']), '36.74'),
    F::add('107', $workingCapital['bancos']['USD'])), $settlementClosed['proyeccion_usd']), 'Capital de trabajo replica I62 con bancos seleccionados explicitamente');
finance('GET', $capitalQuery.'&cuentas_capital_trabajo[]='.$receivable['id'], [], 422);
finance('POST', 'cierres', ['fecha' => $date, 'periodo_desde' => $date, 'id_arqueo_madre' => $mother['id'],
    'id_liquidacion' => $settlement['id'], 'motivo' => 'Sin confirmar'], 422);
$financialClose = makeFinance('cierres', ['fecha' => $date, 'periodo_desde' => $date, 'id_arqueo_madre' => $mother['id'],
    'id_liquidacion' => $settlement['id'], 'motivo' => 'Fuentes de prueba revisadas', 'confirmar_fuentes' => true,
    'cuentas_capital_trabajo' => [$bankNio['id'], $bankUsd['id']]]);
expectApi($financialClose['datos']['capital_grupo_ajustado_usd'] === $capital['capital_grupo_ajustado_usd'], 'El cierre guarda el informe exacto y sus fuentes');
expectApi($financialClose['datos']['capital_trabajo_excel'] === $workingCapital, 'El cierre conserva tambien los bancos seleccionados para capital de trabajo');
finance('POST', 'operaciones', ['fecha' => $date, 'tipo' => 'ingreso', 'moneda' => 'NIO', 'monto' => '1',
    'concepto' => 'Retroactivo prohibido', 'clave' => 'RETRO', 'id_cuenta_destino' => $bankNio['id']], 422);
finance('DELETE', 'aplicaciones/'.$expenseApplication['id'], ['motivo' => 'Anular en fecha cerrada'], 422);
finance('PATCH', 'gastos/'.$unpaidExpense['id'], ['fecha' => $nextDate], 422);
finance('PATCH', 'cuentas/'.$bankNio['id'], ['rubro_capital' => 'excluido'], 422);

finance('POST', 'depositosliquidacion/'.$deposit['id'].'/acreditar', ['fecha_acreditacion' => $nextDate]);
expectApi(finance('GET', 'cuentas/'.$transit['id'].'/saldo?fecha='.$date)['Resultado']['saldo'] === '1000.00000000'
    && finance('GET', 'cuentas/'.$transit['id'].'/saldo?fecha='.$nextDate)['Resultado']['saldo'] === '0.00000000', 'La acreditacion posterior no altera el saldo historico');
expectApi(finance('GET', 'liquidaciones/'.$settlement['id'].'/resumen')['Resultado']['Resumen'] === $settlementClosed, 'El cierre anterior conserva los depositos que conocia');
expectApi(finance('GET', 'cierres/'.$financialClose['id'])['Registro']['datos'] === $financialClose['datos'], 'La fotografia financiera no cambia con eventos posteriores');
foreach ($financeCashiers as $number => $owner) {
    $newDay = api('POST', 'arqueos', ['fecha' => $nextDate], $owner, 201)['Arqueo']['id'];
    api('PATCH', 'arqueos/'.$newDay, ['biller_cordobas' => 0, 'biller_dolares' => 0, 'envios_cordobas' => 0,
        'envios_dolares' => 0, 'pagos_cordobas' => 0, 'pagos_dolares' => 0], $owner);
    api('POST', 'detallearqueo', $number === 0 ? ['id_arqueo' => $newDay, 'moneda' => 'NIO', 'estado' => 'contado', 'cien' => 9, 'cinco' => 1, 'uno' => 2]
        : ['id_arqueo' => $newDay, 'moneda' => 'NIO', 'estado' => 'contado', 'cien' => 2], $owner, 201);
    api('POST', 'detallearqueo', $number === 0 ? ['id_arqueo' => $newDay, 'moneda' => 'USD', 'estado' => 'contado', 'cincuenta' => 1, 'veinte' => 1, 'diez' => 1, 'cinco' => 1, 'dos' => 1]
        : ['id_arqueo' => $newDay, 'moneda' => 'USD', 'estado' => 'contado', 'veinte' => 1], $owner, 201);
    api('POST', 'arqueos/'.$newDay.'/cerrar', ['confirmar_cheques' => true], $owner);
}
$nextMother = makeFinance('arqueosmadre', ['fecha' => $nextDate, 'usuarios_esperados' => array_map(fn ($u) => $u->id, $financeCashiers), 'tasa_referencia' => '36.74']);
finance('POST', 'arqueosmadre/'.$nextMother['id'].'/cerrar');
$nextSettlement = makeFinance('liquidaciones', ['id_arqueo_madre' => $nextMother['id']]);
$nextSettlementSummary = finance('GET', 'liquidaciones/'.$nextSettlement['id'].'/resumen')['Resultado']['Resumen'];
expectApi($nextSettlement['saldo_anterior_usd'] === F::sub(0, $settlementClosed['pendiente_usd']), 'La liquidacion arrastra el pendiente con la perspectiva correcta');
expectApi($nextSettlementSummary['depositos_acreditados_usd'] === F::div('1000', '36.74'), 'La acreditacion de un deposito anterior se reconoce una vez en el siguiente corte');
expectApi(protectedApiSnapshot() === $financeProtected, 'La administracion no modifica las tablas protegidas');
expectApi($db->select('PRAGMA foreign_key_check') === [], 'No hay referencias financieras huerfanas');
echo 'OK administracion: '.($checks - $startFinanceChecks)." comprobaciones nuevas, ademas de la regresion de caja; SQLite en memoria.\n";
