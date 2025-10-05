<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TrabajadorController;
use App\Http\Controllers\Api\SubAgenciaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\MesasCambioController;
use App\Http\Controllers\AuthController;


//EndPoints para Autenticación
 
Route::post('/login', [AuthController::class, 'login']);
Route::middleware(['auth:sanctum'])->group(function () {
   //EndPoint para Cerrar Sesión
   Route::post('/logout', [AuthController::class, 'logout']);
   //EndPoints para Usuarios
   Route::get('/usuarios', [UserController::class, 'index']);
   Route::get('/usuarios/{id}', [UserController::class, 'indexid']);
   Route::post('/usuarios', [AuthController::class, 'store']);
   Route::put('/usuarios/{id}', [UserController::class, 'update']);
   Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
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
   Route::get('/clientes/cedula/{cedula}', [ClienteController::class, 'indexcedula']);
   Route::post('/clientes', [ClienteController::class, 'store']);
   Route::put('/clientes/{id}', [ClienteController::class, 'update']);
   Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);
   //EndPoints para Mesas de Cambio
   Route::get('/mesasdecambio', [MesasCambioController::class, 'index']);
   Route::get('/mesasdecambio/{id}', [MesasCambioController::class, 'indexid']);
   Route::post('/mesasdecambio', [MesasCambioController::class, 'store']);
   Route::put('/mesasdecambio/{id}', [MesasCambioController::class, 'update']);
   Route::delete('/mesasdecambio/{id}', [MesasCambioController::class, 'destroy']);
});




