<?php

namespace Tests\Feature;

use App\Http\Middleware\CabecerasSeguridad;
use App\Models\AdministradorPlataforma;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Content-Security-Policy estricta: todo del mismo sitio, sin scripts ni estilos en línea, sin evaluar código y sin
 * iframes. Para que se cumpla, las vistas no tienen <script> ejecutable en línea, ni style="...", ni onclick/onsubmit.
 */
class CspTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
    }

    private function csp(): string
    {
        return implode('; ', CabecerasSeguridad::CSP);
    }

    public function test_la_politica_es_estricta(): void
    {
        $politica = $this->csp();
        foreach (["default-src 'self'", "script-src 'self'", "style-src 'self'", "frame-ancestors 'none'", "object-src 'none'", "base-uri 'self'", "form-action 'self'"] as $regla) {
            $this->assertStringContainsString($regla, $politica);
        }
        $this->assertStringNotContainsString('unsafe-inline', $politica);
        $this->assertStringNotContainsString('unsafe-eval', $politica);
        $this->assertStringNotContainsString('*', $politica);
        $this->assertStringNotContainsString('http', $politica);
    }

    public function test_las_paginas_llevan_la_csp(): void
    {
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 100, 'peso_actual_g' => 50, 'biomasa_kg' => 5]);

        // Sin sesión: el ingreso y Registro rápido (la pantalla que guarda el service worker)
        foreach ([route('login'), route('campo')] as $url) {
            $this->get($url)->assertOk()->assertHeader('Content-Security-Policy', $this->csp());
        }

        // Con sesión: el inicio, la cosecha (tabla de pesadas sin Alpine), el asistente, la conversión con sus barras y el QR de dos pasos
        foreach (['inicio', 'admin.cosechas.create', 'admin.conversion.index', 'mi-cuenta.dos-pasos.activar'] as $ruta) {
            $respuesta = $this->actingAs($this->administrador)->get(route($ruta));
            $respuesta->assertOk()->assertHeader('Content-Security-Policy', $this->csp());
            // Nada en línea que la CSP bloquee
            $html = $respuesta->getContent();
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\b(?:src=|type="application\/json"))[^>]*>/i', $html, "{$ruta}: script en línea");
            $this->assertDoesNotMatchRegularExpression('/\sstyle="/i', $html, "{$ruta}: style en línea");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+="/i', $html, "{$ruta}: manejador en línea");
            $this->assertStringNotContainsString('x-data', $html);
        }

        // Los textos del JS van en JSON que no se ejecuta
        $this->actingAs($this->administrador)->get(route('inicio'))->assertSee('<script type="application/json" id="textos-js">', false);
    }

    public function test_el_panel_de_plataforma_tambien(): void
    {
        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
        $this->get(route('plataforma.login'))->assertOk()->assertHeader('Content-Security-Policy', $this->csp());
    }

    public function test_las_descargas_no_la_llevan(): void
    {
        $this->actingAs($this->administrador)->get(route('admin.reportes.mensual.pdf'))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_ninguna_vista_tiene_scripts_estilos_ni_manejadores_en_linea(): void
    {
        $problemas = [];
        foreach (File::allFiles(resource_path('views')) as $archivo) {
            // Las vistas del PDF las dibuja dompdf en el servidor: no llegan al navegador
            if (str_contains($archivo->getRelativePath(), 'pdf')) {
                continue;
            }
            $codigo = $archivo->getContents();
            if (preg_match('/<script(?![^>]*\b(?:src=|type="application\/json"))[^>]*>/i', $codigo)) {
                $problemas[] = $archivo->getRelativePathname().': <script> en línea';
            }
            if (preg_match('/\sstyle="/i', $codigo)) {
                $problemas[] = $archivo->getRelativePathname().': style="..."';
            }
            if (preg_match('/\son(click|submit|change|input|load)="/i', $codigo)) {
                $problemas[] = $archivo->getRelativePathname().': on...="..."';
            }
            if (preg_match('/\sx-(data|on|model|show|text|bind)\b/', $codigo)) {
                $problemas[] = $archivo->getRelativePathname().': Alpine';
            }
        }

        $this->assertSame([], $problemas);
    }
}
