<?php

// Confirmaciones comerciales del 2026-11-06; solo SQLite en memoria y datos ficticios.
require __DIR__.'/servicios_externos_revision.php';

use App\Models\Administracion\ArqueoServicioExterno;
use App\Services\Administracion\ServicioExternoService;

$startNoCommissionChecks = $checks;
$noCommissionFailures = [];
function casoSinComision(string $name, callable $case): void
{
    global $db, $noCommissionFailures;
    $db->beginTransaction();
    try {
        $case();
    } catch (Throwable $e) {
        $noCommissionFailures[] = $name.': '.$e->getMessage();
    } finally {
        $db->rollBack();
    }
}
function diaSinComision(): int
{
    global $agencies;

    return nuevoExterno('arqueosserviciosexternos', ['fecha' => '2026-11-21', 'agencias_esperadas' => $agencies])['id'];
}
function tarifaV1(): array
{
    $tariff = ServicioExternoService::catalog();
    $tariff['version'] = '2026-11-05.v1';
    foreach (['lafise', 'banpro'] as $bank) {
        unset($tariff['servicios'][$bank]['sin_comision']);
    }

    return $tariff;
}

casoSinComision('Una tarifa para todas las cuentas y movimientos sin comision fuera del tramo', function () {
    global $agencies, $db, $mappings;
    $id = diaSinComision();
    $operationCount = $db->table('operaciones_financieras')->count();
    for ($n = 1; $n <= 10; $n++) {
        filaExterna($id, 'CON-COMISION-'.$n);
    }
    filaExterna($id, 'FONDEO-BANPRO-NIO', ['servicio' => 'sin_comision', 'motivo_sin_comision' => 'Fondeo del POS', 'monto' => '1000']);
    filaExterna($id, 'TRASLADO-BANPRO-USD', ['servicio' => 'sin_comision', 'motivo_sin_comision' => 'Traslado interno',
        'monto' => '500', 'moneda' => 'USD', 'tipo' => 'Salida', 'id_subagencia' => $agencies[1]]);
    filaExterna($id, 'FONDEO-LAFISE', ['banco' => 'lafise', 'servicio' => 'sin_comision', 'motivo_sin_comision' => 'Fondeo del POS',
        'monto' => '500', 'moneda' => 'USD']);
    $summary = resumenExterno($id);
    expectApi($summary['transacciones_cuenta_banpro'] === 10 && $summary['tarifa_transacciones_banpro_nio'] === '5.00000000'
        && $summary['totales']['ganancia_nio'] === '50.00000000', 'Los movimientos sin comision no hacen subir el tramo a once');
    expectApi($summary['totales']['cantidad'] === 13 && $summary['totales']['cantidad_con_comision'] === 10
        && $summary['totales']['cantidad_sin_comision'] === 3 && $summary['totales']['pendientes'] === 0, 'Se separan las cantidades sin perder movimientos');
    filaExterna($id, 'CUENTA-ONCE');
    $summary = resumenExterno($id);
    expectApi($summary['transacciones_cuenta_banpro'] === 11 && $summary['tarifa_transacciones_banpro_nio'] === '5.50000000'
        && $summary['totales']['ganancia_nio'] === '60.50000000', 'La regla confirmada es once por 5.50, no un calculo progresivo');
    expectApi($summary['por_banco']['banpro']['cantidad_sin_comision'] === 2 && $summary['por_banco']['lafise']['cantidad_sin_comision'] === 1,
        'La separacion de cantidades se conserva por banco');
    $line = collect($summary['lineas_reporte'])->first(fn ($l) => $l['id_subagencia'] === $agencies[0] && $l['banco'] === 'banpro'
        && $l['moneda'] === 'NIO' && $l['tipo'] === 'Entrada');
    expectApi($line['monto'] === '2100.00000000' && $line['cantidad'] === 12, 'El reporte incluye el principal del fondeo aunque no genere comision');
    $closed = cerrarExterno($id);
    expectApi($closed['totales']['cantidad_con_comision'] === 11 && $closed['totales']['cantidad_sin_comision'] === 3, 'El cierre congela ambos contadores');
    $emitted = externo('POST', 'arqueosserviciosexternos/'.$id.'/generarreporte', ['corte_at' => '2026-11-21 18:00:00'])['Registro'];
    $saved = $db->table('detalles_remesa_central')->where('id_reporte', $emitted['id'])->where('id_subagencia', $agencies[0])
        ->where('banco', 'banpro')->where('moneda', 'NIO')->where('tipo', 'Entrada')->value('monto');
    expectApi((string) $saved === '2100', 'El reporte emitido conserva el total monetario completo');
    externo('POST', 'arqueosserviciosexternos/'.$id.'/registrarcomisiones', ['fecha' => '2026-11-21',
        'cuentas' => [['banco' => 'lafise', 'id_cuenta' => $mappings[0]['id_cuenta']]]], 422);
    $commission = externo('POST', 'arqueosserviciosexternos/'.$id.'/registrarcomisiones', ['fecha' => '2026-11-21',
        'cuentas' => [['banco' => 'banpro', 'id_cuenta' => $mappings[1]['id_cuenta']]]])['Resultado']['comisiones'][0];
    expectApi($commission['monto'] === '60.50000000', 'El devengo solo reconoce las ganancias, no el fondeo');
    $coverage = ServicioExternoService::financialCoverage('2026-11-21');
    expectApi(! collect($coverage['pendientes'])->contains('id_arqueo_externo', $id), 'No se exige un devengo cero para LAFISE');
    $period = externo('GET', 'serviciosexternos/resumen?fecha_desde=2026-11-21&fecha_hasta=2026-11-21')['Resultado'];
    expectApi($period['cantidad'] === 14 && $period['cantidad_con_comision'] === 11 && $period['cantidad_sin_comision'] === 3
        && $period['por_banco']['banpro']['cantidad_sin_comision'] === 2, 'El periodo suma ambos tipos por dia y por banco');
    expectApi($db->table('operaciones_financieras')->count() === $operationCount, 'Capturar, cerrar y devengar no duplica principal ni efectivo');
});

