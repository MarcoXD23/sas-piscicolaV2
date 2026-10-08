<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\AvisoPesca;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Modo de campo (etapa 3): la pantalla Registro rápido y sus accesos. La pantalla arma cada registro con el paquete
 * (resources/js/campo-logica.js, probado con node --test); aquí se comprueba que lo que arma, enviado como lo envía
 * la cola, se guarda en el servidor, y que la pantalla se encuentra (menú, franja sin conexión y offline.html).
 */
class RegistroRapidoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => now()->subDays(20),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 950, 'peso_actual_g' => 40, 'biomasa_kg' => 38]);
        StockAlimento::create(['tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => 100]);
    }

    private function persona(string $rol, bool $enTurno = true): User
    {
        $usuario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([$rol]);
        if ($enTurno && in_array($rol, [Rol::TRABAJADOR, Rol::CELADOR], true)) {
            $this->ponerEnTurno($usuario, $rol);
        }

        return $usuario;
    }

    /** Envío como lo hace la cola desde Registro rápido: token fresco, uuid, hora del registro y del teléfono. */
    private function enviarComoLaCola(User $usuario, string $url, array $campos)
    {
        $this->flushHeaders();
        $token = $this->actingAs($usuario)->getJson(route('campo.token'))->json('token');

        return $this->actingAs($usuario)->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN' => $token,
            Middleware::CABECERA_ID => (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:s.v\Z'),
            Middleware::CABECERA_HORA_TELEFONO => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
        ])->post($url, $campos);
    }

    public function test_cada_seccion_armada_con_el_paquete_se_guarda_en_el_servidor(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $paquete = $this->actingAs($trabajador)->getJson(route('campo.paquete'))->json();
        $lago = $paquete['lagos'][0];

        // Los mismos campos que arma armarRegistro() (decimales con punto, solo lo escrito)
        $this->enviarComoLaCola($trabajador, $lago['url_racion'], ['fecha' => now()->toDateString(), 'tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => '4.5'])->assertCreated();
        $this->enviarComoLaCola($trabajador, $lago['siembras'][0]['url_mortalidad'], ['fecha' => now()->toDateString(), 'cantidad' => '5'])->assertCreated();
        $this->enviarComoLaCola($trabajador, $lago['url_aviso_pesca'], ['siembra_id' => (string) $this->siembra->id, 'nota' => ''])->assertCreated();

        $celador = $this->persona(Rol::CELADOR);
        $paqueteNoche = $this->actingAs($celador)->getJson(route('campo.paquete'))->json();
        $this->enviarComoLaCola($celador, $paqueteNoche['urls']['ronda'], ['tipo' => Ronda::SIN_NOVEDAD, 'lago_id' => '', 'descripcion' => ''])->assertCreated();
        $this->enviarComoLaCola($celador, $paqueteNoche['lagos'][0]['url_medicion'], ['temperatura_c' => '26.5'])->assertCreated();

        $this->assertEquals(95.5, StockAlimento::sole()->cantidad_kg);
        $this->assertSame(945, $this->siembra->fresh()->peces_vivos);
        $this->assertSame(1, AvisoPesca::count());
        $this->assertSame(1, Ronda::count());
        $this->assertEquals(26.5, MedicionAgua::sole()->temperatura_c);
    }

    public function test_el_paquete_dice_que_secciones_exigen_turno(): void
    {
        $this->assertSame(['racion' => true, 'mortalidad' => true, 'aviso_pesca' => true],
            $this->actingAs($this->persona(Rol::TRABAJADOR))->getJson(route('campo.paquete'))->json('exige_turno'));
        // El administrador registra ración y mortalidad sin turno
        $this->assertSame(['racion' => false, 'mortalidad' => false],
            $this->actingAs($this->persona(Rol::ADMINISTRADOR))->getJson(route('campo.paquete'))->json('exige_turno'));
        $this->assertSame(['ronda' => true, 'medicion' => true],
            $this->actingAs($this->persona(Rol::CELADOR))->getJson(route('campo.paquete'))->json('exige_turno'));
    }

    public function test_el_menu_lleva_a_registro_rapido_a_los_roles_de_campo(): void
    {
        $enlaces = fn (string $html) => substr_count($html, 'href="'.route('campo').'"');

        // Menú (escritorio y celular) más el enlace de la franja sin conexión
        foreach ([Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $html = $this->actingAs($this->persona($rol))->get(route('inicio'))->assertOk()->getContent();
            $this->assertGreaterThanOrEqual(3, $enlaces($html), $rol);
        }

        // El propietario solo consulta: no está en su menú (solo queda el enlace de la franja, que no se ve con señal)
        $html = $this->actingAs($this->persona(Rol::PROPIETARIO))->get(route('inicio'))->assertOk()->getContent();
        $this->assertSame(1, $enlaces($html));
    }

    public function test_la_franja_sin_conexion_y_offline_html_llevan_a_registro_rapido(): void
    {
        $this->actingAs($this->persona(Rol::TRABAJADOR))->get(route('inicio'))
            ->assertSee('data-sin-conexion hidden', false)
            ->assertSee(__('layout.abrir_registro_rapido'));

        $offline = file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('href="/campo"', $offline);
        $this->assertStringContainsString('Abrir Registro rápido', $offline);
    }

    public function test_la_pantalla_trae_su_estructura_y_la_lista_de_la_cola_sin_datos(): void
    {
        $html = $this->get(route('campo'))->assertOk()->getContent();

        foreach (['data-campo-sin-paquete', 'data-campo-listo', 'data-campo-secciones', 'data-campo-formulario', 'data-campo-actualizar',
            'id="cola-sin-conexion"', 'data-cola-pendientes', 'data-cola-rechazados', 'data-cola-enviar', 'data-cola-boton'] as $marca) {
            $this->assertStringContainsString($marca, $html, $marca);
        }
        $this->assertStringContainsString('data-horas-viejo="'.config('sin_conexion.horas_paquete_viejo').'"', $html);
        $this->assertStringContainsString('"campo_quien":', $html);   // textos de la pantalla en window.textos
        // Sin token, sin finca y sin el contador del layout de la app
        $this->assertStringNotContainsString('csrf-token', $html);
        $this->assertStringNotContainsString('data-cola-clave', $html);
    }
}
