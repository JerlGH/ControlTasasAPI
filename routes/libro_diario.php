<?php

use App\Http\Controllers\Api\LibroDiarioController as Controller;
use App\Http\Middleware\RequireAdministrador;
use App\Support\Administracion\RecursosLibroDiario;
use Illuminate\Support\Facades\Route;

// Cargado dentro de auth:sanctum en api.php.
Route::prefix('administracion')->middleware(RequireAdministrador::class)->group(function () {
    foreach (RecursosLibroDiario::DEFINICIONES as $uri => $definition) {
        Route::get('/'.$uri, [Controller::class, 'index'])->defaults('recurso_diario', $uri);
        Route::get('/'.$uri.'/{id}', [Controller::class, 'show'])->whereNumber('id')->defaults('recurso_diario', $uri);
        Route::post('/'.$uri, [Controller::class, 'store'])->defaults('recurso_diario', $uri);
        if ($uri !== 'revisionesdiario') {
            Route::match(['put', 'patch'], '/'.$uri.'/{id}', [Controller::class, 'update'])->whereNumber('id')->defaults('recurso_diario', $uri);
            Route::delete('/'.$uri.'/{id}', [Controller::class, 'destroy'])->whereNumber('id')->defaults('recurso_diario', $uri);
        }
    }
    Route::get('/librosdiarios/{id}/resumen', [Controller::class, 'summary'])->whereNumber('id');
    Route::post('/librosdiarios/{id}/recalcular', [Controller::class, 'recalculate'])->whereNumber('id');
    Route::post('/librosdiarios/{id}/cerrar', [Controller::class, 'close'])->whereNumber('id');
    Route::post('/reportesremesascentrales/{id}/confirmar', [Controller::class, 'confirmReport'])->whereNumber('id');
});
