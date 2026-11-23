<?php

// Rutas reales y transacciones sobre SQLite en memoria, sin .env ni API externa.
require __DIR__.'/caja_api.php';

use App\Models\Arqueo;
use App\Models\Pases;
use App\Models\SolicitudPase;
use App\Models\User;
use App\Support\Caja\Decimal;
use Illuminate\Support\Str;

$beforeChecks = $checks;
function crearCajeroPase(int $agencia, string $rol = 'Caja', string $estado = 'Activo'): array
{
    global $db, $cashier;
    $key = (string) Str::uuid();
    $worker = (array) $db->table('trabajadores')->first();
    unset($worker['id']); $worker['nombre'] = 'Cajero '.$key; $worker['cedula'] = $key; $worker['id_subagencia'] = $agencia;
    $worker['inss'] = $key; $worker['numero_cuenta_banco'] = $key;
    $idWorker = $db->table('trabajadores')->insertGetId($worker);
    $u = $cashier->getAttributes(); unset($u['id']);
    $u['usuario'] = $key; $u['correo_asignado_cajero'] = $key.'@example.invalid';
    $u['usuario_asignado_airpak'] = $key; $u['id_trabajador'] = $idWorker; $u['rol_usuario'] = $rol; $u['estado_usuario'] = $estado;
    $user = User::findOrFail($db->table('users')->insertGetId($u));
    $day = Arqueo::create(['id_usuario' => $user->id, 'id_subagencia' => $agencia, 'fecha' => '2026-11-23', 'estado' => 'abierto',
        'caja_inicial_cordobas' => '1000.00', 'caja_inicial_dolares' => '1000.00',
        'biller_cordobas' => '0', 'biller_dolares' => '0', 'envios_cordobas' => '0', 'envios_dolares' => '0',
        'pagos_cordobas' => '0', 'pagos_dolares' => '0', 'cheques_inicial_cordobas' => '0', 'cheques_inicial_dolares' => '0',
        'tasa_referencia' => '36.700000']);
    return [$user, $day];
}

[$sender, $senderDay] = crearCajeroPase($agencies[0]);
[$receiver, $receiverDay] = crearCajeroPase($agencies[0], 'Cajero');
[$remote, $remoteDay] = crearCajeroPase($agencies[1]);
[$inactive] = crearCajeroPase($agencies[0], 'Caja', 'Inactivo');
[$notCashier] = crearCajeroPase($agencies[0], 'Administrador');
$same = api('GET', 'solicitudespases/destinatarios?id_arqueo='.$senderDay->id, [], $sender)['Destinatarios'];
$ids = array_column($same, 'id_usuario');
expectApi(in_array($receiver->id, $ids, true) && !in_array($remote->id, $ids, true) && !in_array($sender->id, $ids, true), 'Destinos por defecto: otra caja de la misma agencia');
expectApi(!in_array($inactive->id, $ids, true) && !in_array($notCashier->id, $ids, true), 'Excluir inactivos y roles distintos de caja');
expectApi(str_contains($same[0]['texto'], ' - '), 'Destino con ubicación y nombre');
$all = api('GET', 'solicitudespases/destinatarios?id_arqueo='.$senderDay->id.'&entre_subagencias=1', [], $sender)['Destinatarios'];
expectApi(in_array($remote->id, array_column($all, 'id_usuario'), true), 'Permitir cajeros de otras agencias al habilitarlo');
api('GET', 'solicitudespases/destinatarios?id_arqueo='.$senderDay->id, [], $receiver, 404);
$body = ['clave' => (string) Str::uuid(), 'id_arqueo' => $senderDay->id, 'id_usuario_destino' => $receiver->id,
    'entre_subagencias' => false, 'moneda' => 'NIO', 'monto' => '50.25', 'referencia' => '0000123'];
