<?php

use App\Http\Controllers\Api\AdministracionController;
use App\Http\Middleware\RequireAdministrador;
use App\Support\Administracion\Recursos;
use Illuminate\Support\Facades\Route;

// Este archivo se carga dentro del grupo auth:sanctum de api.php.
Route::prefix('administracion')->middleware(RequireAdministrador::class)->group(function () {
    Route::get('/capital', [AdministracionController::class, 'capital']);
    foreach (Recursos::DEFINICIONES as $uri => $definition) {
        Route::get('/'.$uri, [AdministracionController::class, 'index'])->defaults('recurso', $uri);
        Route::get('/'.$uri.'/{id}', [AdministracionController::class, 'show'])->whereNumber('id')->defaults('recurso', $uri);
        if ($uri !== 'historial') {
            Route::post('/'.$uri, [AdministracionController::class, 'store'])->defaults('recurso', $uri);
            if ($uri !== 'cierres') {
                Route::match(['put', 'patch'], '/'.$uri.'/{id}', [AdministracionController::class, 'update'])->whereNumber('id')->defaults('recurso', $uri);
                Route::delete('/'.$uri.'/{id}', [AdministracionController::class, 'destroy'])->whereNumber('id')->defaults('recurso', $uri);
            }
        }
    }
    foreach ([
        'arqueosmadre' => ['get' => ['resumen'], 'post' => ['recalcular', 'cerrar']],
        'reportesmatriz' => ['post' => ['confirmar']],
        'conciliaciones' => ['post' => ['revisar']],
        'liquidaciones' => ['get' => ['resumen'], 'post' => ['cerrar']],
        'depositosliquidacion' => ['post' => ['acreditar']],
        'cuentas' => ['get' => ['saldo']], 'activos' => ['get' => ['inventario']],
        'proyectos' => ['get' => ['resumen']], 'financiamientos' => ['get' => ['resumen']],
        'incidencias' => ['get' => ['resumen']],
    ] as $uri => $methods) {
        foreach ($methods as $method => $actions) {
            foreach ($actions as $action) {
                Route::$method('/'.$uri.'/{id}/'.$action, [AdministracionController::class, 'action'])
                    ->whereNumber('id')->defaults('recurso', $uri)->defaults('accion', $action);
            }
        }
    }
});
