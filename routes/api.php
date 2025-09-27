<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TrabajadorController;
use App\Http\Controllers\Api\SubAgenciaController;
use App\Http\Controllers\Api\ClienteController;
use App\Http\Controllers\Api\TasaRealizadasController;


//API Usuarios
Route::get('/usuarios', [UserController::class, 'index']);
Route::get('/usuarios/{id}', [UserController::class, 'indexid']);
Route::post('/usuarios', [UserController::class, 'store']);
Route::put('/usuarios/{id}', [UserController::class, 'update']);
Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);

//API Trabajadores
Route::get('/trabajadores', [TrabajadorController::class, 'index']);
Route::get('/trabajadores/{id}', [TrabajadorController::class, 'indexid']);
Route::post('/trabajadores', [TrabajadorController::class, 'store']);
Route::put('/trabajadores/{id}', [TrabajadorController::class, 'update']);
Route::delete('/trabajadores/{id}', [TrabajadorController::class, 'destroy']);

//API SubAgencias
Route::get('/subagencias', [SubAgenciaController::class, 'index']);
Route::get('/subagencias/{id}', [SubAgenciaController::class, 'indexid']);
Route::post('/subagencias', [SubAgenciaController::class, 'store']);
Route::put('/subagencias/{id}', [SubAgenciaController::class, 'update']);
Route::delete('/subagencias/{id}', [SubAgenciaController::class, 'destroy']);

//API Clientes
Route::get('/clientes', [ClienteController::class, 'index']);
Route::get('/clientes/{id}', [ClienteController::class, 'indexid']);
Route::post('/clientes', [ClienteController::class, 'store']);
Route::put('/clientes/{id}', [ClienteController::class, 'update']);
Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);

//API TasasRealizadas
Route::get('/tasasrealizadas', [TasaRealizadasController::class, 'index']);
Route::get('/tasasrealizadas/{id}', [TasaRealizadasController::class, 'indexid']);
Route::post('/tasasrealizadas', [TasaRealizadasController::class, 'store']);
Route::put('/tasasrealizadas/{id}', [TasaRealizadasController::class, 'update']);
Route::delete('/tasasrealizadas/{id}', [TasaRealizadasController::class, 'destroy']);
