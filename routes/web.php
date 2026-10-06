<?php

use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\InicioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:10,1');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/', InicioController::class)->name('inicio');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('rol:propietario')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/personal', [PersonalController::class, 'index'])->name('personal.index');
        Route::put('/personal/{usuario}', [PersonalController::class, 'update'])
            ->whereNumber('usuario')
            ->name('personal.update');
            
        Route::get('/bodega', [\App\Http\Controllers\Admin\BodegaController::class, 'index'])->name('bodega.index');
        Route::post('/bodega', [\App\Http\Controllers\Admin\BodegaController::class, 'store'])->name('bodega.store');
    });

    Route::middleware('rol:propietario,administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/lagos', [\App\Http\Controllers\Admin\LagoController::class, 'store'])->name('lagos.store');
        Route::post('/lagos/{lago}/siembras', [\App\Http\Controllers\Admin\SiembraController::class, 'store'])->name('siembras.store');
        Route::post('/siembras/{siembra}/muestreos', [\App\Http\Controllers\Admin\MuestreoController::class, 'store'])->name('muestreos.store');
    });

    Route::middleware('rol:propietario,administrador,trabajador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/lagos', [\App\Http\Controllers\Admin\LagoController::class, 'index'])->name('lagos.index');
        Route::post('/siembras/{siembra}/mortalidades', [\App\Http\Controllers\Admin\MortalidadController::class, 'store'])->name('mortalidades.store');
        Route::post('/lagos/{lago}/raciones', [\App\Http\Controllers\Admin\RacionController::class, 'store'])->name('raciones.store');
    });
});

