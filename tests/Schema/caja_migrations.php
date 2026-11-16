<?php

// Prueba independiente: no carga Laravel, .env ni una conexion de la aplicacion.
require dirname(__DIR__, 2).'/vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Facade;

$checks = 0;

function verify(bool $condition, string $message): void
{
    global $checks;

    if (! $condition) {
        throw new RuntimeException($message);
    }

    $checks++;
}

function rejects(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (QueryException) {
        verify(true, $message);

        return;
    }

    throw new RuntimeException($message);
}

function snapshot($connection, $schema): array
{
    $result = [];

    foreach (['subagencias', 'trabajadores', 'users', 'clientes', 'mesas_cambio'] as $table) {
        $result[$table] = [
            'columns' => $schema->getColumns($table),
            'indexes' => $schema->getIndexes($table),
            'foreign_keys' => $schema->getForeignKeys($table),
            'rows' => $connection->table($table)->orderBy('id')->get()->all(),
        ];
    }

    return $result;
}

$container = new Container;
$capsule = new Capsule($container);
$capsule->addConnection([
    'driver' => 'sqlite',
    'database' => ':memory:',
    'foreign_key_constraints' => true,
]);
$connection = $capsule->getConnection();
$schema = $connection->getSchemaBuilder();
$container->instance('db.schema', $schema);
Facade::setFacadeApplication($container);
$connection->statement('PRAGMA foreign_keys = ON');
verify((int) $connection->selectOne('PRAGMA foreign_keys')->foreign_keys === 1, 'Las claves foraneas deben estar activas');

$files = glob(dirname(__DIR__, 2).'/database/migrations/*.php');
sort($files);
$protected = [];
$expansion = [];

foreach ($files as $file) {
    $name = basename($file);
    $migration = require $file;

    if (str_starts_with($name, '0001_') || str_starts_with($name, '2025_')
        || str_contains($name, 'clientes_table') || str_contains($name, 'mesas_de_cambio_table')) {
        $protected[$name] = $migration;
        $migration->up();
    } else {
        $expansion[$name] = $migration;
    }
}

$agency = $connection->table('subagencias')->insertGetId([
    'nombre' => 'Agencia de prueba', 'ubicacion' => 'Prueba', 'direccion' => 'Prueba',
    'fecha_apertura' => '2026-01-01', 'fecha_cierre' => '2099-12-31', 'estado_agencia' => 'activo',
]);
$users = [];

foreach ([1, 2] as $number) {
    $worker = $connection->table('trabajadores')->insertGetId([
        'nombre' => 'Cajero de prueba '.$number, 'cedula' => 'T'.$number,
        'fecha_nacimiento' => '1990-01-01', 'inss' => 'I'.$number,
        'nombre_banco' => 'Prueba', 'numero_cuenta_banco' => 'PRUEBA'.$number,
        'estadotrabajador' => 'activo', 'fecha_contratacion' => '2026-01-01',
        'id_subagencia' => $agency, 'cargo' => 'Cajero', 'salario_basico_inicial' => 0,
        'salario_basico_actual' => 0, 'fecha_finalizacion' => '2099-12-31',
        'concepto_finalizacion' => 'Prueba en memoria',
    ]);
    $users[] = $connection->table('users')->insertGetId([
        'usuario' => 'caja_prueba_'.$number, 'contraseña' => 'SIN_USO', 'rol_usuario' => 'cajero',
        'correo_asignado_cajero' => 'caja'.$number.'@example.invalid',
        'usuario_asignado_airpak' => 'PRUEBA'.$number, 'estado_usuario' => 'activo',
        'id_trabajador' => $worker,
    ]);
}