casoSinComision('Motivo obligatorio y reclasificacion explicita', function () {
    $id = diaSinComision();
    foreach ([null, '', '   '] as $reason) {
        $response = externo('POST', 'transaccionesexternas', ['id_arqueo_externo' => $id, 'clave' => 'INVALIDA',
            'id_subagencia' => $GLOBALS['agencies'][0], 'nombre_cajero' => 'Central', 'banco' => 'banpro', 'servicio' => 'sin_comision',
            'tipo' => 'Entrada', 'moneda' => 'NIO', 'numero_referencia' => 'FONDEO', 'monto' => '100', 'motivo_sin_comision' => $reason,
            'motivo' => 'Este motivo de auditoria no sustituye a la clasificacion'], 422);
        expectApi(isset($response['errors']['motivo_sin_comision']), 'El motivo del movimiento no puede estar vacio');
    }
    $row = filaExterna($id, 'SIN-COMISION', ['servicio' => 'sin_comision', 'motivo_sin_comision' => '  Fondeo de POS  ']);
    expectApi($row['motivo_sin_comision'] === 'Fondeo de POS', 'Se conserva un motivo normalizado');
    externo('PATCH', 'transaccionesexternas/'.$row['id'], ['observacion' => 'Verificado']);
    externo('PATCH', 'transaccionesexternas/'.$row['id'], ['motivo_sin_comision' => null], 422);
    externo('PATCH', 'transaccionesexternas/'.$row['id'], ['servicio' => 'transaccion_cuenta'], 422);
    $before = resumenExterno($id);
    externo('PATCH', 'transaccionesexternas/'.$row['id'], ['servicio' => 'transaccion_cuenta', 'motivo_sin_comision' => null]);
    $charged = resumenExterno($id);
    expectApi($charged['totales']['ganancia_nio'] === '5.00000000' && $charged['totales']['cantidad_sin_comision'] === 0,
        'Reclasificar a cuenta restablece el tramo y la comision');
    externo('POST', 'arqueosserviciosexternos/'.$id.'/cerrar', ['confirmar_registros' => true, 'huella' => $before['huella']], 422);
    externo('PATCH', 'transaccionesexternas/'.$row['id'], ['servicio' => 'sin_clasificar']);
    $unknown = resumenExterno($id);
    expectApi(! $unknown['completo'] && $unknown['totales']['ganancia_nio'] === null && $unknown['totales']['cantidad_sin_comision'] === 0,
        'Sin clasificar no es una confirmacion de ganancia cero');
    externo('PATCH', 'transaccionesexternas/'.$row['id'], ['servicio' => 'sin_comision', 'motivo_sin_comision' => 'Fondeo confirmado']);
    $closed = cerrarExterno($id);
    expectApi($closed['totales']['ganancia_nio'] === '0.00000000' && $closed['transacciones'][0]['calculo']['motivo_sin_comision'] === 'Fondeo confirmado',
        'El calculo congelado incluye la justificacion del cero');
    externo('PATCH', 'transaccionesexternas/'.$row['id'], ['motivo_sin_comision' => 'Cambiar la historia'], 422);
});

