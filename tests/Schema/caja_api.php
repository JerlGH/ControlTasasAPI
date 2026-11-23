<?php

// Ejecuta las rutas/controladores reales con usuarios ficticios y SQLite en memoria.
// No carga .env, no inicia sesiones reales y no se conecta a la base de la aplicacion.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use App\Models\Arqueo;
use App\Models\User;
use App\Support\Caja\Decimal;
use Carbon\Carbon;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

$checks = 0;
function expectApi(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $checks++;
}

$container = new Container;
Container::setInstance($container);
$container->instance('config', new Repository);
$container->instance('events', new Dispatcher($container));
$capsule = new Capsule($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]);
$capsule->bootEloquent();
$db = $capsule->getConnection();
$db->statement('PRAGMA foreign_keys = ON');
$container->instance('db', $capsule->getDatabaseManager());
$container->instance('db.schema', $db->getSchemaBuilder());
$validator = new Factory(new Translator(new ArrayLoader, 'es'), $container);
$validator->setPresenceVerifier(new DatabasePresenceVerifier($capsule->getDatabaseManager()));
$container->instance('validator', $validator);
Facade::setFacadeApplication($container);
Carbon::setTestNow('2026-11-04 12:00:00');
$files = glob(dirname(__DIR__, 2).'/database/migrations/*.php');
sort($files);
foreach ($files as $file) {
    (require $file)->up();
}
$agencies = [];
foreach ([1, 2] as $i) {
    $agencies[] = $db->table('subagencias')->insertGetId(['nombre' => 'Prueba '.$i, 'ubicacion' => 'Prueba',
        'direccion' => 'Prueba', 'fecha_apertura' => '2026-01-01', 'fecha_cierre' => '2099-12-31', 'estado_agencia' => 'activo']);
}
$users = [];
foreach ([1, 2, 3] as $i) {
    $worker = $db->table('trabajadores')->insertGetId(['nombre' => 'Prueba '.$i, 'cedula' => 'T'.$i,
        'fecha_nacimiento' => '1990-01-01', 'inss' => 'I'.$i, 'nombre_banco' => 'Prueba',
        'numero_cuenta_banco' => 'P'.$i, 'estadotrabajador' => 'activo', 'fecha_contratacion' => '2026-01-01',
        'id_subagencia' => $agencies[$i === 3 ? 1 : 0], 'cargo' => 'Cajero', 'salario_basico_inicial' => 0,
        'salario_basico_actual' => 0, 'fecha_finalizacion' => '2099-12-31', 'concepto_finalizacion' => 'Prueba']);
    $id = $db->table('users')->insertGetId(['usuario' => 'prueba'.$i, 'contraseña' => 'SIN_USO',
        'rol_usuario' => $i === 3 ? 'Administrador' : 'Cajero', 'correo_asignado_cajero' => 'prueba'.$i.'@example.invalid',
        'usuario_asignado_airpak' => 'PRUEBA'.$i, 'estado_usuario' => 'Activo', 'id_trabajador' => $worker]);
    $users[] = User::findOrFail($id);
}
[$cashier, $other, $admin] = $users;
$client = $db->table('clientes')->insertGetId(['id_usuario' => $cashier->id, 'nombre' => 'Cliente de prueba',
    'cedula' => 'PRUEBA-1', 'telefono' => '00000000', 'direccion' => 'Prueba', 'estado_cliente' => 'activo']);
$mesa = $db->table('mesas_cambio')->insertGetId(['id_usuario' => $cashier->id, 'id_cliente' => $client,
    'fecha' => '2026-11-04 09:00:00', 'tipo_transaccion' => 'Compra', 'tasa' => 36.5,
    'monto_entrada' => 5.83, 'monto_salida' => 212.795, 'concepto' => 'Prueba', 'archivo_adjunto' => false, 'estado_mesa' => 'activo']);
function protectedApiSnapshot(): string
{
    global $db;
    $data = [];
    foreach (['users', 'trabajadores', 'clientes', 'subagencias', 'mesas_cambio'] as $table) {
        $data[$table] = $db->table($table)->orderBy('id')->get()->all();
    }
    return json_encode($data, JSON_THROW_ON_ERROR);
}
$protected = protectedApiSnapshot();
$router = new Router($container->make('events'), $container);
$container->instance('router', $router);
$router->group(['prefix' => 'api'], function () {
    require dirname(__DIR__, 2).'/routes/api.php';
});