$client = $connection->table('clientes')->insertGetId([
    'id_usuario' => $users[0], 'nombre' => 'Cliente de prueba', 'cedula' => 'CLIENTE1',
    'telefono' => '00000000', 'direccion' => 'Prueba', 'estado_cliente' => 'activo',
]);
$mesa = $connection->table('mesas_cambio')->insertGetId([
    'id_usuario' => $users[0], 'id_cliente' => $client, 'fecha' => '2026-11-04 09:00:00',
    'tipo_transaccion' => 'Compra', 'tasa' => 36.5, 'monto_entrada' => 100,
    'monto_salida' => 3650, 'concepto' => 'Prueba', 'archivo_adjunto' => false, 'estado_mesa' => 'activo',
]);
$original = snapshot($connection, $schema);

foreach ($expansion as $migration) {
    $migration->up();
}

verify(snapshot($connection, $schema) == $original, 'La expansion no debe alterar tablas ni filas protegidas');
$box1 = $connection->table('cajas')->insertGetId(['id_subagencia' => $agency, 'codigo' => 'Caja1', 'nombre' => 'Caja 1']);
$box2 = $connection->table('cajas')->insertGetId(['id_subagencia' => $agency, 'codigo' => 'Caja2', 'nombre' => 'Caja 2']);
$dayData = ['id_usuario' => $users[0], 'id_subagencia' => $agency, 'id_caja' => $box1,
    'caja_inicial_cordobas' => '10000.00', 'caja_inicial_dolares' => '200.00'];
$previous = $connection->table('arqueo')->insertGetId($dayData + ['fecha' => '2026-11-03']);
$day = $connection->table('arqueo')->insertGetId($dayData + [
    'fecha' => '2026-11-04', 'id_arqueo_anterior' => $previous,
    'cheques_inicial_cordobas' => '1000.00', 'tasa_referencia' => '36.700000',
]);
$other = $connection->table('arqueo')->insertGetId([
    'id_usuario' => $users[1], 'id_subagencia' => $agency, 'id_caja' => $box2,
    'fecha' => '2026-11-04', 'caja_inicial_cordobas' => 0, 'caja_inicial_dolares' => 0,
]);
$row = $connection->table('arqueo')->find($day);
verify($row->estado === 'abierto' && $row->caja_final_cordobas === null && $row->diferencia_dolares === null,
    'La apertura debe distinguir un cierre pendiente de un resultado cero');
verify($row->biller_cordobas === null && $row->envios_dolares === null, 'Los totales no informados deben conservar NULL');
$connection->table('arqueo')->where('id', $day)->update(['biller_cordobas' => 0, 'biller_dolares' => '-6500.00']);
verify((float) $connection->table('arqueo')->find($day)->biller_dolares === -6500.0, 'Debe conservarse el signo de Biller');
rejects(fn () => $connection->table('arqueo')->insert($dayData + ['fecha' => '2026-11-04']),
    'No debe haber dos arqueos diarios para el mismo cajero');
rejects(fn () => $connection->table('arqueo')->insert($dayData + ['fecha' => '2026-11-05', 'id_arqueo_anterior' => $previous]),
    'Un cierre previo no debe alimentar dos jornadas diferentes');

$connection->table('detalle_arqueo')->insert(['id_arqueo' => $day, 'moneda' => 'NIO', 'mil' => 9]);
$connection->table('detalle_arqueo')->insert(['id_arqueo' => $day, 'moneda' => 'USD', 'cien' => 2]);
verify($connection->table('detalle_arqueo')->where('id_arqueo', $day)->where('moneda', 'NIO')->first()->total === null,
    'El total del conteo permanece pendiente hasta su verificacion');
rejects(fn () => $connection->table('detalle_arqueo')->insert(['id_arqueo' => $day, 'moneda' => 'NIO']),
    'Debe existir un solo conteo por moneda y arqueo');
rejects(fn () => $connection->table('detalle_arqueo')->insert(['id_arqueo' => $other, 'moneda' => 'C$']),
    'Las monedas deben utilizar los codigos NIO y USD');
rejects(fn () => $connection->table('detalle_arqueo')->insert(['id_arqueo' => 999999, 'moneda' => 'NIO']),
    'Un conteo no puede apuntar a una jornada inexistente');

