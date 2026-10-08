<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Rutas;
use Tests\TestCase;

/**
 * Revisiones de cierre: seguridad de las rutas, traducción al español y aplicación instalable.
 */
class CierreTest extends TestCase
{
    use RefreshDatabase;

    /** Rutas que no exigen sesión, a propósito. */
    private const PUBLICAS = ['login', 'registro', 'olvide-contrasena', 'restablecer-contrasena', 'restablecer-contrasena/{token}', 'ingreso/dos-pasos', 'api/login', 'sanctum/csrf-cookie', 'up', 'plataforma/login'];

    /** @return list<Route> Rutas privadas de las fincas (el panel de plataforma se revisa aparte) */
    private function rutas(): array
    {
        return array_values(array_filter(Rutas::getRoutes()->getRoutes(), fn (Route $ruta) => ! in_array($ruta->uri(), self::PUBLICAS, true)
            && ! str_starts_with($ruta->uri(), 'plataforma')));
    }

    public function test_las_rutas_del_panel_de_plataforma_exigen_su_propio_ingreso_y_no_usan_finca(): void
    {
        $rutas = collect(Rutas::getRoutes()->getRoutes())->filter(fn (Route $ruta) => str_starts_with($ruta->uri(), 'plataforma') && $ruta->uri() !== 'plataforma/login');

        $this->assertNotEmpty($rutas);
        foreach ($rutas as $ruta) {
            $this->assertContains('auth:plataforma', $ruta->gatherMiddleware(), "La ruta {$ruta->uri()} no exige el ingreso de plataforma.");
            $this->assertNotContains('tenant', $ruta->gatherMiddleware());
        }
    }

    public function test_toda_ruta_privada_exige_sesion_y_fija_la_finca(): void
    {
        foreach ($this->rutas() as $ruta) {
            $middleware = $ruta->gatherMiddleware();
            $autenticada = collect($middleware)->contains(fn ($m) => $m === 'auth' || str_starts_with($m, 'auth:'));

            $this->assertTrue($autenticada, "La ruta {$ruta->uri()} no exige sesión.");
            $this->assertContains('tenant', $middleware, "La ruta {$ruta->uri()} no fija la finca (tenant).");
        }
    }

    public function test_las_rutas_de_modulos_restringidos_exigen_rol(): void
    {
        $restringidas = collect($this->rutas())->filter(fn (Route $ruta) => str_starts_with((string) $ruta->getName(), 'admin.')
            || str_starts_with((string) $ruta->getName(), 'noche.')
            || str_starts_with((string) $ruta->getName(), 'mi-trabajo.')
            || str_starts_with((string) $ruta->getName(), 'especies.'));

        $this->assertNotEmpty($restringidas);
        foreach ($restringidas as $ruta) {
            $this->assertTrue(
                collect($ruta->gatherMiddleware())->contains(fn ($m) => str_starts_with($m, 'rol:')),
                "La ruta {$ruta->getName()} no restringe por rol."
            );
        }
    }

    public function test_las_rutas_de_archivos_privados_no_estan_expuestas(): void
    {
        $this->assertFalse(Rutas::has('storage.local'));
        $this->assertFalse(collect(Rutas::getRoutes()->getRoutes())->contains(fn (Route $ruta) => str_starts_with($ruta->uri(), 'storage/')));
    }

    public function test_los_mensajes_de_validacion_salen_en_espanol(): void
    {
        $this->post(route('registro'), [])
            ->assertSessionHasErrors([
                'email' => 'El campo correo es obligatorio.',
                'password' => 'El campo contraseña es obligatorio.',
                'name' => 'El campo nombre es obligatorio.',
            ]);

        $this->post(route('registro'), ['email' => 'no-es-correo'])->assertSessionHasErrors(['email' => 'El campo correo debe ser un correo válido.']);
    }

    public function test_la_aplicacion_es_instalable_y_tiene_pagina_sin_conexion(): void
    {
        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("'/offline.html'", $sw);
        // Las páginas con datos de la finca no se guardan en caché
        $this->assertStringContainsString("peticion.mode === 'navigate'", $sw);

        $offline = file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('Sin conexión', $offline);
        $this->assertLessThan(10 * 1024, strlen($offline));

        $this->assertStringContainsString("register('/sw.js')", file_get_contents(resource_path('js/app.js')));
    }
}
