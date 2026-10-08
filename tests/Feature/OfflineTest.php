<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lo comprobable sin navegador de la consulta sin conexión. El comportamiento en el teléfono
 * (copias guardadas, franja y formularios sin señal) se revisa a mano: ver README, «Sin conexión».
 */
class OfflineTest extends TestCase
{
    use RefreshDatabase;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-15 08:30', 'America/Bogota'));
        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($finca->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([Rol::PROPIETARIO]);
    }

    private function serviceWorker(): string
    {
        return file_get_contents(public_path('sw.js'));
    }

    public function test_el_service_worker_guarda_paginas_para_consulta_y_nunca_las_privadas(): void
    {
        $sw = $this->serviceWorker();

        $this->assertStringContainsString("peticion.mode === 'navigate'", $sw);
        $this->assertStringContainsString('MAXIMO_PAGINAS = 40', $sw);
        foreach (['/login', '/registro', '/olvide-contrasena', '/restablecer-contrasena', '/plataforma', '/asistente', '/logout', '/api/'] as $privada) {
            $this->assertStringContainsString("'{$privada}'", $sw, "{$privada} debe estar en la lista de páginas que nunca se guardan");
        }
        $this->assertStringContainsString("['/excel', '/pdf']", $sw);
        // Solo lectura: las peticiones que no son GET nunca se responden desde la caché
        $this->assertStringContainsString("if (peticion.method !== 'GET') return;", $sw);
        // No se guarda una redirección (por ejemplo, al ingreso con la sesión vencida)
        $this->assertStringContainsString('!respuesta.redirected', $sw);
        // Al cerrar sesión la página puede pedir borrar las copias
        $this->assertStringContainsString("evento.data?.tipo === 'limpiar'", $sw);
    }

    public function test_los_archivos_de_la_pwa_existen(): void
    {
        foreach (['sw.js', 'offline.html', 'manifest.webmanifest', 'favicon.ico', 'icons/icon-192.png'] as $archivo) {
            $this->assertFileExists(public_path($archivo));
        }
        $this->assertStringContainsString("'/offline.html'", $this->serviceWorker());
    }

    public function test_cada_pagina_lleva_la_franja_sin_conexion_con_su_hora_en_la_zona_de_la_finca(): void
    {
        $this->actingAs($this->propietario)->get(route('inicio'))->assertOk()
            ->assertSee('data-sin-conexion hidden', false)
            ->assertSee(__('layout.sin_conexion', ['momento' => '15/10 08:30']));
    }

    public function test_el_aviso_para_formularios_sin_senal_viene_de_lang(): void
    {
        // Los textos del JavaScript se publican en window.textos (lang/es/js.php)
        $this->actingAs($this->propietario)->get(route('inicio'))->assertOk()->assertSee('"sin_conexion_guardar":', false);

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("t('sin_conexion_guardar')", $js);
        $this->assertStringContainsString("fetch('/up'", $js);
    }

    public function test_cerrar_sesion_borra_las_copias_guardadas_en_el_navegador(): void
    {
        $this->actingAs($this->propietario)->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertHeader('Clear-Site-Data', '"cache", "storage"');
    }

    public function test_el_chequeo_de_conexion_responde_sin_sesion(): void
    {
        $this->get('/up')->assertOk();
    }
    public function test_el_service_worker_guarda_registro_rapido_y_nunca_el_paquete_ni_el_token(): void
    {
        $sw = $this->serviceWorker();

        // /campo se guarda al instalarse (con sus estilos y scripts) y se sirve sin señal
        $this->assertStringContainsString("const CAMPO = '/campo';", $sw);
        $this->assertStringContainsString("const PRECARGA = ['/offline.html', CAMPO,", $sw);
        $this->assertStringContainsString('url.pathname === CAMPO ? campo(evento) : pagina(evento, url)', $sw);
        $this->assertStringContainsString('html.match(/\/build\/assets\/[^', $sw);
        $this->assertStringContainsString("evento.data?.tipo === 'actualizar-campo'", $sw);
        // Paquete y token: nunca en caché
        $this->assertStringContainsString("'/salud', '/campo/'];", $sw);
    }
}
