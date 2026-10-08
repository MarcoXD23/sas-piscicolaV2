<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:ingreso-api');

Route::middleware(['auth:sanctum', 'tenant', 'aceptacion'])->group(function () {
    Route::get('/yo', [AuthController::class, 'yo']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    // Con los términos pendientes, la app móvil los acepta aquí (los textos están en /terminos y /privacidad)
    Route::post('/terminos/aceptar', [LegalController::class, 'aceptarApi'])->name('api.terminos.aceptar');
});
