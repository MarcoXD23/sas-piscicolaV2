<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Lado del teléfono de los registros sin conexión (resources/js/sin-conexion.js): qué formularios se pueden guardar,
 * los datos que el layout le pasa a la cola y el envío tal como lo hace el teléfono. La lógica de la cola se prueba
 * con node --test (tests/js/sin-conexion.test.mjs); el resto, a mano en el teléfono (README, «Sin conexión»).
 */
class SinConexionTelefonoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $trabajador;

    private User $celador;

    private User $propietario;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 09:00', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->celador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::CELADOR]);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $this->ponerEnTurno($this->celador, Rol::CELADOR);
    }

    /** Acciones de los formularios que se pueden guardar en el teléfono. */
    private function formulariosGuardables(string $html): array
    {
        preg_match_all('/<form action="([^"]+)"[^>]*data-guardar-sin-conexion="[^"]+"/', $html, $coincidencias);

        return array_map(fn ($url) => parse_url(html_entity_decode($url), PHP_URL_PATH), $coincidencias[1]);
    }

    public function test_solo_las_rutas_con_sin_conexion_aceptan_registros_del_telefono(): void
    {
        $rutas = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => in_array('sin_conexion', $ruta->gatherMiddleware(), true))
            ->map(fn ($ruta) => $ruta->getName())->sort()->values()->all();

        $this->assertSame(['admin.avisos-pesca.store', 'admin.mortalidades.store', 'admin.raciones.store', 'noche.mediciones.store', 'noche.rondas.store'], $rutas);
    }

    public function test_el_trabajador_puede_guardar_racion_mortalidad_y_aviso_y_nada_mas(): void
    {
        $html = $this->actingAs($this->trabajador)->get(route('admin.lagos.index'))->assertOk()->getContent();

        $siembra = Siembra::first();
        $this->assertEqualsCanonicalizing([
            parse_url(route('admin.avisos-pesca.store', $this->lago), PHP_URL_PATH),
            parse_url(route('admin.mortalidades.store', $siembra->id), PHP_URL_PATH),
            parse_url(route('admin.raciones.store', $this->lago->id), PHP_URL_PATH),
        ], $this->formulariosGuardables($html));
        // El resumen que se ve en la lista del teléfono
        $this->assertStringContainsString('data-guardar-sin-conexion="'.e(__('layout.cola.resumen_racion', ['lago' => 'L01'])).'"', $html);
    }

    public function test_el_celador_puede_guardar_ronda_y_medicion_pero_no_el_aireador(): void
    {
        $html = $this->actingAs($this->celador)->get(route('noche.index'))->assertOk()->getContent();

        $this->assertEqualsCanonicalizing([
            parse_url(route('noche.mediciones.store', $this->lago), PHP_URL_PATH),
            parse_url(route('noche.rondas.store'), PHP_URL_PATH),
        ], $this->formulariosGuardables($html));
        $this->assertStringContainsString(parse_url(route('noche.aireador.encender', $this->lago), PHP_URL_PATH), $html);
    }

    public function test_el_propietario_solo_consulta_y_no_tiene_formularios_guardables(): void
    {
        foreach (['admin.lagos.index', 'noche.index'] as $ruta) {
            $html = $this->actingAs($this->propietario)->get(route($ruta))->assertOk()->getContent();
            $this->assertSame([], $this->formulariosGuardables($html), $ruta);
        }
    }

    public function test_el_layout_separa_la_cola_por_finca_y_persona_y_trae_sus_ventanas(): void
    {
        $this->actingAs($this->celador)->get(route('noche.index'))
            ->assertSee('data-cola-clave="'.$this->finca->id.'|'.$this->celador->id.'"', false)
            ->assertSee('data-cola-horas="'.config('sin_conexion.horas').'"', false)
            ->assertSee('id="cola-sin-conexion"', false)
            ->assertSee('id="salir-con-pendientes"', false)
            ->assertSee('data-cola-boton hidden', false)
            ->assertSee('"cola_guardado":', false);

        // Sin sesión de finca no hay cola
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertDontSee('data-cola-clave', false)->assertDontSee('id="cola-sin-conexion"', false);
    }

    public function test_el_envio_como_lo_hace_el_telefono_se_guarda_y_responde_json(): void
    {
        $uuid = (string) Str::uuid();
        // new Date().toISOString() del teléfono: UTC con milisegundos
        $hora = now()->subMinutes(40)->utc()->format('Y-m-d\TH:i:s.v\Z');

        $this->actingAs($this->celador)->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            Middleware::CABECERA_ID => $uuid,
            Middleware::CABECERA_MOMENTO => $hora,
            Middleware::CABECERA_HORA_TELEFONO => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ])->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD, 'lago_id' => '', 'descripcion' => ''])
            ->assertCreated()->assertJson(['estado' => 'guardado']);

        $this->assertSame(now()->subMinutes(40)->utc()->format('Y-m-d H:i'), Ronda::first()->registrada_at->utc()->format('Y-m-d H:i'));
    }

    public function test_con_la_sesion_vencida_el_telefono_recibe_401_y_no_una_redireccion(): void
    {
        $this->withHeaders([
            'Accept' => 'application/json',
            Middleware::CABECERA_ID => (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => now()->toIso8601String(),
            Middleware::CABECERA_HORA_TELEFONO => now()->toIso8601String(),
        ])->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])->assertUnauthorized();
    }

    public function test_el_javascript_guarda_solo_los_formularios_marcados_y_avisa_al_salir(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));
        $cola = file_get_contents(resource_path('js/sin-conexion.js'));

        $this->assertStringContainsString("formulario.hasAttribute('data-guardar-sin-conexion') && await cola.guardar(formulario)", $app);
        $this->assertStringContainsString("getElementById('salir-con-pendientes')", $app);
        $this->assertStringContainsString("'X-Registro-Sin-Conexion': registro.uuid", $cola);
        $this->assertStringContainsString("'X-Registrado-En': registro.registradoEn", $cola);
        $this->assertStringContainsString("'X-Hora-Telefono': new Date().toISOString()", $cola);
        $this->assertStringContainsString("index('clave').getAll(clave)", $cola);
        $this->assertStringContainsString("const VERSION = 'v11'", file_get_contents(public_path('sw.js')));
    }

    public function test_la_cola_envia_con_un_token_fresco_y_no_con_el_de_la_pagina(): void
    {
        $cola = file_get_contents(resource_path('js/sin-conexion.js'));

        // El token se pide justo antes de enviar; el de una página guardada puede estar vencido
        $this->assertStringContainsString("fetch('/campo/token'", $cola);
        $this->assertStringContainsString("'X-CSRF-TOKEN': sesion.token", $cola);
        $this->assertStringNotContainsString('csrf-token', $cola);
        // En /campo (sin sesión en la página) la clave sale de la sesión activa guardada en el teléfono
        $this->assertStringContainsString('leerSesionActiva()', $cola);
        $this->assertStringContainsString('guardarRegistro', $cola);
    }

    public function test_el_paquete_se_descarga_solo_y_se_borra_al_salir(): void
    {
        $this->actingAs($this->trabajador)->get(route('admin.lagos.index'))
            ->assertSee('data-paquete-minutos="'.config('sin_conexion.minutos_paquete').'"', false);

        $paquete = file_get_contents(resource_path('js/paquete.js'));
        $this->assertStringContainsString("fetch('/campo/paquete'", $paquete);
        $this->assertStringContainsString("cache: 'no-store'", $paquete);
        $this->assertStringContainsString('paqueteValido(paquete, clave)', $paquete);
        $this->assertStringContainsString("window.addEventListener('online'", $paquete);
        $this->assertStringContainsString('clavesAjenas(claves, clave)', $paquete);

        // Al cerrar sesión: pantallas guardadas, paquete y sesión activa
        $app = file_get_contents(resource_path('js/app.js'));
        $this->assertMatchesRegularExpression("/endsWith\\('\\/logout'\\)\\) \\{\\s*navigator\\.serviceWorker\\?\\.controller\\?\\.postMessage\\(\\{ tipo: 'limpiar' \\}\\);\\s*await paquete\\.borrar\\(\\);/", $app);
    }
}