casoSinComision('Un dia solo de movimientos sin comision puede cerrar sin cargos ficticios', function () {
    global $db, $mappings;
    $id = diaSinComision();
    filaExterna($id, 'TRASLADO', ['servicio' => 'sin_comision', 'motivo_sin_comision' => 'Traslado interno']);
    $before = $db->table('comisiones_administrativas')->count();
    $closed = cerrarExterno($id);
    expectApi($closed['completo'] && $closed['totales']['cantidad_sin_comision'] === 1 && $closed['totales']['ganancia_nio'] === '0.00000000',
        'Cero confirmado no es pendiente');
    expectApi(! collect(ServicioExternoService::financialCoverage('2026-11-21')['pendientes'])->contains('id_arqueo_externo', $id),
        'El control financiero no exige cobrar una comision inexistente');
    externo('POST', 'arqueosserviciosexternos/'.$id.'/registrarcomisiones', ['fecha' => '2026-11-21',
        'cuentas' => [['banco' => 'banpro', 'id_cuenta' => $mappings[1]['id_cuenta']]]], 422);
    expectApi($db->table('comisiones_administrativas')->count() === $before, 'No se crea cargo de comision cero');
});

casoSinComision('Los borradores V1 habilitan la clase nueva sin cambiar sus tarifas', function () {
    global $db;
    $id = diaSinComision();
    filaExterna($id, 'REMESA-LAFISE', ['banco' => 'lafise', 'servicio' => 'remesa']);
    $v1 = tarifaV1();
    ArqueoServicioExterno::findOrFail($id)->update(['tarifario' => $v1]);
    $old = resumenExterno($id);
    filaExterna($id, 'FONDEO', ['servicio' => 'sin_comision', 'motivo_sin_comision' => 'Fondeo POS']);
    $updated = ArqueoServicioExterno::findOrFail($id)->tarifario;
    expectApi($updated['version'] === '2026-11-06.v2' && $updated['servicios']['lafise']['remesa'] === $v1['servicios']['lafise']['remesa']
        && $updated['tramos_transacciones_banpro'] === $v1['tramos_transacciones_banpro'], 'La habilitacion agrega cero y conserva todas las tarifas anteriores');
    expectApi($db->table('historial_financiero')->where('entidad', 'arqueos_servicios_externos')->where('id_registro', $id)
        ->where('accion', 'habilitar_sin_comision')->count() === 1, 'La habilitacion del borrador se audita una sola vez');
    externo('POST', 'arqueosserviciosexternos/'.$id.'/cerrar', ['confirmar_registros' => true, 'huella' => $old['huella']], 422);
    expectApi(cerrarExterno($id)['totales']['ganancia_nio'] === '20.00000000', 'El cierre no pierde la comision de la remesa anterior');
});

casoSinComision('Los cierres V1 conservan su snapshot y el periodo interpreta los contadores anteriores', function () {
    $id = diaSinComision();
    filaExterna($id, 'CUENTA-ANTERIOR');
    $legacy = cerrarExterno($id);
    $legacy['tarifario'] = tarifaV1();
    foreach (['totales'] as $key) {
        unset($legacy[$key]['cantidad_con_comision'], $legacy[$key]['cantidad_sin_comision']);
    }
    foreach (['por_banco', 'por_agencia_banco', 'por_servicio'] as $key) {
        foreach ($legacy[$key] as &$bucket) {
            unset($bucket['cantidad_con_comision'], $bucket['cantidad_sin_comision']);
        }
        unset($bucket);
    }
    ArqueoServicioExterno::findOrFail($id)->update(['tarifario' => tarifaV1(), 'resumen' => $legacy]);
    expectApi(resumenExterno($id) === $legacy, 'Leer un cierre anterior no reescribe ni completa el JSON guardado');
    $period = externo('GET', 'serviciosexternos/resumen?fecha_desde=2026-11-21&fecha_hasta=2026-11-21')['Resultado'];
    expectApi($period['cantidad_con_comision'] === 1 && $period['cantidad_sin_comision'] === 0
        && $period['por_banco']['banpro']['cantidad_con_comision'] === 1, 'Los cierres V1 solo contenian servicios con comision');
    externo('POST', 'transaccionesexternas', ['id_arqueo_externo' => $id, 'clave' => 'NO-ALTERAR', 'id_subagencia' => $GLOBALS['agencies'][0],
        'nombre_cajero' => 'Central', 'banco' => 'banpro', 'servicio' => 'sin_comision', 'motivo_sin_comision' => 'Fondeo',
        'tipo' => 'Entrada', 'moneda' => 'NIO', 'numero_referencia' => 'NO-ALTERAR', 'monto' => '100'], 422);
    expectApi(ArqueoServicioExterno::findOrFail($id)->tarifario['version'] === '2026-11-05.v1', 'Los cierres V1 nunca se habilitan para nueva captura');
});

if ($noCommissionFailures !== []) {
    throw new RuntimeException("Movimientos sin comision:\n".implode("\n", $noCommissionFailures));
}
expectApi(protectedApiSnapshot() === $externalProtected, 'Los movimientos sin comision no modifican tablas protegidas');
echo 'OK movimientos sin comision: '.($checks - $startNoCommissionChecks)." comprobaciones nuevas; cero explicito, tarifa unica, reportes y compatibilidad V1.\n";