$rate = $connection->table('tasas_cambio_arqueo')->insertGetId([
    'id_arqueo' => $day, 'tipo' => 'Compra', 'numero_precio' => 1, 'tasa' => '36.500000',
]);
$otherRate = $connection->table('tasas_cambio_arqueo')->insertGetId([
    'id_arqueo' => $other, 'tipo' => 'Compra', 'numero_precio' => 1, 'tasa' => '36.400000',
]);
$operation = ['id_arqueo' => $day, 'tipo' => 'Compra', 'monto_dolares' => '100.00',
    'tasa' => '36.500000', 'monto_cordobas' => '3650.00', 'id_tasa_arqueo' => $rate];
$connection->table('operaciones_cambio_caja')->insert($operation + ['id_mesa_cambio' => $mesa]);
rejects(fn () => $connection->table('operaciones_cambio_caja')->insert($operation + ['id_mesa_cambio' => $mesa]),
    'Una mesa existente no puede asociarse dos veces');
rejects(fn () => $connection->table('operaciones_cambio_caja')->insert(array_replace($operation, ['id_tasa_arqueo' => $otherRate])),
    'La tasa debe pertenecer a la misma jornada');
rejects(fn () => $connection->table('operaciones_cambio_caja')->insert(array_replace($operation, ['tipo' => 'Venta'])),
    'Una venta no puede vincularse a una tasa de compra');
$connection->table('tasas_cambio_arqueo')->where('id', $rate)->update(['tasa' => '36.600000']);
verify((float) $connection->table('operaciones_cambio_caja')->first()->tasa === 36.5,
    'Cambiar el precio diario no debe cambiar la tasa capturada en una operacion');
$connection->table('operaciones_cambio_caja')->insert([
    'id_arqueo' => $day, 'tipo' => 'Compra', 'monto_dolares' => '5.83',
    'tasa' => '36.500000', 'monto_cordobas' => '212.79500000',
]);
verify(bccomp((string) $connection->table('operaciones_cambio_caja')->orderByDesc('id')->first()->monto_cordobas,
    '212.79500000', 8) === 0, 'La captura debe conservar las fracciones de centavo del Excel');
rejects(fn () => $connection->table('mesas_cambio')->where('id', $mesa)->delete(),
    'El vinculo con caja debe impedir borrar una mesa referenciada');

$cheque = $connection->table('cheques')->insertGetId([
    'id_arqueo' => $previous, 'moneda' => 'NIO', 'monto' => '1000.00', 'ganancia' => '10.00',
]);
foreach ([[$previous, 'cierre'], [$day, 'apertura'], [$day, 'cierre']] as [$journal, $moment]) {
    $connection->table('arqueo_cheques')->insert([
        'id_arqueo' => $journal, 'id_cheque' => $cheque, 'momento' => $moment,
        'moneda' => 'NIO', 'monto' => '1000.00',
    ]);
}
verify($connection->table('cheques')->count() === 1
    && (float) $connection->table('cheques')->sum('ganancia') === 10.0,
    'Arrastrar un cheque debe conservar su identidad sin duplicar su ganancia');
rejects(fn () => $connection->table('arqueo_cheques')->insert([
    'id_arqueo' => $day, 'id_cheque' => $cheque, 'momento' => 'cierre', 'moneda' => 'NIO', 'monto' => 1000,
]), 'El mismo cheque no puede contarse dos veces en el mismo cierre');
$connection->table('cheques')->insert(['id_arqueo' => $day, 'moneda' => 'NIO', 'ganancia' => '352.00']);
verify($connection->table('cheques')->where('id_arqueo', $day)->first()->monto === null,
    'Las filas del Excel con solo ganancia deben conservar el principal desconocido');

