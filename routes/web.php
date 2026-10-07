<?php

use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Admin\AjustesFincaController;
use App\Http\Controllers\Admin\AlertaController;
use App\Http\Controllers\Admin\AvisoPescaController;
use App\Http\Controllers\Admin\BitacoraController;
use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\ConversionController;
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
use App\Http\Controllers\Auth\RecuperarContrasenaController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\EspecieController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MiTrabajoController;
use App\Http\Controllers\Noche\AireacionController;
use App\Http\Controllers\Noche\NocheController;
use App\Http\Controllers\Noche\RondaController;
use App\Http\Controllers\Plataforma\FincaController as PlataformaFincaController;
use App\Http\Controllers\Plataforma\LoginController as PlataformaLoginController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:ingreso');

    Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:registro');

    Route::get('/olvide-contrasena', [RecuperarContrasenaController::class, 'create'])->name('password.request');
    Route::post('/olvide-contrasena', [RecuperarContrasenaController::class, 'store'])->middleware('throttle:pedir-enlace-contrasena')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [RecuperarContrasenaController::class, 'edit'])->name('password.reset');
    Route::post('/restablecer-contrasena', [RecuperarContrasenaController::class, 'update'])->middleware('throttle:restablecer-contrasena')->name('password.update');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/', InicioController::class)->name('inicio');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Exclusivo del propietario: personal, turnos, nómina, solicitudes, ajustes, agenda, bitácora y despachos de bodega
    Route::middleware('rol:propietario')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/personal', [PersonalController::class, 'index'])->name('personal.index');
        Route::put('/personal/{usuario}', [PersonalController::class, 'update'])
            ->whereNumber('usuario')
            ->name('personal.update');
        Route::put('/personal/mi-rol-administrador', [PersonalController::class, 'miRolAdministrador'])->name('personal.mi-rol-administrador');

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

        Route::get('/ajustes', [AjustesFincaController::class, 'edit'])->name('ajustes.edit');
        Route::put('/ajustes', [AjustesFincaController::class, 'update'])->name('ajustes.update');

        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
        Route::put('/agenda/{evento}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::post('/agenda/{evento}/completar', [AgendaController::class, 'completar'])->name('agenda.completar');
        Route::delete('/agenda/{evento}', [AgendaController::class, 'destroy'])->name('agenda.destroy');

        Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');

        Route::post('/bodega/despachos', [BodegaController::class, 'despachar'])->name('bodega.despachos.store');
        Route::post('/bodega/despachos/{despacho}/revisado', [BodegaController::class, 'marcarRevisado'])->name('bodega.despachos.revisado');
    });

    // Consulta de propietario y administrador (el propietario solo lee)
    Route::middleware('rol:propietario,administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::get('/conversion', [ConversionController::class, 'index'])->name('conversion.index');
        Route::get('/bodega', [BodegaController::class, 'index'])->name('bodega.index');
        Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');

        Route::get('/reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('/reportes/mensual', [ReporteController::class, 'mensual'])->name('reportes.mensual');
        Route::get('/reportes/libro-de-campo', [ReporteController::class, 'libroCampo'])->name('reportes.libro-campo');
        Route::get('/reportes/mensual/excel', [ReporteController::class, 'mensualExcel'])->name('reportes.mensual.excel');
        Route::get('/reportes/mensual/pdf', [ReporteController::class, 'mensualPdf'])->name('reportes.mensual.pdf');
        Route::get('/reportes/libro-de-campo/excel', [ReporteController::class, 'libroCampoExcel'])->name('reportes.libro-campo.excel');
        Route::get('/reportes/libro-de-campo/pdf', [ReporteController::class, 'libroCampoPdf'])->name('reportes.libro-campo.pdf');
    });

    // Operación de la finca: solo el administrador (o el propietario que también es administrador)
    Route::middleware('rol:administrador')->prefix('admin')->name('admin.')->group(function () {
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

        Route::post('/ventas', [VentaController::class, 'store'])->name('ventas.store');
        Route::post('/ventas/{venta}/abonos', [VentaController::class, 'abonar'])->name('ventas.abonar');

        Route::post('/bodega/recepciones', [BodegaController::class, 'recibir'])->name('bodega.recepciones.store');

        Route::post('/avisos-pesca/{aviso}/atender', [AvisoPescaController::class, 'atender'])->name('avisos-pesca.atender');
    });

    Route::middleware('rol:propietario,administrador,trabajador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/lagos', [LagoController::class, 'index'])->name('lagos.index');
        Route::get('/lagos/{lago}', [LagoController::class, 'show'])->whereNumber('lago')->name('lagos.show');
    });

    // Alimentación y mortalidad: el administrador y el trabajador en su turno (también enviadas después de registrarse sin conexión)
    Route::middleware(['rol:administrador,trabajador', 'sin_conexion', 'turno:trabajador'])->prefix('admin')->name('admin.')->group(function () {
        Route::post('/siembras/{siembra}/mortalidades', [MortalidadController::class, 'store'])->name('mortalidades.store');
        Route::post('/lagos/{lago}/raciones', [RacionController::class, 'store'])->name('raciones.store');
    });

    // Aviso «listo para pescar»: lo reporta el trabajador, solo en su turno
    Route::middleware(['rol:trabajador', 'sin_conexion', 'turno:trabajador'])->prefix('admin')->name('admin.')->group(function () {
        Route::post('/lagos/{lago}/avisos-pesca', [AvisoPescaController::class, 'store'])->name('avisos-pesca.store');
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

    // Seguridad & Noche: el celador registra solo en su turno; propietario y administrador consultan (solo lectura)
    Route::middleware('rol:propietario,administrador,celador')->prefix('noche')->name('noche.')->group(function () {
        Route::get('/', [NocheController::class, 'index'])->name('index');

        // Ronda y medición: también enviadas después de registrarse sin conexión (con la hora real del teléfono)
        Route::middleware(['rol:celador', 'sin_conexion', 'turno:celador'])->group(function () {
            Route::post('/rondas', [RondaController::class, 'store'])->name('rondas.store');
            Route::post('/lagos/{lago}/mediciones', [MedicionAguaController::class, 'store'])->name('mediciones.store');
        });

        // El aireador depende de su estado actual: solo con conexión
        Route::middleware(['rol:celador', 'turno:celador'])->group(function () {
            Route::post('/lagos/{lago}/aireador', [AireacionController::class, 'encender'])->name('aireador.encender');
            Route::post('/aireaciones/{aireacion}/apagar', [AireacionController::class, 'apagar'])->name('aireador.apagar');
        });
    });
});

// Panel de plataforma (dueño del software): guard «plataforma», sin finca ni roles de finca
Route::prefix('plataforma')->name('plataforma.')->group(function () {
    Route::middleware('guest:plataforma')->group(function () {
        Route::get('/login', [PlataformaLoginController::class, 'create'])->name('login');
        Route::post('/login', [PlataformaLoginController::class, 'store'])->middleware('throttle:ingreso-plataforma');
    });

    Route::middleware('auth:plataforma')->group(function () {
        Route::post('/logout', [PlataformaLoginController::class, 'destroy'])->name('logout');
        Route::get('/', [PlataformaFincaController::class, 'index'])->name('fincas.index');
        Route::post('/fincas/{finca}/suspender', [PlataformaFincaController::class, 'suspender'])->name('fincas.suspender');
        Route::post('/fincas/{finca}/reactivar', [PlataformaFincaController::class, 'reactivar'])->name('fincas.reactivar');
    });
});
