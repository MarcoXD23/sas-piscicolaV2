<?php

use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\CosechaController;
use App\Http\Controllers\Admin\LagoController;
use App\Http\Controllers\Admin\MedicionAguaController;
use App\Http\Controllers\Admin\MortalidadController;
use App\Http\Controllers\Admin\MuestreoController;
use App\Http\Controllers\Admin\NominaController;
use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Admin\RacionController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\SiembraController;
use App\Http\Controllers\Admin\SolicitudController;
use App\Http\Controllers\Admin\TareaController;
use App\Http\Controllers\Admin\TrasladoController;
use App\Http\Controllers\Admin\TratamientoController;
use App\Http\Controllers\Admin\TurnoController;
use App\Http\Controllers\Admin\VentaController;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\EspecieController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MiTrabajoController;
use App\Http\Controllers\Noche\AireacionController;
use App\Http\Controllers\Noche\NocheController;
use App\Http\Controllers\Noche\RondaController;
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

        Route::get('/nomina', [NominaController::class, 'index'])->name('nomina.index');
        Route::post('/nomina', [NominaController::class, 'store'])->name('nomina.store');
        Route::post('/nomina/{liquidacion}/pagar', [NominaController::class, 'pagar'])->name('nomina.pagar');
        Route::delete('/nomina/{liquidacion}', [NominaController::class, 'destroy'])->name('nomina.destroy');
        Route::put('/personal/{usuario}/tarifas', [NominaController::class, 'tarifas'])->whereNumber('usuario')->name('nomina.tarifas');

        Route::get('/solicitudes', [SolicitudController::class, 'index'])->name('solicitudes.index');
        Route::post('/permisos/{permiso}/revisar', [SolicitudController::class, 'revisarPermiso'])->name('permisos.revisar');
        Route::post('/horas-extras/{horaExtra}/revisar', [SolicitudController::class, 'revisarHoras'])->name('horas-extras.revisar');

        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
        Route::put('/agenda/{evento}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::post('/agenda/{evento}/completar', [AgendaController::class, 'completar'])->name('agenda.completar');
        Route::delete('/agenda/{evento}', [AgendaController::class, 'destroy'])->name('agenda.destroy');

        Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
        Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
        Route::post('/ventas/{venta}/abonos', [VentaController::class, 'abonar'])->name('ventas.abonar');

        Route::get('/bodega', [BodegaController::class, 'index'])->name('bodega.index');
        Route::post('/bodega', [BodegaController::class, 'store'])->name('bodega.store');
    });

    Route::middleware('rol:propietario,administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/lagos', [LagoController::class, 'store'])->name('lagos.store');
        Route::post('/lagos/{lago}/siembras', [SiembraController::class, 'store'])->name('siembras.store');
        Route::post('/siembras/{siembra}/muestreos', [MuestreoController::class, 'store'])->name('muestreos.store');
        Route::post('/siembras/{siembra}/finalizar', [SiembraController::class, 'finalizar'])->name('siembras.finalizar');
        Route::post('/siembras/{siembra}/traslados', [TrasladoController::class, 'store'])->name('traslados.store');
        Route::post('/siembras/{siembra}/cosechas', [CosechaController::class, 'store'])->name('cosechas.store');
        Route::post('/lagos/{lago}/mediciones-agua', [MedicionAguaController::class, 'store'])->name('mediciones-agua.store');
        Route::post('/lagos/{lago}/tratamientos', [TratamientoController::class, 'store'])->name('tratamientos.store');

        Route::get('/tareas', [TareaController::class, 'index'])->name('tareas.index');
        Route::post('/tareas', [TareaController::class, 'store'])->name('tareas.store');
        Route::delete('/tareas/{tarea}', [TareaController::class, 'destroy'])->name('tareas.destroy');

        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/mensual', [ReporteController::class, 'mensual'])->name('reportes.mensual');
        Route::get('/reportes/libro-de-campo', [ReporteController::class, 'libroCampo'])->name('reportes.libro-campo');
    });

    Route::middleware('rol:propietario,administrador,trabajador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/lagos', [LagoController::class, 'index'])->name('lagos.index');
        Route::get('/lagos/{lago}', [LagoController::class, 'show'])->whereNumber('lago')->name('lagos.show');

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

    // Asistente IA: un solo widget para todos los roles (el contexto se filtra por rol)
    Route::get('/asistente', [AsistenteController::class, 'historial'])->name('asistente.historial');
    Route::post('/asistente', [AsistenteController::class, 'enviar'])->middleware('throttle:asistente')->name('asistente.enviar');
    Route::delete('/asistente', [AsistenteController::class, 'reiniciar'])->name('asistente.reiniciar');

    // Autogestión del personal de campo: tareas, permisos y horas extra
    Route::middleware('rol:trabajador,celador')->prefix('mi-trabajo')->name('mi-trabajo.')->group(function () {
        Route::get('/', [MiTrabajoController::class, 'index'])->name('index');
        Route::post('/tareas/{tarea}/completar', [MiTrabajoController::class, 'completar'])->name('tareas.completar');
        Route::post('/permisos', [MiTrabajoController::class, 'solicitarPermiso'])->name('permisos.store');
        Route::post('/horas-extras', [MiTrabajoController::class, 'reportarHoras'])->name('horas-extras.store');
    });

    // Seguridad & Noche: el celador solo registra dentro de su turno
    Route::middleware('rol:propietario,administrador,celador')->prefix('noche')->name('noche.')->group(function () {
        Route::get('/', [NocheController::class, 'index'])->name('index');

        Route::middleware('turno:celador')->group(function () {
            Route::post('/rondas', [RondaController::class, 'store'])->name('rondas.store');
            Route::post('/lagos/{lago}/aireador', [AireacionController::class, 'encender'])->name('aireador.encender');
            Route::post('/aireaciones/{aireacion}/apagar', [AireacionController::class, 'apagar'])->name('aireador.apagar');
            Route::post('/lagos/{lago}/mediciones', [MedicionAguaController::class, 'store'])->name('mediciones.store');
        });
    });
});
