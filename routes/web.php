<?php

use App\Http\Controllers\Admin\AgendaController;
use App\Http\Controllers\Admin\AjustesFincaController;
use App\Http\Controllers\Admin\AjustesPesajeController;
use App\Http\Controllers\Admin\AlertaController;
use App\Http\Controllers\Admin\AvisoPescaController;
use App\Http\Controllers\Admin\BitacoraController;
use App\Http\Controllers\Admin\BodegaController;
use App\Http\Controllers\Admin\ConcentradoController;
use App\Http\Controllers\Admin\ConversionController;
use App\Http\Controllers\Admin\CosechaController;
use App\Http\Controllers\Admin\DatosFincaController;
use App\Http\Controllers\Admin\EspecieController as EspecieFincaController;
use App\Http\Controllers\Admin\EspecieFotoController;
use App\Http\Controllers\Admin\FaseController;
use App\Http\Controllers\Admin\JornadaController;
use App\Http\Controllers\Admin\JornaleroController;
use App\Http\Controllers\Admin\LagoController;
use App\Http\Controllers\Admin\MedicionAguaController;
use App\Http\Controllers\Admin\MortalidadController;
use App\Http\Controllers\Admin\MuestreoController;
use App\Http\Controllers\Admin\NominaController;
use App\Http\Controllers\Admin\PersonalController;
use App\Http\Controllers\Admin\PrimerUsoController;
use App\Http\Controllers\Admin\ProveedorController;
use App\Http\Controllers\Admin\RacionController;
use App\Http\Controllers\Admin\ReporteController;
use App\Http\Controllers\Admin\SeguridadFincaController;
use App\Http\Controllers\Admin\SiembraController;
use App\Http\Controllers\Admin\SolicitudController;
use App\Http\Controllers\Admin\TareaController;
use App\Http\Controllers\Admin\TrasladoController;
use App\Http\Controllers\Admin\TratamientoController;
use App\Http\Controllers\Admin\TurnoController;
use App\Http\Controllers\Admin\VentaController;
use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\Auth\DosPasosController;
use App\Http\Controllers\Auth\InvitacionFincaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RecuperarContrasenaController;
use App\Http\Controllers\Auth\RegistroController;
use App\Http\Controllers\CampoController;
use App\Http\Controllers\EspecieController;
use App\Http\Controllers\FotoEspecieController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MiCuentaController;
use App\Http\Controllers\MiTrabajoController;
use App\Http\Controllers\Noche\AireacionController;
use App\Http\Controllers\Noche\NocheController;
use App\Http\Controllers\Noche\RondaController;
use App\Http\Controllers\Plataforma\DosPasosController as PlataformaDosPasosController;
use App\Http\Controllers\Plataforma\FincaController as PlataformaFincaController;
use App\Http\Controllers\Plataforma\InvitacionController as PlataformaInvitacionController;
use App\Http\Controllers\Plataforma\LoginController as PlataformaLoginController;
use App\Http\Controllers\SaludController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Enlace de invitación para crear una finca (lo genera el panel de plataforma): un solo uso, vence en 7 días.
// Sin «guest»: con una sesión abierta la página lo avisa y ofrece cerrarla, en vez de mandar callado al inicio.
Route::middleware('throttle:invitacion')->group(function () {
    Route::get('/invitacion/{codigo}', [InvitacionFincaController::class, 'show'])->name('invitacion.show');
    Route::post('/invitacion/{codigo}', [InvitacionFincaController::class, 'store'])->name('invitacion.store');
    Route::post('/invitacion/{codigo}/logout', [InvitacionFincaController::class, 'salir'])->name('invitacion.salir');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:ingreso');

    Route::get('/registro', [RegistroController::class, 'create'])->name('registro');
    Route::post('/registro', [RegistroController::class, 'store'])->middleware('throttle:registro');

    Route::get('/olvide-contrasena', [RecuperarContrasenaController::class, 'create'])->name('password.request');
    Route::post('/olvide-contrasena', [RecuperarContrasenaController::class, 'store'])->middleware('throttle:pedir-enlace-contrasena')->name('password.email');
    Route::get('/restablecer-contrasena/{token}', [RecuperarContrasenaController::class, 'edit'])->name('password.reset');
    Route::post('/restablecer-contrasena', [RecuperarContrasenaController::class, 'update'])->middleware('throttle:restablecer-contrasena')->name('password.update');

    // Segundo paso del ingreso: la contraseña ya se revisó; falta el código de la app o uno de recuperación
    Route::get('/ingreso/dos-pasos', [DosPasosController::class, 'create'])->name('dos-pasos.create');
    Route::post('/ingreso/dos-pasos', [DosPasosController::class, 'store'])->middleware('throttle:dos-pasos')->name('dos-pasos.store');
});