api('POST', 'detallearqueo', ['id_arqueo' => $senderDay->id, 'moneda' => 'NIO', 'mil' => 1, 'estado' => 'contado'], $sender, 201);
api('POST', 'detallearqueo', ['id_arqueo' => $senderDay->id, 'moneda' => 'USD', 'cien' => 10, 'estado' => 'contado'], $sender, 201);
$count = Pases::count();
$s = api('POST', 'solicitudespases', $body, $sender, 201)['Solicitud'];
expectApi(Pases::count() === $count + 1 && $s['id_pase_entrada'] === null && $s['estado'] === 'pendiente', 'Solo se crea la salida al solicitar');
expectApi(api('POST', 'solicitudespases', $body, $sender)['Solicitud']['id'] === $s['id'] && Pases::count() === $count + 1, 'Repetir la misma clave no duplica la salida');
api('POST', 'solicitudespases', array_replace($body, ['monto' => '51.25']), $sender, 409);
expectApi(count(api('GET', 'solicitudespases', [], $receiver)['Solicitudes']) === 1, 'El destinatario recibe una notificación persistente');
expectApi(api('GET', 'solicitudespases', [], $remote)['Solicitudes'] === [], 'Un tercero no recibe solicitudes ajenas');
api('GET', 'solicitudespases/'.$s['id'], [], $remote, 404);
expectApi(api('GET', 'solicitudespases?clave='.$body['clave'], [], $sender)['Solicitudes'][0]['id'] === $s['id'], 'Verificar una respuesta perdida por UUID sin reenviar');
$sumSource = api('GET', 'arqueos/'.$senderDay->id.'/resumen', [], $sender)['Resumen'];
$sumDest = api('GET', 'arqueos/'.$receiverDay->id.'/resumen', [], $receiver)['Resumen'];
expectApi(Decimal::compare($sumSource['esperado']['NIO'], '949.75') === 0 && Decimal::compare($sumDest['esperado']['NIO'], '1000') === 0, 'Salida afecta origen y entrada espera aceptación');
expectApi(in_array('pases_por_resolver', $sumSource['pendientes'], true) && in_array('pases_por_resolver', $sumDest['pendientes'], true), 'Ambas jornadas muestran el pase pendiente');
expectApi($sumSource['pendientes'] === ['pases_por_resolver'], 'El pase es el único dato que impide cerrar una jornada completa');
api('POST', 'arqueos/'.$senderDay->id.'/cerrar', ['confirmar_cheques' => true], $sender, 422);
api('DELETE', 'arqueos/'.$senderDay->id, ['motivo' => 'Prueba'], $sender, 422);
api('POST', 'solicitudespases/'.$s['id'].'/aceptar', [], $sender, 404);
api('PATCH', 'pases/'.$s['id_pase_salida'], ['monto' => '99.00'], $sender, 422);
api('POST', 'pases', ['id_arqueo' => $receiverDay->id, 'tipo' => 'Entrada', 'moneda' => 'NIO', 'monto' => '50.25',
    'id_pase_relacionado' => $s['id_pase_salida']], $admin, 422);
$accepted = api('POST', 'solicitudespases/'.$s['id'].'/aceptar', [], $receiver)['Solicitud'];
$in = Pases::findOrFail($accepted['id_pase_entrada']); $out = Pases::findOrFail($s['id_pase_salida']);
expectApi($in->id_arqueo === $receiverDay->id && $in->id_pase_relacionado === $out->id && $out->id_pase_relacionado === $in->id
    && Decimal::compare($in->monto, $out->monto) === 0 && $in->referencia === '0000123', 'Aceptación crea entrada idéntica y enlaza ambos movimientos');
