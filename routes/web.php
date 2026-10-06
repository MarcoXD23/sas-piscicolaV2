<?php

use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\LagoController;
use App\Http\Controllers\Admin\MortalidadController;
use App\Http\Controllers\Admin\MuestreoController;
use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Admin\RacionController;
use App\Http\Controllers\Admin\SiembraController;
use App\Http\Controllers\Admin\TurnoController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\EspecieController;
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

        Route::get('/turnos', [TurnoController::class, 'index'])->name('turnos.index');
        Route::post('/turnos', [TurnoController::class, 'store'])->name('turnos.store');
        Route::delete('/turnos/{turno}', [TurnoController::class, 'destroy'])->name('turnos.destroy');

        Route::get('/bodega', [BodegaController::class, 'index'])->name('bodega.index');
        Route::post('/bodega', [BodegaController::class, 'store'])->name('bodega.store');
    });

    Route::middleware('rol:propietario,administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/lagos', [LagoController::class, 'store'])->name('lagos.store');
        Route::post('/lagos/{lago}/siembras', [SiembraController::class, 'store'])->name('siembras.store');
        Route::post('/siembras/{siembra}/muestreos', [MuestreoController::class, 'store'])->name('muestreos.store');
        Route::post('/siembras/{siembra}/finalizar', [SiembraController::class, 'finalizar'])->name('siembras.finalizar');
    });

    Route::middleware('rol:propietario,administrador,trabajador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/lagos', [LagoController::class, 'index'])->name('lagos.index');

        // El trabajador solo registra alimentación y mortalidad dentro de su turno
        Route::middleware('turno:trabajador')->group(function () {
            Route::post('/siembras/{siembra}/mortalidades', [MortalidadController::class, 'store'])->name('mortalidades.store');
            Route::post('/lagos/{lago}/raciones', [RacionController::class, 'store'])->name('raciones.store');
        });
    });

    Route::middleware('rol:propietario,administrador,trabajador')->group(function () {
        Route::get('/especies', [EspecieController::class, 'index'])->name('especies.index');
        Route::get('/especies/{slug}', [EspecieController::class, 'show'])->name('especies.show');
    });
});