// Estado del sistema para el monitoreo externo: solo «ok» o «falla», sin detalles
Route::get('/salud', SaludController::class)->middleware('throttle:salud')->name('salud');

// Registro rápido (modo de campo): cascarón público y sin datos, que el teléfono guarda para registrar sin conexión.
// Sin sesión: nunca lleva el token ni datos de nadie (CampoController)
Route::get('/campo', [CampoController::class, 'index'])
    ->withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->name('campo');

// Términos de uso y política de privacidad: públicos (con o sin sesión)
Route::get('/terminos', [LegalController::class, 'terminos'])->name('legal.terminos');
Route::get('/privacidad', [LegalController::class, 'privacidad'])->name('legal.privacidad');

// 'aceptacion': quien no aceptó la versión vigente de los términos va primero a aceptarlos
// 'dos_pasos_obligatoria': si la finca la exige, el propietario y el administrador la activan antes de seguir
Route::middleware(['auth', 'tenant', 'aceptacion', 'dos_pasos_obligatoria'])->group(function () {
    Route::get('/aceptar-terminos', [LegalController::class, 'aceptar'])->name('legal.aceptar');
    Route::post('/aceptar-terminos', [LegalController::class, 'guardarAceptacion'])->name('legal.aceptar.store');

    Route::get('/', InicioController::class)->name('inicio');

    // Modo de campo: paquete de trabajo (datos mínimos del rol) y token vigente para enviar lo registrado sin conexión
    Route::middleware('throttle:campo')->prefix('campo')->name('campo.')->group(function () {
        Route::get('/paquete', [CampoController::class, 'paquete'])->name('paquete');
        Route::get('/token', [CampoController::class, 'token'])->name('token');
    });
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Mi cuenta: todos los roles de la finca, siempre sobre la persona con sesión
    Route::prefix('mi-cuenta')->name('mi-cuenta.')->group(function () {
        Route::get('/', [MiCuentaController::class, 'index'])->name('index');
        Route::put('/contrasena', [MiCuentaController::class, 'cambiarContrasena'])->middleware('throttle:cambiar-contrasena')->name('contrasena');
        Route::put('/formato-hora', [MiCuentaController::class, 'formatoHora'])->name('formato-hora');
        Route::put('/nombre', [MiCuentaController::class, 'nombre'])->name('nombre');
        // Mis sesiones abiertas: cerrar una, o todas las demás pidiendo la contraseña actual
        Route::delete('/sesiones/{sesion}', [MiCuentaController::class, 'cerrarSesion'])->name('sesiones.cerrar');
        Route::post('/sesiones/cerrar-demas', [MiCuentaController::class, 'cerrarDemas'])->middleware('throttle:cambiar-contrasena')->name('sesiones.cerrar-demas');
        // Aviso de ingreso desde un equipo nuevo: «Entendido»
        Route::post('/ingresos/{ingreso}/visto', [MiCuentaController::class, 'avisoVisto'])->whereNumber('ingreso')->name('ingresos.visto');
        Route::get('/dos-pasos/activar', [MiCuentaController::class, 'activar'])->name('dos-pasos.activar');
        Route::post('/dos-pasos/activar', [MiCuentaController::class, 'confirmar'])->middleware('throttle:cambiar-contrasena')->name('dos-pasos.confirmar');
        Route::get('/dos-pasos/codigos', [MiCuentaController::class, 'codigos'])->name('dos-pasos.codigos');
        Route::delete('/dos-pasos', [MiCuentaController::class, 'desactivar'])->middleware('throttle:cambiar-contrasena')->name('dos-pasos.desactivar');
    });

    // Exclusivo del propietario: personal, turnos, nómina, solicitudes, ajustes, agenda, bitácora y despachos de bodega
    Route::middleware('rol:propietario')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/personal', [PersonalController::class, 'index'])->name('personal.index');
        Route::put('/personal/{usuario}', [PersonalController::class, 'update'])
            ->whereNumber('usuario')
            ->name('personal.update');
        Route::put('/personal/mi-rol-administrador', [PersonalController::class, 'miRolAdministrador'])->name('personal.mi-rol-administrador');
        // Datos de la finca: exportar (ZIP) y pedir o cancelar la eliminación
        Route::get('/datos', [DatosFincaController::class, 'index'])->name('datos.index');
        Route::post('/datos/exportar', [DatosFincaController::class, 'exportar'])->name('datos.exportar');
        Route::get('/datos/exportaciones/{exportacion}/descargar', [DatosFincaController::class, 'descargar'])->name('datos.descargar');
        Route::post('/datos/eliminar', [DatosFincaController::class, 'solicitarEliminacion'])->name('datos.eliminar');
        Route::get('/datos/eliminacion', [DatosFincaController::class, 'eliminacion'])->name('datos.eliminacion');
        Route::post('/datos/cancelar-eliminacion', [DatosFincaController::class, 'cancelarEliminacion'])->name('datos.cancelar');
        Route::post('/personal/{usuario}/quitar-dos-pasos', [PersonalController::class, 'quitarDosPasos'])->whereNumber('usuario')->name('personal.quitar-dos-pasos');

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
        Route::put('/ajustes/operacion', [AjustesFincaController::class, 'guardarOperacion'])->name('ajustes.operacion');
        // Seguridad de la finca: dos pasos obligatoria, cerrar la sesión del personal e ingresos del personal
        Route::put('/ajustes/seguridad', [SeguridadFincaController::class, 'guardar'])->name('ajustes.seguridad.guardar');
        Route::post('/ajustes/seguridad/cerrar-sesiones', [SeguridadFincaController::class, 'cerrarSesiones'])->middleware('throttle:cambiar-contrasena')->name('ajustes.seguridad.cerrar-sesiones');
        Route::get('/ajustes/seguridad/ingresos', [SeguridadFincaController::class, 'ingresos'])->name('ajustes.seguridad.ingresos');
        Route::get('/ajustes/fases', [FaseController::class, 'index'])->name('fases.index');
        Route::post('/ajustes/fases', [FaseController::class, 'store'])->name('fases.store');
        Route::put('/ajustes/fases/{fase}', [FaseController::class, 'update'])->whereNumber('fase')->name('fases.update');
        Route::delete('/ajustes/fases/{fase}', [FaseController::class, 'destroy'])->whereNumber('fase')->name('fases.destroy');
        Route::post('/ajustes/fases/{fase}/reglas', [FaseController::class, 'guardarRegla'])->whereNumber('fase')->name('fases.reglas.guardar');

        Route::get('/ajustes/concentrados', [ConcentradoController::class, 'index'])->name('concentrados.index');
        Route::post('/ajustes/concentrados', [ConcentradoController::class, 'store'])->name('concentrados.store');
        Route::put('/ajustes/concentrados/{concentrado}', [ConcentradoController::class, 'update'])->whereNumber('concentrado')->name('concentrados.update');
        Route::post('/ajustes/concentrados/racion', [ConcentradoController::class, 'guardarTramo'])->name('concentrados.racion.guardar');
        Route::delete('/ajustes/concentrados/racion/{tramo}', [ConcentradoController::class, 'eliminarTramo'])->whereNumber('tramo')->name('concentrados.racion.eliminar');
        Route::post('/ajustes/concentrados/racion/{especie}/restaurar', [ConcentradoController::class, 'restaurarEspecie'])->whereNumber('especie')->name('concentrados.racion.restaurar');

        Route::get('/agenda', [AgendaController::class, 'index'])->name('agenda.index');
        Route::post('/agenda', [AgendaController::class, 'store'])->name('agenda.store');
        Route::put('/agenda/{evento}', [AgendaController::class, 'update'])->name('agenda.update');
        Route::post('/agenda/{evento}/completar', [AgendaController::class, 'completar'])->name('agenda.completar');
        Route::delete('/agenda/{evento}', [AgendaController::class, 'destroy'])->name('agenda.destroy');

        Route::get('/bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');

        Route::post('/bodega/despachos', [BodegaController::class, 'despachar'])->name('bodega.despachos.store');
        Route::post('/bodega/despachos/{despacho}/revisado', [BodegaController::class, 'marcarRevisado'])->name('bodega.despachos.revisado');
    });

    // Jornaleros (personas sin cuenta en la app): propietario y administrador; anonimizar, solo el propietario
    Route::middleware('rol:propietario,administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/jornaleros', [JornaleroController::class, 'index'])->name('jornaleros.index');
        Route::post('/jornaleros', [JornaleroController::class, 'store'])->name('jornaleros.store');
        Route::get('/jornaleros/{jornalero}/editar', [JornaleroController::class, 'edit'])->whereNumber('jornalero')->name('jornaleros.edit');
        Route::put('/jornaleros/{jornalero}', [JornaleroController::class, 'update'])->whereNumber('jornalero')->name('jornaleros.update');
        Route::post('/jornaleros/{jornalero}/activo', [JornaleroController::class, 'cambiarActivo'])->whereNumber('jornalero')->name('jornaleros.activo');
    });
    Route::middleware('rol:propietario')->prefix('admin')->name('admin.')->group(function () {
        Route::post('/jornaleros/{jornalero}/anonimizar', [JornaleroController::class, 'anonimizar'])->whereNumber('jornalero')->name('jornaleros.anonimizar');
    });

    // Consulta de propietario y administrador (el propietario solo lee)
    Route::middleware('rol:propietario,administrador')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
        Route::get('/conversion', [ConversionController::class, 'index'])->name('conversion.index');
        Route::get('/bodega', [BodegaController::class, 'index'])->name('bodega.index');
        Route::get('/ventas', [VentaController::class, 'index'])->name('ventas.index');
        // Ocultar la lista de primeros pasos de una finca nueva
        Route::post('/primer-uso/ocultar', [PrimerUsoController::class, 'ocultar'])->name('primer-uso.ocultar');
        // Catálogo de especies de la finca: el propietario lo consulta, el administrador lo edita
        Route::get('/especies', [EspecieFincaController::class, 'index'])->name('especies.index');
        // Proveedores (alevinos, alimento, otros): el propietario consulta, el administrador edita
        Route::get('/proveedores', [ProveedorController::class, 'index'])->name('proveedores.index');
        Route::get('/proveedores/revision', [ProveedorController::class, 'revision'])->name('proveedores.revision');
        // Detalle de una cosecha (encabezado, pesadas y totales): el administrador y el propietario en solo lectura
        Route::get('/cosechas/{cosecha}', [CosechaController::class, 'show'])->whereNumber('cosecha')->name('cosechas.show');
        // Jornadas de proceso (solo con el ajuste encendido; si no, 404): el propietario solo consulta
        Route::get('/jornadas', [JornadaController::class, 'index'])->name('jornadas.index');
        Route::get('/jornadas/{jornada}', [JornadaController::class, 'show'])->whereNumber('jornada')->name('jornadas.show');

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
        Route::put('/lagos/{lago}', [LagoController::class, 'update'])->whereNumber('lago')->name('lagos.update');
        Route::post('/lagos/{lago}/siembras', [SiembraController::class, 'store'])->name('siembras.store');
        Route::post('/siembras/{siembra}/finalizar', [SiembraController::class, 'finalizar'])->name('siembras.finalizar');
        Route::post('/siembras/{siembra}/traslados', [TrasladoController::class, 'store'])->name('traslados.store');
        Route::get('/cosechas/crear', [CosechaController::class, 'create'])->name('cosechas.create');
        Route::post('/cosechas', [CosechaController::class, 'store'])->name('cosechas.store');
        Route::get('/jornadas/crear', [JornadaController::class, 'create'])->name('jornadas.create');
        Route::post('/jornadas', [JornadaController::class, 'store'])->name('jornadas.store');
        Route::post('/jornadas/{jornada}/lagos', [JornadaController::class, 'agregarLago'])->whereNumber('jornada')->name('jornadas.lagos.store');
        Route::post('/jornadas/{jornada}/pesajes', [JornadaController::class, 'guardarPesaje'])->whereNumber('jornada')->name('jornadas.pesajes.store');
        Route::delete('/jornadas/{jornada}/pesajes/{pesaje}', [JornadaController::class, 'eliminarPesaje'])->whereNumber(['jornada', 'pesaje'])->name('jornadas.pesajes.destroy');
        Route::get('/jornadas/{jornada}/cerrar', [JornadaController::class, 'cerrar'])->whereNumber('jornada')->name('jornadas.cerrar');
        Route::post('/jornadas/{jornada}/cerrar', [JornadaController::class, 'guardarCierre'])->whereNumber('jornada')->name('jornadas.cerrar.guardar');
        Route::post('/jornadas/{jornada}/cancelar', [JornadaController::class, 'cancelar'])->whereNumber('jornada')->name('jornadas.cancelar');
        Route::get('/ajustes-pesaje', [AjustesPesajeController::class, 'edit'])->name('ajustes-pesaje.edit');
        Route::post('/especies', [EspecieFincaController::class, 'store'])->name('especies.store');
        Route::get('/especies/{especie}/editar', [EspecieFincaController::class, 'edit'])->name('especies.edit');
        Route::put('/especies/{especie}', [EspecieFincaController::class, 'update'])->name('especies.update');
        Route::post('/especies/{especie}/activo', [EspecieFincaController::class, 'cambiarActivo'])->name('especies.activo');
        Route::post('/proveedores', [ProveedorController::class, 'store'])->name('proveedores.store');
        Route::get('/proveedores/{proveedor}/editar', [ProveedorController::class, 'edit'])->whereNumber('proveedor')->name('proveedores.edit');
        Route::put('/proveedores/{proveedor}', [ProveedorController::class, 'update'])->whereNumber('proveedor')->name('proveedores.update');
        Route::post('/proveedores/{proveedor}/activo', [ProveedorController::class, 'cambiarActivo'])->whereNumber('proveedor')->name('proveedores.activo');
        Route::post('/proveedores/revision', [ProveedorController::class, 'ligar'])->name('proveedores.ligar');
        Route::delete('/especies/{especie}', [EspecieFincaController::class, 'destroy'])->name('especies.destroy');
        // Fotos de una especie: la foto debe ser de esa especie (scopeBindings)
        Route::scopeBindings()->group(function () {
            Route::post('/especies/{especie}/fotos', [EspecieFotoController::class, 'store'])->name('especies.fotos.store');
            Route::post('/especies/{especie}/fotos/{foto}/orden', [EspecieFotoController::class, 'ordenar'])->name('especies.fotos.orden');
            Route::delete('/especies/{especie}/fotos/{foto}', [EspecieFotoController::class, 'destroy'])->name('especies.fotos.destroy');
        });
        Route::put('/ajustes-pesaje', [AjustesPesajeController::class, 'update'])->name('ajustes-pesaje.update');
        // También sin conexión desde Registro rápido (sin turno: el administrador no trabaja por turnos). Quién mide: el ajuste de la finca
        Route::post('/lagos/{lago}/mediciones-agua', [MedicionAguaController::class, 'store'])->middleware('sin_conexion')->name('mediciones-agua.store');
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

    // Muestreo: solo el administrador, sin turno (también enviado después de registrarse sin conexión en Registro rápido)
    Route::middleware(['rol:administrador', 'sin_conexion'])->prefix('admin')->name('admin.')->group(function () {
        Route::post('/siembras/{siembra}/muestreos', [MuestreoController::class, 'store'])->name('muestreos.store');
    });

    // Aviso «listo para pescar»: lo reporta el trabajador, solo en su turno
    Route::middleware(['rol:trabajador', 'sin_conexion', 'turno:trabajador'])->prefix('admin')->name('admin.')->group(function () {
        Route::post('/lagos/{lago}/avisos-pesca', [AvisoPescaController::class, 'store'])->name('avisos-pesca.store');
    });

    // Guía de especies: todos los roles de la finca (solo lectura)
    Route::middleware('rol:propietario,administrador,trabajador,celador')->group(function () {
        Route::get('/especies', [EspecieController::class, 'index'])->name('especies.index');
        Route::get('/especies/{especie}', [EspecieController::class, 'show'])->whereNumber('especie')->name('especies.show');
        // Fotos del disco privado: ligera para todos los roles de la finca; el original lo limita el controlador
        Route::get('/especies/fotos/{foto}/{version}', [FotoEspecieController::class, 'ver'])->whereNumber('foto')->whereIn('version', ['ligera', 'original'])->name('especies.fotos.ver');
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
        // Segundo paso del ingreso (código de la app o de recuperación)
        Route::get('/dos-pasos', [PlataformaDosPasosController::class, 'create'])->name('dos-pasos.create');
        Route::post('/dos-pasos', [PlataformaDosPasosController::class, 'store'])->middleware('throttle:dos-pasos')->name('dos-pasos.store');
    });

    // La verificación en dos pasos es obligatoria: sin ella activa solo se puede activar o salir
    Route::middleware(['auth:plataforma', 'plataforma_dos_pasos'])->group(function () {
        Route::post('/logout', [PlataformaLoginController::class, 'destroy'])->name('logout');
        Route::get('/dos-pasos/activar', [PlataformaDosPasosController::class, 'activar'])->name('dos-pasos.activar');
        Route::post('/dos-pasos/activar', [PlataformaDosPasosController::class, 'confirmar'])->middleware('throttle:dos-pasos')->name('dos-pasos.confirmar');
        Route::get('/dos-pasos/codigos', [PlataformaDosPasosController::class, 'codigos'])->name('dos-pasos.codigos');
        Route::get('/', [PlataformaFincaController::class, 'index'])->name('fincas.index');
        Route::post('/fincas/{finca}/suspender', [PlataformaFincaController::class, 'suspender'])->name('fincas.suspender');
        Route::post('/fincas/{finca}/reactivar', [PlataformaFincaController::class, 'reactivar'])->name('fincas.reactivar');
        Route::post('/fincas/{finca}/eliminar', [PlataformaFincaController::class, 'eliminar'])->name('fincas.eliminar');
        Route::post('/fincas/{finca}/cancelar-eliminacion', [PlataformaFincaController::class, 'cancelarEliminacion'])->name('fincas.cancelar-eliminacion');
        Route::post('/invitaciones', [PlataformaInvitacionController::class, 'store'])->name('invitaciones.store');
        Route::post('/invitaciones/{invitacion}/anular', [PlataformaInvitacionController::class, 'anular'])->whereNumber('invitacion')->name('invitaciones.anular');
    });
});
