<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TrabajadorController;
use App\Http\Controllers\Api\SubAgenciaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\ArqueoController;
use App\Http\Controllers\Api\PasesController;
use App\Http\Controllers\Api\ChequesController;
use App\Http\Controllers\Api\EntradasYSalidasController;
use App\Http\Controllers\Api\PrecioProveedorDolarController;
use App\Http\Controllers\Mesa_de_CambioController;
use App\Http\Controllers\AuthController;


//EndPoints para Autenticación
 
Route::post('/login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
   //EndPoint para Cerrar Sesión
   Route::post('/logout', [AuthController::class, 'logout']);

   //EndPoints para Usuarios
   Route::get('/usuarios', [UserController::class, 'index']);
   Route::get('/usuarios/{id}', [UserController::class, 'indexid']);
   Route::post('/usuarios', [AuthController::class, 'store'])->middleware(\App\Http\Middleware\RequireAdministrador::class);
   Route::put('/usuarios/{id}', [UserController::class, 'update'])->middleware(\App\Http\Middleware\RequireAdministrador::class);
   Route::delete('/usuarios/{id}', [UserController::class, 'destroy'])->middleware(\App\Http\Middleware\RequireAdministrador::class);

   //EndPoints para Trabajadores
   Route::get('/trabajadores', [TrabajadorController::class, 'index']);
   Route::get('/trabajadores/{id}', [TrabajadorController::class, 'indexid']);
   Route::post('/trabajadores', [TrabajadorController::class, 'store']);
   Route::put('/trabajadores/{id}', [TrabajadorController::class, 'update']);
   Route::delete('/trabajadores/{id}', [TrabajadorController::class, 'destroy']);
   
   //EndPoints para SubAgencias
   Route::get('/subagencias', [SubAgenciaController::class, 'index']);
   Route::get('/subagencias/{id}', [SubAgenciaController::class, 'indexid']);
   Route::post('/subagencias', [SubAgenciaController::class, 'store']);
   Route::put('/subagencias/{id}', [SubAgenciaController::class, 'update']);
   Route::delete('/subagencias/{id}', [SubAgenciaController::class, 'destroy']);
   
   //EndPoints para Clientes
   Route::get('/clientes', [ClienteController::class, 'index']);
   Route::get('/clientes/{id}', [ClienteController::class, 'indexid']);
   Route::get('/clientes/usuario/{id_usuario}', [ClienteController::class, 'indexByUsuario']);
   Route::get('/clientes/ultimos100/{id}', [ClienteController::class, 'indexultimos100xusuario']);
   Route::get('/clientes/cedula/{cedula}', [ClienteController::class, 'indexcedula']);
   Route::post('/clientes', [ClienteController::class, 'store']);
   Route::put('/clientes/{id}', [ClienteController::class, 'update']);
   Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);

   //EndPoints para Mesas de Cambio
   Route::get('/mesasdecambio', [Mesa_de_CambioController::class, 'index']);
   Route::post('/mesasdecambiofiltred', [Mesa_de_CambioController::class, 'showfiltred']);
   Route::post('/mesasconusuarios', [Mesa_de_CambioController::class, 'showmesasconusuarios']);
   Route::get('/mesasdecambio/{id}', [Mesa_de_CambioController::class, 'show'])->whereNumber('id');
   Route::post('/mesasdecambio', [Mesa_de_CambioController::class, 'store']);
   Route::put('/mesasdecambio/{id}', [Mesa_de_CambioController::class, 'update']);
   Route::delete('/mesasdecambio/{id}', [Mesa_de_CambioController::class, 'destroy']);

   //EndPoints para Arqueos
   Route::get('/arqueos', [ArqueoController::class, 'index']);
   Route::get('/arqueos/{id}', [ArqueoController::class, 'indexid'])->whereNumber('id');
   Route::get('/arqueos/ultimo/{id_usuario}', [ArqueoController::class, 'ultimoPorUsuario']);
   Route::get('/arqueos/detalle/{id_arqueo}', [ArqueoController::class, 'detallePorArqueo']);
   Route::post('/arqueos', [ArqueoController::class, 'store']);
   Route::put('/arqueos/{id}', [ArqueoController::class, 'update']);
   Route::delete('/arqueos/{id}', [ArqueoController::class, 'destroy']);

   // Solicitudes de efectivo: salida y aceptación enlazadas en una transacción.
   Route::get('/solicitudespases/destinatarios', [\App\Http\Controllers\Api\SolicitudPaseController::class, 'destinatarios']);
   Route::get('/solicitudespases', [\App\Http\Controllers\Api\SolicitudPaseController::class, 'index']);
   Route::get('/solicitudespases/{id}', [\App\Http\Controllers\Api\SolicitudPaseController::class, 'indexid'])->whereNumber('id');
   Route::post('/solicitudespases', [\App\Http\Controllers\Api\SolicitudPaseController::class, 'store']);
   Route::post('/solicitudespases/{id}/aceptar', [\App\Http\Controllers\Api\SolicitudPaseController::class, 'aceptar'])->whereNumber('id');
   Route::post('/solicitudespases/{id}/rechazar', [\App\Http\Controllers\Api\SolicitudPaseController::class, 'rechazar'])->whereNumber('id');

   //EndPoints para Pases
   Route::get('/pases', [PasesController::class, 'index']);
   Route::get('/pases/{id}', [PasesController::class, 'indexid']);
   Route::get('/pases/ultimos/{id_usuario}', [PasesController::class, 'ultimosPorUsuario']);
   Route::post('/pases', [PasesController::class, 'store']);
   Route::put('/pases/{id}', [PasesController::class, 'update']);
   Route::delete('/pases/{id}', [PasesController::class, 'destroy']);

   //EndPoints para Cheques
   Route::get('/cheques', [ChequesController::class, 'index']);
   Route::get('/cheques/{id}', [ChequesController::class, 'indexid']);
   Route::get('/cheques/arqueo/{id_arqueo}', [ChequesController::class, 'porArqueo']);
   Route::post('/cheques', [ChequesController::class, 'store']);
   Route::put('/cheques/{id}', [ChequesController::class, 'update']);
   Route::delete('/cheques/{id}', [ChequesController::class, 'destroy']);

   //EndPoints para Entradas y Salidas
   Route::get('/entradasysalidas', [EntradasYSalidasController::class, 'index']);
   Route::get('/entradasysalidas/{id}', [EntradasYSalidasController::class, 'indexid']);
   Route::post('/entradasysalidas', [EntradasYSalidasController::class, 'store']);
   Route::put('/entradasysalidas/{id}', [EntradasYSalidasController::class, 'update']);
   Route::delete('/entradasysalidas/{id}', [EntradasYSalidasController::class, 'destroy']);

   //EndPoints para Precios de proveedor del dólar
   Route::get('/precioproveedordolar', [PrecioProveedorDolarController::class, 'index']);
   Route::get('/precioproveedordolar/ultimo', [PrecioProveedorDolarController::class, 'ultimo']);
   Route::get('/precioproveedordolar/{id}', [PrecioProveedorDolarController::class, 'indexid'])->whereNumber('id');
   Route::post('/precioproveedordolar', [PrecioProveedorDolarController::class, 'store']);
   Route::put('/precioproveedordolar/{id}', [PrecioProveedorDolarController::class, 'update']);
   Route::delete('/precioproveedordolar/{id}', [PrecioProveedorDolarController::class, 'destroy']);

   // Registros base por jornada. Las lecturas se filtran por el usuario autenticado.
   Route::get('/arqueos/{id}/resumen', [ArqueoController::class, 'resumen'])->whereNumber('id');
   Route::get('/arqueos/{id}/registros', [ArqueoController::class, 'registros'])->whereNumber('id');
   Route::post('/arqueos/{id}/cerrar', [ArqueoController::class, 'cerrar'])->whereNumber('id');

   foreach ([
      'arqueos' => ArqueoController::class,
      'pases' => PasesController::class,
      'cheques' => ChequesController::class,
      'entradasysalidas' => EntradasYSalidasController::class,
      'precioproveedordolar' => PrecioProveedorDolarController::class,
   ] as $uri => $controller) {
      Route::patch('/'.$uri.'/{id}', [$controller, 'update'])->whereNumber('id');
   }

   foreach ([
      'cajas' => \App\Http\Controllers\Api\CajaController::class,
      'detallearqueo' => \App\Http\Controllers\Api\DetalleArqueoController::class,
      'custodiascheques' => \App\Http\Controllers\Api\ArqueoChequeController::class,
      'tasascambioarqueo' => \App\Http\Controllers\Api\TasaCambioArqueoController::class,
      'operacionescambiocaja' => \App\Http\Controllers\Api\OperacionCambioCajaController::class,
      'remesasbancarias' => \App\Http\Controllers\Api\RemesaBancariaController::class,
      'servicioscaja' => \App\Http\Controllers\Api\ServicioCajaController::class,
      'conteosservicios' => \App\Http\Controllers\Api\ConteoServicioDiarioController::class,
      'enrolamientos' => \App\Http\Controllers\Api\EnrolamientoController::class,
      'conteospapeleria' => \App\Http\Controllers\Api\ConteoPapeleriaController::class,
      'enviosdhl' => \App\Http\Controllers\Api\EnvioDhlController::class,
      'detallesenviosdhl' => \App\Http\Controllers\Api\DetalleEnvioDhlController::class,
   ] as $uri => $controller) {
      Route::get('/'.$uri, [$controller, 'index']);
      Route::get('/'.$uri.'/{id}', [$controller, 'indexid'])->whereNumber('id');
      Route::post('/'.$uri, [$controller, 'store']);
      Route::match(['put', 'patch'], '/'.$uri.'/{id}', [$controller, 'update'])->whereNumber('id');
      Route::delete('/'.$uri.'/{id}', [$controller, 'destroy'])->whereNumber('id');
   }
   Route::get('/historialcaja', [\App\Http\Controllers\Api\HistorialCajaController::class, 'index']);
   Route::get('/historialcaja/{id}', [\App\Http\Controllers\Api\HistorialCajaController::class, 'indexid'])->whereNumber('id');
   require __DIR__.'/administracion.php';
   require __DIR__.'/libro_diario.php';
   require __DIR__.'/servicios_externos.php';
});