$outgoing = $connection->table('pases')->insertGetId([
    'id_arqueo' => $day, 'id_usuario' => $users[0], 'id_caja_contraparte' => $box2,
    'tipo' => 'Salida', 'moneda' => 'NIO', 'monto' => '500.00', 'detalle' => 'Caja2',
]);
$incoming = $connection->table('pases')->insertGetId([
    'id_arqueo' => $other, 'id_usuario' => $users[1], 'id_caja_contraparte' => $box1,
    'id_pase_relacionado' => $outgoing, 'tipo' => 'Entrada', 'moneda' => 'NIO', 'monto' => '500.00',
]);
$connection->table('pases')->where('id', $outgoing)->update(['id_pase_relacionado' => $incoming]);
verify((float) $connection->table('pases')->where('tipo', 'Entrada')->sum('monto')
    === (float) $connection->table('pases')->where('tipo', 'Salida')->sum('monto'),
    'Las declaraciones de ambas cajas deben poder relacionarse y conciliarse');

foreach (['bancario', 'entre_agencias', 'gasto', 'varios'] as $category) {
    $connection->table('entradas_y_salidas')->insert([
        'id_arqueo' => $day, 'categoria' => $category, 'tipo' => 'Salida', 'moneda' => 'NIO',
        'monto' => '25.75', 'detalle' => $category === 'gasto' ? 'PAPELERIA' : null,
    ]);
}
verify($connection->table('entradas_y_salidas')->where('id_arqueo', $day)->distinct()->count('categoria') === 4,
    'Los cuatro controles del Excel deben conservarse como categorias diferenciadas');
$movement1 = $connection->table('entradas_y_salidas')->insertGetId([
    'id_arqueo' => $day, 'categoria' => 'entre_agencias', 'tipo' => 'Salida', 'moneda' => 'USD', 'monto' => 100,
]);
$movement2 = $connection->table('entradas_y_salidas')->insertGetId([
    'id_arqueo' => $other, 'categoria' => 'entre_agencias', 'tipo' => 'Entrada', 'moneda' => 'USD',
    'monto' => 100, 'id_movimiento_relacionado' => $movement1,
]);
$connection->table('entradas_y_salidas')->where('id', $movement1)->update(['id_movimiento_relacionado' => $movement2]);
$connection->table('remesas_bancarias')->insert([
    'id_arqueo' => $day, 'banco' => 'Lafise', 'tipo' => 'Salida', 'moneda' => 'USD',
    'numero_remesa' => '000056178372', 'monto' => '100.00',
]);
verify($connection->table('remesas_bancarias')->first()->numero_remesa === '000056178372',
    'Las referencias deben conservar ceros iniciales');

$shipment = $connection->table('envios_dhl')->insertGetId([
    'id_arqueo' => $day, 'guia' => '001409262632', 'nombre_contacto' => 'Contacto de prueba',
    'tipo' => 'DOC', 'fecha_envio' => '2026-11-04', 'fecha_entrega' => '2026-11-09 12:00:00',
]);
$connection->table('detalle_envios_dhl')->insert(['id_envio_dhl' => $shipment, 'descripcion' => 'Pasaportes', 'cantidad' => 2]);
$connection->table('envios_dhl')->insert(['id_arqueo' => $other, 'guia' => '001409262632']);
verify($connection->table('envios_dhl')->where('guia', '001409262632')->count() === 2,
    'Las guias repetidas deben poder conservarse para su verificacion posterior');
verify($connection->table('envios_dhl')->find($shipment)->nombre_beneficiario === null,
    'El registro DHL abreviado no debe inventar el beneficiario');

$service = $connection->table('servicios_caja')->insertGetId(['codigo' => 'FDL', 'nombre' => 'FDL']);
$connection->table('conteos_servicios_diarios')->insert(['id_arqueo' => $day, 'id_servicio' => $service, 'cantidad' => 0]);
rejects(fn () => $connection->table('conteos_servicios_diarios')->insert([
    'id_arqueo' => $day, 'id_servicio' => $service, 'cantidad' => 1,
]), 'Un resumen diario no debe duplicar un mismo servicio');
$connection->table('enrolamientos')->insert([
    'id_arqueo' => $day, 'id_cliente' => $client, 'nombre_completo' => 'Cliente de prueba',
    'numero_documento' => 'CLIENTE1', 'fecha_enrolamiento' => '2026-11-04', 'usuario_servicio' => '14514',
]);
$connection->table('enrolamientos')->insert([
    'id_arqueo' => $day, 'nombre_completo' => 'Cliente de prueba', 'numero_documento' => 'CLIENTE1',
    'fecha_original' => '36/02/2025',
]);
verify($connection->table('enrolamientos')->where('numero_documento', 'CLIENTE1')->count() === 2,
    'Los eventos repetidos de una persona no deben forzar otro cliente');