function api(string $method, string $uri, array $data, ?User $user, int $expected = 200): array
{
    global $container, $router;
    $request = Request::create('/api/'.$uri, $method, $data, [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->setUserResolver(fn () => $user);
    $container->instance(Request::class, $request);
    $container->instance('request', $request);
    try {
        $route = $router->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        expectApi(in_array('auth:sanctum', $route->gatherMiddleware(), true), 'La ruta debe requerir Sanctum');
        // La autenticacion del token se sustituye por el usuario ficticio; la autorizacion real si se ejecuta.
        $response = $route->run();
    } catch (ValidationException $e) {
        $response = new JsonResponse(['errors' => $e->errors()], 422);
    } catch (ModelNotFoundException) {
        $response = new JsonResponse(['error' => 'No encontrado'], 404);
    } catch (HttpExceptionInterface $e) {
        $response = new JsonResponse(['error' => $e->getMessage()], $e->getStatusCode());
    }
    expectApi($response->getStatusCode() === $expected, $method.' '.$uri.' esperaba '.$expected.' y obtuvo '
        .$response->getStatusCode().': '.$response->getContent());

    return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
}

$box1 = api('POST', 'cajas', ['id_subagencia' => $agencies[0], 'codigo' => 'Caja1', 'nombre' => 'Caja 1'], $admin, 201)['Caja']['id'];
$box2 = api('POST', 'cajas', ['id_subagencia' => $agencies[0], 'codigo' => 'Caja2', 'nombre' => 'Caja 2'], $admin, 201)['Caja']['id'];
api('POST', 'cajas', ['id_subagencia' => $agencies[0], 'codigo' => 'Caja3', 'nombre' => 'Caja 3'], $cashier, 403);
$service = api('POST', 'servicioscaja', ['codigo' => 'FDL', 'nombre' => 'FDL'], $admin, 201)['Servicio']['id'];
api('PATCH', 'servicioscaja/'.$service, ['nombre' => 'FDL prueba'], $admin);
api('GET', 'servicioscaja/'.$service, [], $cashier);
api('POST', 'precioproveedordolar', ['proveedor_actual' => 'Prueba', 'precio_dolar' => '36.7'], $admin, 201);
$latest = api('GET', 'precioproveedordolar/ultimo', [], $cashier)['Precio proveedor dólar'];
expectApi($latest['precio_dolar'] === '36.700000', 'La ruta ultimo debe consultar un precio, no un ID literal');
api('POST', 'precioproveedordolar', ['proveedor_actual' => 'Futuro', 'precio_dolar' => '99',
    'vigente_desde' => '2026-12-01 00:00:00'], $admin, 201);
expectApi(api('GET', 'precioproveedordolar/ultimo', [], $cashier)['Precio proveedor dólar']['id'] === $latest['id'],
    'No debe seleccionarse un precio que todavia no esta vigente');
api('GET', 'cajas', [], null, 401);
$disabled = clone $cashier;
$disabled->estado_usuario = 'Inactivo';
api('GET', 'cajas', [], $disabled, 403);

$day = api('POST', 'arqueos', ['fecha' => '2026-11-04', 'id_caja' => $box1,
    'caja_inicial_cordobas' => '32924.00', 'caja_inicial_dolares' => '410.00',
    'cheques_inicial_cordobas' => '0.00', 'cheques_inicial_dolares' => '0.00',
    'tasa_referencia' => '36.70', 'motivo_apertura_manual' => 'Saldo inicial de prueba'], $cashier, 201)['Arqueo']['id'];
$day2 = api('POST', 'arqueos', ['fecha' => '2026-11-04', 'id_caja' => $box2,
    'caja_inicial_cordobas' => '10000', 'caja_inicial_dolares' => '200',
    'cheques_inicial_cordobas' => '1000', 'cheques_inicial_dolares' => '0',
    'tasa_referencia' => '36.70', 'motivo_apertura_manual' => 'Prueba'], $other, 201)['Arqueo']['id'];
api('POST', 'arqueos', ['fecha' => '2026-11-04', 'id_caja' => $box1, 'id_subagencia' => $agencies[0],
    'caja_inicial_cordobas' => '0', 'caja_inicial_dolares' => '0', 'motivo_apertura_manual' => 'Prueba'], $admin, 422);
api('GET', 'arqueos/'.$day2, [], $cashier, 404);
api('PATCH', 'arqueos/'.$day, ['tasa_referencia' => '99'], $cashier, 403);
api('PATCH', 'arqueos/'.$day, ['caja_final_cordobas' => '99999'], $cashier, 422);
api('POST', 'arqueos/'.$day.'/cerrar', ['confirmar_cheques' => true], $cashier, 422);
api('POST', 'remesasbancarias', ['id_arqueo' => $day2, 'banco' => 'Prueba', 'tipo' => 'Salida',
    'moneda' => 'USD', 'numero_remesa' => '1', 'monto' => 1], $cashier, 404);
api('PATCH', 'arqueos/'.$day, ['biller_cordobas' => '331179.66', 'biller_dolares' => '1708.16',
    'envios_cordobas' => '2352.32', 'envios_dolares' => '313.80', 'pagos_cordobas' => '4600', 'pagos_dolares' => '1239.70'], $cashier);

// Reproduce los registros y conteos de la hoja 08 de Evelyn, con una fecha ficticia de prueba.
foreach ([['NIO', '217017.33'], ['USD', '7000'], ['NIO', '250000']] as [$currency, $amount]) {
    api('POST', 'entradasysalidas', ['id_arqueo' => $day, 'categoria' => 'bancario',
        'tipo' => 'Salida', 'moneda' => $currency, 'monto' => $amount], $cashier, 201);
}
$outgoing = null;
foreach ([['Entrada', 'USD', '1000'], ['Entrada', 'NIO', '1622.22'], ['Entrada', 'NIO', '17500'],
    ['Salida', 'NIO', '50000'], ['Entrada', 'USD', '15'], ['Salida', 'USD', '400'],
    ['Salida', 'NIO', '50000'], ['Entrada', 'USD', '1000'], ['Salida', 'USD', '390'], ['Salida', 'USD', '60']] as [$type, $currency, $amount]) {
    $pass = api('POST', 'pases', ['id_arqueo' => $day, 'tipo' => $type, 'moneda' => $currency,
        'monto' => $amount, 'detalle' => 'Caja2'], $cashier, 201)['Pase'];
    if ($type === 'Salida' && $amount === '400') {
        $outgoing = $pass['id'];
    }
}
foreach ([['Salida', 'USD', '560'], ['Salida', 'NIO', '300'], ['Entrada', 'NIO', '100000'],
    ['Entrada', 'NIO', '47387.50'], ['Entrada', 'NIO', '162202.93'], ['Entrada', 'USD', '4411'],
    ['Entrada', 'NIO', '363192.18']] as [$type, $currency, $amount]) {
    api('POST', 'entradasysalidas', ['id_arqueo' => $day, 'categoria' => 'varios',
        'tipo' => $type, 'moneda' => $currency, 'monto' => $amount], $cashier, 201);
}
foreach (['100', '6750'] as $amount) {
    api('POST', 'remesasbancarias', ['id_arqueo' => $day, 'banco' => 'Lafise', 'tipo' => 'Salida',
        'moneda' => '$', 'numero_remesa' => '0000'.$amount, 'monto' => $amount], $cashier, 201);
}
$firstOperation = null;
foreach ([['Compra', 1, '36.5', ['100.2', '50', '350', '148.61', '90.89', '0.85', '100', '5.83', '200']],
    ['Compra', 2, '36.65', ['1000', '6750', '450']], ['Compra', 3, '36.63', ['1740']],
    ['Venta', 1, '37', ['870', '8', '855']]] as [$type, $number, $rate, $amounts]) {
    $rateId = api('POST', 'tasascambioarqueo', ['id_arqueo' => $day, 'tipo' => $type,
        'numero_precio' => $number, 'tasa' => $rate], $admin, 201)['Tasa']['id'];
    foreach ($amounts as $amount) {
        $data = ['id_arqueo' => $day, 'tipo' => $type, 'id_tasa_arqueo' => $rateId, 'monto_dolares' => $amount, 'tasa' => $rate];
        if ($amount === '5.83') {
            $data['id_mesa_cambio'] = $mesa;
        }
        $operation = api('POST', 'operacionescambiocaja', $data, $cashier, 201)['Operacion'];
        $firstOperation ??= $operation['id'];
        if ($amount === '5.83') {
            expectApi($operation['monto_cordobas'] === '212.79500000', 'No debe redondearse el producto del Excel');
        }
    }
}
api('POST', 'tasascambioarqueo', ['id_arqueo' => $day, 'tipo' => 'Compra', 'numero_precio' => 5, 'tasa' => 36], $admin, 422);
$selectedRate = $db->table('tasas_cambio_arqueo')->where('id_arqueo', $day)->where('tipo', 'Compra')->first()->id;
api('POST', 'operacionescambiocaja', ['id_arqueo' => $day, 'id_tasa_arqueo' => $selectedRate,
    'tipo' => 'Compra', 'monto_dolares' => '1', 'tasa' => '1'], $cashier, 422);
api('POST', 'pases', ['id_arqueo' => $day, 'id_usuario' => $other->id, 'tipo' => 'Salida', 'moneda' => 'NIO', 'monto' => 1], $cashier, 422);
api('POST', 'pases', ['id_arqueo' => $day, 'tipo' => 'Salida', 'moneda' => 'NIO', 'monto' => '-10'], $cashier, 422);
api('POST', 'pases', ['id_arqueo' => $day, 'tipo' => 'Salida', 'moneda' => 'NIO', 'monto' => '1.001'], $cashier, 422);
api('POST', 'pases', ['id_arqueo' => $day, 'tipo' => ['Salida'], 'moneda' => ['USD'], 'monto' => '1'], $cashier, 422);
api('POST', 'detallearqueo', ['id_arqueo' => $day, 'moneda' => 'USD', 'mil' => 1, 'estado' => 'contado'], $cashier, 422);
api('POST', 'detallearqueo', ['id_arqueo' => $day, 'moneda' => 'NIO', 'mil' => 29, 'quinientos' => 145,
    'doscientos' => 79, 'cien' => 213, 'cincuenta' => 100, 'veinte' => 23, 'diez' => 23,
    'cinco' => 708, 'uno' => 308, 'estado' => 'contado'], $cashier, 201);
api('POST', 'detallearqueo', ['id_arqueo' => $day, 'moneda' => 'USD', 'cien' => 1, 'veinte' => 75,
    'diez' => 1, 'uno' => 1, 'estado' => 'contado'], $cashier, 201);

$shipment = api('POST', 'enviosdhl', ['id_arqueo' => $day, 'guia' => '000123', 'fecha_envio' => '2026-11-04',
    'nombre_contacto' => 'Prueba', 'tipo' => 'DOC'], $cashier, 201)['Envio']['id'];
expectApi(api('GET', 'enviosdhl/'.$shipment, [], $cashier)['Envio']['tipo'] === 'DOC', 'El tipo DHL debe conservar su texto original');
$item = api('POST', 'detallesenviosdhl', ['id_envio_dhl' => $shipment, 'descripcion' => 'Pasaporte', 'cantidad' => 1], $cashier, 201)['DetalleEnvio']['id'];
api('PATCH', 'detallesenviosdhl/'.$item, ['cantidad' => 2], $cashier);
$enrolment = api('POST', 'enrolamientos', ['id_arqueo' => $day, 'id_cliente' => $client, 'nombre_completo' => 'Prueba',
    'numero_documento' => 'PRUEBA1', 'usuario_servicio' => '14514', 'fecha_original' => '36/02/2025'], $cashier, 201)['Enrolamiento']['id'];
api('PATCH', 'enrolamientos/'.$enrolment, ['estado' => 'registrado'], $cashier, 422);
api('PATCH', 'enrolamientos/'.$enrolment, ['estado' => 'registrado', 'fecha_enrolamiento' => '2026-11-04'], $cashier);
$counter = api('POST', 'conteosservicios', ['id_arqueo' => $day, 'id_servicio' => $service, 'cantidad' => 0], $cashier, 201)['ConteoServicio']['id'];
api('POST', 'conteosservicios', ['id_arqueo' => $day, 'id_servicio' => $service, 'cantidad' => 2], $cashier, 422);
api('PATCH', 'servicioscaja/'.$service, ['codigo' => 'OTRO'], $admin, 422);
$paper = api('POST', 'conteospapeleria', ['concepto' => 'SERVI RED', 'cantidad' => 306], $cashier, 201)['ConteoPapeleria'];
expectApi(($paper['fecha'] ?? null) === null && ($paper['id_arqueo'] ?? null) === null, 'No debe inventarse la fecha de papeleria');
api('PATCH', 'conteospapeleria/'.$paper['id'], ['cantidad' => 307], $cashier);
$datedPaper = api('POST', 'conteospapeleria', ['id_arqueo' => $day, 'concepto' => 'DHL', 'cantidad' => 1], $cashier, 201)['ConteoPapeleria']['id'];
api('PATCH', 'conteospapeleria/'.$datedPaper, ['cantidad' => 2], $cashier);

$summary = api('GET', 'arqueos/'.$day.'/resumen', [], $cashier)['Resumen'];
expectApi($summary['esperado'] === ['NIO' => '148105.41000000', 'USD' => '1611.64000000'], 'El esperado debe coincidir con la hoja 08');
expectApi($summary['diferencia'] === ['NIO' => '32.59000000', 'USD' => '-0.64000000'], 'Las diferencias deben conservarse por moneda');
api('PATCH', 'precioproveedordolar/'.$latest['id'], ['precio_dolar' => '40'], $admin);
expectApi(api('GET', 'arqueos/'.$day.'/resumen', [], $cashier)['Resumen']['esperado'] === $summary['esperado'],
    'El esperado no debe cambiar con una cotizacion posterior');
api('POST', 'arqueos/'.$day.'/cerrar', [], $cashier, 422);
$closed = api('POST', 'arqueos/'.$day.'/cerrar', ['confirmar_cheques' => true], $cashier)['Arqueo'];
expectApi($closed['caja_final_cordobas'] === '148138.00' && $closed['estado'] === 'cerrado', 'El cierre debe guardar el contado');
api('PATCH', 'operacionescambiocaja/'.$firstOperation, ['monto_dolares' => '999'], $cashier, 422);
api('DELETE', 'conteosservicios/'.$counter, ['motivo' => 'Prueba'], $cashier, 422);
api('PATCH', 'enviosdhl/'.$shipment, ['fecha_entrega' => '2026-11-09 12:00:00', 'estado_envio' => 'ENTREGADO'], $cashier);
api('PATCH', 'enviosdhl/'.$shipment, ['guia' => 'ALTERADA'], $cashier, 422);
api('PATCH', 'detallesenviosdhl/'.$item, ['cantidad' => 99], $cashier, 422);
$all = api('GET', 'arqueos/'.$day.'/registros', [], $cashier)['Arqueo'];
expectApi(count($all['operaciones_cambio']) === 16 && count($all['envios_dhl']) === 1,
    'La extraccion diaria debe incluir los registros base relacionados');
api('GET', 'arqueos/'.$day.'/registros', [], $other, 404);

api('PATCH', 'arqueos/'.$day2, array_fill_keys(\App\Services\ArqueoService::TOTALES, 0), $other);
$cheque = api('POST', 'cheques', ['id_arqueo' => $day2, 'moneda' => 'NIO', 'monto' => '1000', 'ganancia' => 0], $other, 201)['Cheque']['id'];
foreach (['apertura', 'cierre'] as $moment) {
    api('POST', 'custodiascheques', ['id_arqueo' => $day2, 'id_cheque' => $cheque, 'momento' => $moment], $other, 201);
}
api('POST', 'custodiascheques', ['id_arqueo' => $day2, 'id_cheque' => $cheque, 'momento' => 'cierre', 'moneda' => 'USD'], $other, 422);
$fee = api('POST', 'cheques', ['id_arqueo' => $day2, 'moneda' => 'USD', 'moneda_ganancia' => 'NIO', 'ganancia' => '352'], $other, 201)['Cheque']['id'];
api('POST', 'custodiascheques', ['id_arqueo' => $day2, 'id_cheque' => $fee, 'momento' => 'cierre'], $other, 422);
api('PATCH', 'cheques/'.$cheque, ['monto' => '2000'], $other, 422);
api('POST', 'pases', ['id_arqueo' => $day2, 'tipo' => 'Entrada', 'moneda' => 'USD', 'monto' => '400',
    'id_caja_contraparte' => $box1, 'id_pase_relacionado' => $outgoing], $other, 403);
$paired = api('POST', 'pases', ['id_arqueo' => $day2, 'tipo' => 'Entrada', 'moneda' => 'USD', 'monto' => '400',
    'id_caja_contraparte' => $box1, 'id_pase_relacionado' => $outgoing], $admin, 201)['Pase']['id'];
api('PATCH', 'pases/'.$paired, ['monto' => '401'], $other, 422);
api('POST', 'entradasysalidas', ['id_arqueo' => $day2, 'categoria' => 'varios', 'tipo' => 'Entrada',
    'moneda' => 'NIO', 'medio' => 'mixto', 'monto' => 100, 'monto_efectivo' => 50, 'monto_cheques' => 49], $other, 422);
$mixed = api('POST', 'entradasysalidas', ['id_arqueo' => $day2, 'categoria' => 'varios', 'tipo' => 'Entrada',
    'moneda' => 'NIO', 'medio' => 'mixto', 'monto' => 100, 'monto_efectivo' => 50, 'monto_cheques' => 50], $other, 201)['Entrada salida']['id'];
api('DELETE', 'entradasysalidas/'.$mixed, ['motivo' => 'Correccion en prueba'], $other);
expectApi($db->table('entradas_y_salidas')->where('id', $mixed)->value('estado') === 'anulado', 'La anulacion debe conservar el registro');
api('POST', 'detallearqueo', ['id_arqueo' => $day2, 'moneda' => 'NIO', 'mil' => 9, 'estado' => 'contado'], $other, 201);
api('POST', 'detallearqueo', ['id_arqueo' => $day2, 'moneda' => 'USD', 'cien' => 2, 'estado' => 'contado'], $other, 201);
$closed2 = api('POST', 'arqueos/'.$day2.'/cerrar', ['confirmar_cheques' => true], $other)['Arqueo'];
expectApi($closed2['monto_total_cheques'] === '1000.00' && $closed2['ganancia_total_cheques'] === '352.00', 'La ganancia y la custodia son hechos distintos');
api('PATCH', 'cheques/'.$cheque, ['estado' => 'depositado', 'fecha_liquidacion' => '2026-11-05 10:00:00'], $other);
api('PATCH', 'cheques/'.$fee, ['estado' => 'anulado'], $other, 422);
$next = api('POST', 'arqueos', ['fecha' => '2026-11-05'], $other, 201)['Arqueo'];
expectApi($next['id_arqueo_anterior'] === $day2 && $next['caja_inicial_cordobas'] === '10000.00'
    && $next['caja_inicial_dolares'] === '200.00', 'La apertura debe copiar el contado, incluso si habia diferencia');
expectApi($next['id_caja'] === $box2 && $next['tasa_referencia'] === '36.700000', 'Se conservan caja y tasa de referencia');
$opening = $db->table('arqueo_cheques')->where('id_arqueo', $next['id'])->first();
expectApi($opening->id_cheque === $cheque && $opening->momento === 'apertura', 'Debe arrastrarse la identidad del cheque');
expectApi($db->table('cheques')->where('id_arqueo', $next['id'])->count() === 0, 'El arrastre no debe registrar una nueva operacion de cheque');
api('DELETE', 'custodiascheques/'.$opening->id, ['motivo' => 'Prueba'], $other, 422);
$nextSummary = api('GET', 'arqueos/'.$next['id'].'/resumen', [], $other)['Resumen'];
expectApi(Decimal::compare($nextSummary['ganancias_cheques']['NIO'], 0) === 0, 'La ganancia anterior no se repite');
api('PATCH', 'arqueos/'.$next['id'], ['caja_inicial_cordobas' => 1, 'motivo_apertura_manual' => 'Prueba'], $other, 422);

// Una transferencia bancaria identificada no incrementa efectivo ni cheques en caja.
api('PATCH', 'arqueos/'.$next['id'], array_fill_keys(\App\Services\ArqueoService::TOTALES, 0), $other);
$beforeBank = api('GET', 'arqueos/'.$next['id'].'/resumen', [], $other)['Resumen']['esperado'];
api('POST', 'entradasysalidas', ['id_arqueo' => $next['id'], 'categoria' => 'bancario', 'tipo' => 'Entrada',
    'moneda' => 'NIO', 'medio' => 'transferencia', 'monto' => '5000'], $other, 201);
expectApi(api('GET', 'arqueos/'.$next['id'].'/resumen', [], $other)['Resumen']['esperado'] === $beforeBank,
    'La transferencia entre cuentas no debe contarse como efectivo');
foreach (['cajas', 'servicioscaja', 'precioproveedordolar', 'arqueos', 'detallearqueo', 'cheques',
    'custodiascheques', 'pases', 'entradasysalidas', 'tasascambioarqueo', 'operacionescambiocaja',
    'remesasbancarias', 'enviosdhl', 'detallesenviosdhl', 'enrolamientos', 'conteosservicios', 'conteospapeleria'] as $uri) {
    api('GET', $uri, [], $cashier);
}
api('GET', 'detallesenviosdhl?id_arqueo='.$day, [], $cashier);
api('GET', 'pases?fecha_hasta=2026-11-04&id_arqueo='.$day, [], $cashier);
api('GET', 'arqueos/ultimo/'.$cashier->id, [], $cashier);
api('GET', 'pases/ultimos/'.$cashier->id, [], $cashier);
api('GET', 'pases/ultimos/'.$other->id, [], $cashier, 403);
api('GET', 'cheques/arqueo/'.$day2, [], $other);

// La custodia declarada de apertura debe concordar con sus documentos.
api('POST', 'detallearqueo', ['id_arqueo' => $next['id'], 'moneda' => 'NIO', 'mil' => 10, 'estado' => 'contado'], $other, 201);
api('POST', 'detallearqueo', ['id_arqueo' => $next['id'], 'moneda' => 'USD', 'cien' => 2, 'estado' => 'contado'], $other, 201);
$db->table('arqueo_cheques')->where('id', $opening->id)->update(['monto' => '999.00']);
api('POST', 'arqueos/'.$next['id'].'/cerrar', ['confirmar_cheques' => true], $other, 422);
$db->table('arqueo_cheques')->where('id', $opening->id)->update(['monto' => '1000.00']);

$history = api('GET', 'historialcaja?id_arqueo='.$day, [], $cashier)['Historial'];
expectApi(count($history) > 0 && isset($history[0]['antes'], $history[0]['despues']), 'El historial debe conservar autor y cambios');
expectApi(api('GET', 'historialcaja?id_arqueo='.$day, [], $other)['Historial'] === [], 'El historial debe respetar el cajero');
expectApi(protectedApiSnapshot() === $protected, 'Las API de caja no deben modificar filas protegidas');
expectApi($db->select('PRAGMA foreign_key_check') === [], 'Las API no deben crear referencias huerfanas');

foreach ($router->getRoutes() as $route) {
    [$class, $method] = explode('@', $route->getActionName(), 2);
    expectApi(method_exists($class, $method), 'Metodo de ruta inexistente: '.$route->getActionName());
}
foreach ([['POST', 'usuarios'], ['PUT', 'usuarios/1'], ['DELETE', 'usuarios/1']] as [$method, $uri]) {
    $route = $router->getRoutes()->match(Request::create('/api/'.$uri, $method));
    expectApi(in_array(\App\Http\Middleware\RequireAdministrador::class, $route->gatherMiddleware(), true),
        'Las rutas que permiten asignar roles deben exigir administrador');
}
$middleware = new \App\Http\Middleware\RequireAdministrador;
$request = Request::create('/api/usuarios', 'POST');
$request->setUserResolver(fn () => $cashier);
$rejected = false;
try {
    $middleware->handle($request, fn () => throw new RuntimeException('No debe permitirse asignar roles'));
} catch (HttpExceptionInterface $e) {
    $rejected = $e->getStatusCode() === 403;
}
expectApi($rejected, 'Un cajero no puede saltar la autorizacion creandose un administrador');
$request->setUserResolver(fn () => $admin);
expectApi($middleware->handle($request, fn () => 'autorizado') === 'autorizado', 'El administrador conserva el acceso a usuarios');
echo 'OK: '.$checks.' verificaciones de API en memoria; saldo y diferencias coinciden con Evelyn 08; tablas protegidas sin cambios.'.PHP_EOL;
