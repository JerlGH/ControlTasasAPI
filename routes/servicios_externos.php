<?php

use App\Http\Controllers\Api\ServicioExternoController as Controller;
use App\Http\Middleware\RequireAdministrador;
use App\Support\Administracion\RecursosServiciosExternos;
use Illuminate\Support\Facades\Route;

// Solo administracion; cargado dentro de auth:sanctum. No se replica en cajas.
Route::prefix('administracion')->middleware(RequireAdministrador::class)->group(function () {
    Route::get('/serviciosexternos/catalogo', [Controller::class, 'catalog']);
    Route::get('/serviciosexternos/resumen', [Controller::class, 'period']);
    foreach (RecursosServiciosExternos::DEFINICIONES as $uri => $definition) {
        Route::get('/'.$uri, [Controller::class, 'index'])->defaults('recurso_externo', $uri);
        Route::get('/'.$uri.'/{id}', [Controller::class, 'show'])->whereNumber('id')->defaults('recurso_externo', $uri);
        Route::post('/'.$uri, [Controller::class, 'store'])->defaults('recurso_externo', $uri);
        Route::match(['put', 'patch'], '/'.$uri.'/{id}', [Controller::class, 'update'])->whereNumber('id')->defaults('recurso_externo', $uri);
        Route::delete('/'.$uri.'/{id}', [Controller::class, 'destroy'])->whereNumber('id')->defaults('recurso_externo', $uri);
    }
    Route::get('/arqueosserviciosexternos/{id}/resumen', [Controller::class, 'summary'])->whereNumber('id');
    Route::post('/arqueosserviciosexternos/{id}/cerrar', [Controller::class, 'close'])->whereNumber('id');
    Route::post('/arqueosserviciosexternos/{id}/generarreporte', [Controller::class, 'generateReport'])->whereNumber('id');
    Route::post('/arqueosserviciosexternos/{id}/registrarcomisiones', [Controller::class, 'commissions'])->whereNumber('id');
});