verify($connection->table('enrolamientos')->whereNull('fecha_enrolamiento')->first()->fecha_original === '36/02/2025',
    'Una fecha invalida de origen debe poder conservarse sin inventar su correccion');
$connection->table('conteos_papeleria')->insert(['id_usuario' => $users[0], 'concepto' => 'SERVI RED', 'cantidad' => 306]);
verify($connection->table('conteos_papeleria')->first()->fecha === null
    && $connection->table('conteos_papeleria')->first()->id_arqueo === null,
    'La papeleria sin periodo identificado no debe asignarse artificialmente a una jornada');
verify($connection->select('PRAGMA foreign_key_check') === [], 'No debe haber referencias huerfanas');
rejects(fn () => $connection->table('arqueo')->where('id', $day)->delete(),
    'Una jornada con registros debe conservar sus dependencias');
verify(snapshot($connection, $schema) == $original, 'Los registros diarios no deben modificar los datos protegidos');

foreach (array_reverse($expansion) as $migration) {
    $migration->down();
}

verify(! $schema->hasTable('arqueo') && ! $schema->hasTable('cajas'), 'La expansion debe poder revertirse en orden inverso');
verify(snapshot($connection, $schema) == $original, 'Revertir la expansion debe conservar las tablas y datos protegidos');

// Compila el DDL para MySQL. La conexion PDO esta bloqueada expresamente.
$mysql = new MySqlConnection(function () {
    throw new RuntimeException('Esta prueba no permite conexiones MySQL reales');
}, 'caja_schema_test');
$container->instance('db.schema', $mysql->getSchemaBuilder());
Facade::clearResolvedInstance('db.schema');
$ddl = $mysql->pretend(function () use ($files) {
    foreach ($files as $file) {
        $migration = require $file;
        $migration->up();
    }
});
verify(count($ddl) > 0, 'Debe generarse el DDL MySQL sin conexion real');

$createdTables = [];
$operationScales = [];

foreach ($ddl as $query) {
    if (preg_match('/^create table `([^`]+)`/i', $query['query'], $tableMatch)) {
        $createdTables[$tableMatch[1]] = true;

        if ($tableMatch[1] === 'operaciones_cambio_caja') {
            preg_match_all('/`(monto_dolares|tasa|monto_cordobas)` decimal\(\d+,\s*(\d+)\)/i',
                $query['query'], $scaleMatches, PREG_SET_ORDER);

            foreach ($scaleMatches as $scaleMatch) {
                $operationScales[$scaleMatch[1]] = (int) $scaleMatch[2];
            }
        }
    }

    preg_match_all('/ references `([^`]+)`/i', $query['query'], $references);

    foreach ($references[1] as $referencedTable) {
        verify(isset($createdTables[$referencedTable]), 'La tabla referenciada debe crearse antes: '.$referencedTable);
    }

    preg_match_all('/(?:constraint|(?:unique|fulltext|spatial) index|index) `([^`]+)`/i', $query['query'], $matches);

    foreach ($matches[1] as $identifier) {
        verify(strlen($identifier) <= 64, 'Identificador MySQL demasiado largo: '.$identifier);
    }
}

verify(isset($operationScales['monto_dolares'], $operationScales['tasa'], $operationScales['monto_cordobas'])
    && $operationScales['monto_cordobas'] >= $operationScales['monto_dolares'] + $operationScales['tasa'],
    'La precision MySQL del producto debe conservar los decimales de monto y tasa sin redondearlos');

echo 'OK: '.$checks.' verificaciones; creacion, registros y reversion en SQLite en memoria; '.count($ddl)." sentencias MySQL compiladas sin conexion.\n";
