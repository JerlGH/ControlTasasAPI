<?php

require __DIR__.'/administracion_api.php';

use App\Models\Administracion\ArqueoMadre;
use App\Models\Administracion\CierreFinanciero;

$startExternalCapital = $checks;
$nextRoot = ArqueoMadre::findOrFail($nextMother['id']);
$nextReport = matrixReport('CAPITAL-EXTERNOS', $nextDate, $nextRoot->totales['metricas']);
makeFinance('conciliaciones', ['id_arqueo_madre' => $nextRoot->id, 'id_reporte' => $nextReport['id']]);
finance('POST', 'liquidaciones/'.$nextSettlement['id'].'/cerrar');
$query = 'capital?fecha='.$nextDate.'&periodo_desde='.$nextDate.'&id_arqueo_madre='.$nextRoot->id.'&id_liquidacion='.$nextSettlement['id'];
$baselineCapital = finance('GET', $query)['Resultado'];
expectApi($baselineCapital['pendientes'] === [], 'El capital de base no debe tener pendientes');
$externalDay = makeFinance('arqueosserviciosexternos', ['fecha' => $nextDate, 'agencias_esperadas' => [$agencies[0]]]);
$externalRow = makeFinance('transaccionesexternas', ['id_arqueo_externo' => $externalDay['id'], 'clave' => 'CAPITAL-UNA',
    'id_subagencia' => $agencies[0], 'nombre_cajero' => 'Captura central', 'banco' => 'lafise', 'servicio' => 'remesa',
    'tipo' => 'Entrada', 'moneda' => 'USD', 'numero_referencia' => '001-ABC', 'monto' => '100']);
$capitalWithDraft = finance('GET', $query)['Resultado'];
expectApi($capitalWithDraft['pendientes'][0]['tipo'] === 'servicios_externos_sin_cerrar', 'El cierre financiero detecta la terminal pendiente');
$closeBody = ['fecha' => $nextDate, 'periodo_desde' => $nextDate, 'id_arqueo_madre' => $nextRoot->id,
    'id_liquidacion' => $nextSettlement['id'], 'confirmar_fuentes' => true, 'motivo' => 'Corte con terminal revisada'];
$blocked = finance('POST', 'cierres', $closeBody, 422);
expectApi(isset($blocked['errors']['cierre']), 'Un borrador de la terminal bloquea el cierre financiero');
$summary = finance('GET', 'arqueosserviciosexternos/'.$externalDay['id'].'/resumen')['Resultado'];
finance('POST', 'arqueosserviciosexternos/'.$externalDay['id'].'/cerrar', ['confirmar_registros' => true, 'huella' => $summary['huella']]);
$unrecognized = finance('GET', $query)['Resultado'];
expectApi($unrecognized['pendientes'][0]['tipo'] === 'comisiones_externas_sin_reconocer', 'El cierre externo no inventa la cuenta por cobrar');
expectApi($unrecognized['componentes']['pendientes_netos'] === $baselineCapital['componentes']['pendientes_netos'], 'No existe ingreso duplicado antes del devengo');
finance('POST', 'cierres', $closeBody, 422);
$provider = makeFinance('contrapartes', ['codigo' => 'CAP-LAFISE', 'nombre' => 'LAFISE', 'tipo' => 'banco']);
$receivable = makeFinance('cuentas', ['codigo' => 'CAP-COM-LAFISE', 'nombre' => 'Comisiones LAFISE', 'tipo' => 'por_cobrar',
    'moneda' => 'NIO', 'rubro_capital' => 'pendiente', 'fecha_apertura' => $nextDate, 'saldo_inicial' => '0', 'id_contraparte' => $provider['id']]);
$commissionBody = ['fecha' => $nextDate, 'cuentas' => [['banco' => 'lafise', 'id_cuenta' => $receivable['id']]]];
$firstCommission = finance('POST', 'arqueosserviciosexternos/'.$externalDay['id'].'/registrarcomisiones', $commissionBody)['Resultado']['comisiones'][0];
$recognized = finance('GET', $query)['Resultado'];
expectApi($recognized['pendientes'] === [] && $recognized['componentes']['pendientes_netos']['NIO']
    === \App\Support\Administracion\Finanzas::add($baselineCapital['componentes']['pendientes_netos']['NIO'], '20'),
    'El capital incorpora una sola cuenta por cobrar de C$20');
expectApi($recognized['fuentes']['servicios_externos'][0]['comisiones'][0]['id_comision'] === $firstCommission['id'], 'La fuente del capital identifica el devengo');
finance('DELETE', 'comisiones/'.$firstCommission['id'], ['motivo' => 'Corregir la cuenta antes de cobrar']);
expectApi(finance('GET', $query)['Resultado']['pendientes'][0]['tipo'] === 'comisiones_externas_sin_reconocer', 'Anular la comision sin cobrar vuelve a señalar el devengo pendiente');
$secondCommission = finance('POST', 'arqueosserviciosexternos/'.$externalDay['id'].'/registrarcomisiones', $commissionBody)['Resultado']['comisiones'][0];
expectApi($secondCommission['id'] !== $firstCommission['id'] && $secondCommission['clave_externa_activa'] === $externalDay['id'].':lafise',
    'La correccion conserva el documento anterior y permite uno vigente sin duplicar saldo');
expectApi(finance('GET', $query)['Resultado']['componentes']['pendientes_netos'] === $recognized['componentes']['pendientes_netos'], 'Reemitir no duplica el devengo');
$finalClose = makeFinance('cierres', $closeBody);
expectApi($finalClose['datos']['fuentes']['servicios_externos'][0]['comisiones'][0]['id_comision'] === $secondCommission['id'],
    'El cierre financiero guarda la fuente de ganancias que realmente utilizo');
finance('DELETE', 'comisiones/'.$secondCommission['id'], ['motivo' => 'No alterar un periodo cerrado'], 422);
finance('POST', 'arqueosserviciosexternos', ['fecha' => $nextDate, 'agencias_esperadas' => [$agencies[0]]], 422);
expectApi(CierreFinanciero::findOrFail($finalClose['id'])->datos === $finalClose['datos'], 'El snapshot financiero permanece intacto');
expectApi($db->select('PRAGMA foreign_key_check') === [], 'No hay referencias huerfanas en el flujo completo');
echo 'OK servicios externos y capital: '.($checks - $startExternalCapital)." comprobaciones nuevas; pendientes, reemision y cierre financiero.\n";