expectApi(api('POST', 'solicitudespases/'.$s['id'].'/aceptar', [], $receiver)['Solicitud']['id_pase_entrada'] === $in->id && Pases::count() === $count + 2, 'Aceptar dos veces no duplica la entrada');
api('POST', 'solicitudespases/'.$s['id'].'/rechazar', ['motivo' => 'Otro estado'], $receiver, 409);
api('DELETE', 'pases/'.$in->id, ['motivo' => 'No permitido'], $receiver, 422);
expectApi(api('GET', 'solicitudespases', [], $receiver)['Solicitudes'] === [], 'Aceptar retira la notificación');
$cross = array_replace($body, ['clave' => (string) Str::uuid(), 'id_usuario_destino' => $remote->id, 'moneda' => 'USD', 'monto' => '25.50']);
api('POST', 'solicitudespases', $cross, $sender, 422);
$cross['entre_subagencias'] = true;
$s2 = api('POST', 'solicitudespases', $cross, $sender, 201)['Solicitud'];
$a2 = api('POST', 'solicitudespases/'.$s2['id'].'/aceptar', [], $remote)['Solicitud'];
expectApi(Pases::findOrFail($a2['id_pase_entrada'])->id_arqueo === $remoteDay->id, 'El mismo flujo acepta entre subagencias sin doble declaración');
$rejectBody = array_replace($body, ['clave' => (string) Str::uuid(), 'monto' => '12.50']);
$s3 = api('POST', 'solicitudespases', $rejectBody, $sender, 201)['Solicitud'];
api('POST', 'solicitudespases/'.$s3['id'].'/rechazar', [], $receiver, 422);
$rejected = api('POST', 'solicitudespases/'.$s3['id'].'/rechazar', ['motivo' => 'No recibido'], $receiver)['Solicitud'];
expectApi($rejected['estado'] === 'rechazado' && $rejected['id_pase_entrada'] === null &&
    Pases::findOrFail($s3['id_pase_salida'])->estado === 'anulado', 'Rechazar anula la salida sin crear entrada');
$count = Pases::count();
api('POST', 'solicitudespases/'.$s3['id'].'/rechazar', ['motivo' => 'No recibido'], $receiver);
expectApi(Pases::count() === $count, 'Rechazo repetido conserva una sola resolución');
api('POST', 'solicitudespases', array_replace($body, ['clave' => (string) Str::uuid(), 'id_usuario_destino' => $sender->id]), $sender, 422);
api('POST', 'solicitudespases', array_replace($body, ['clave' => (string) Str::uuid(), 'id_usuario_destino' => $notCashier->id]), $sender, 422);
api('POST', 'solicitudespases', array_replace($body, ['clave' => (string) Str::uuid(), 'monto' => '1.001']), $sender, 422);
api('POST', 'tasascambioarqueo', ['id_arqueo' => $senderDay->id, 'tipo' => 'Compra', 'numero_precio' => 1, 'tasa' => '36.7'], $sender, 403);
expectApi($db->select('PRAGMA foreign_key_check') === [], 'El flujo conserva todas las referencias');
$atomicBody = array_replace($body, ['clave' => (string) Str::uuid(), 'monto' => '7.25']);
$atomic = api('POST', 'solicitudespases', $atomicBody, $sender, 201)['Solicitud'];
$failIncoming = true;
Pases::creating(function ($row) use (&$failIncoming) {
    if ($failIncoming && $row->tipo === 'Entrada') throw new RuntimeException('Fallo de persistencia simulado');
});
$count = Pases::count();
try {
    api('POST', 'solicitudespases/'.$atomic['id'].'/aceptar', [], $receiver);
    throw new RuntimeException('No se simuló el fallo de la entrada');
} catch (RuntimeException $e) {
    expectApi($e->getMessage() === 'Fallo de persistencia simulado', 'Propagar el fallo sin confirmar aceptación');
}
expectApi(Pases::count() === $count && SolicitudPase::findOrFail($atomic['id'])->estado === 'pendiente'
    && Pases::findOrFail($atomic['id_pase_salida'])->id_pase_relacionado === null, 'La transacción revierte aceptación y vínculos si no se crea la entrada');
$failIncoming = false;
api('POST', 'solicitudespases/'.$atomic['id'].'/aceptar', [], $receiver);
expectApi(Pases::count() === $count + 1, 'La aceptación posterior crea exactamente una entrada');
api('DELETE', 'arqueos/'.$receiverDay->id, ['motivo' => 'No permitido'], $receiver, 422);
echo 'OK: '.($checks - $beforeChecks).' verificaciones de solicitudes de pases, aislamiento, idempotencia y tasas de administración.'.PHP_EOL;
