<?php

use App\Http\Middleware\AceptacionVigente;
use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\CookieSeguraConHttps;
use App\Http\Middleware\EstablecerTenant;
use App\Http\Middleware\PlataformaConDosPasos;
use App\Http\Middleware\RegistroSinConexion;
use App\Http\Middleware\VerificarRol;
use App\Http\Middleware\VerificarTurno;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cabeceras de seguridad en toda la app (web y API)
        $middleware->append(CabecerasSeguridad::class);
        // Cookie de sesión segura con HTTPS (directo o vía proxy de confianza); corre antes de abrir la sesión
        $middleware->append(CookieSeguraConHttps::class);

        $middleware->alias([
            'tenant' => EstablecerTenant::class,
            'rol' => VerificarRol::class,
            'turno' => VerificarTurno::class,
            'sin_conexion' => RegistroSinConexion::class,   // siempre antes de 'turno'
            'aceptacion' => AceptacionVigente::class,       // términos y privacidad aceptados (versión vigente)
            'plataforma_dos_pasos' => PlataformaConDosPasos::class,   // panel de plataforma: dos pasos obligatoria
        ]);

        // El tenant debe fijarse antes del route model binding para que el scope aplique.
        $middleware->prependToPriorityList(SubstituteBindings::class, EstablecerTenant::class);

        // El panel de plataforma tiene su propio ingreso, separado del de las fincas
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('plataforma', 'plataforma/*') ? route('plataforma.login') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->is('plataforma', 'plataforma/*') ? route('plataforma.fincas.index') : route('inicio'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
