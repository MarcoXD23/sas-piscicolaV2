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
use App\Support\Campo\PaqueteTrabajo;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Modo de campo (etapa 1, servidor): el cascarón público de «Registro rápido», el paquete de trabajo del teléfono
 * (solo la finca activa y las secciones del rol) y el token vigente para enviar lo registrado sin conexión.
 */
class PaqueteTrabajoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private Lago $conSiembra;

    private Lago $vacio;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->conSiembra = Lago::create(['nombre' => 'A-Lago con peces', 'tipo' => 'tierra']);
        $this->vacio = Lago::create(['nombre' => 'B-Lago vacío', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->conSiembra->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => now()->subDays(20),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 950, 'peso_actual_g' => 40, 'biomasa_kg' => 38]);
        StockAlimento::create(['tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => 120.5]);
        StockAlimento::create(['tipo_concentrado' => 'Agotado', 'cantidad_kg' => 0]);
    }

    private function persona(string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->finca->id, 'valor_hora' => 9999]))->asignarRoles($roles);
    }

    private function paquete(User $usuario): TestResponse
    {
        return $this->actingAs($usuario)->getJson(route('campo.paquete'))->assertOk();
    }

    public function test_el_trabajador_recibe_racion_mortalidad_y_aviso_solo_de_lagos_con_siembra(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        AvisoPesca::create(['lago_id' => $this->conSiembra->id, 'siembra_id' => $this->siembra->id, 'user_id' => $trabajador->id,
            'estado' => AvisoPesca::PENDIENTE, 'reportado_at' => now()]);

        $paquete = $this->paquete($trabajador)->json();

        $this->assertSame([PaqueteTrabajo::RACION, PaqueteTrabajo::MORTALIDAD, PaqueteTrabajo::AVISO_PESCA], $paquete['secciones']);
        $this->assertSame($this->finca->id.'|'.$trabajador->id, $paquete['clave']);
        $this->assertSame(['A-Lago con peces'], array_column($paquete['lagos'], 'nombre'));   // el lago vacío no sirve para registrar

        $lago = $paquete['lagos'][0];
        $this->assertTrue($lago['aviso_pendiente']);
        $this->assertSame(route('admin.raciones.store', $this->conSiembra->id, absolute: false), $lago['url_racion']);
        $this->assertSame(route('admin.avisos-pesca.store', $this->conSiembra, absolute: false), $lago['url_aviso_pesca']);
        $this->assertArrayNotHasKey('url_medicion', $lago);
        $this->assertSame([[
            'id' => $this->siembra->id, 'especie' => 'Cachama blanca', 'fecha' => $this->siembra->fecha->format('Y-m-d'), 'peces_vivos' => 950,
            'url_mortalidad' => route('admin.mortalidades.store', $this->siembra->id, absolute: false),
        ]], $lago['siembras']);

        // Solo concentrados con existencias; nada de ronda ni medición
        $this->assertSame([['tipo' => 'Engorde 32%', 'kg' => 120.5]], $paquete['concentrados']);
        $this->assertSame([], $paquete['tipos_ronda']);
        $this->assertSame([], $paquete['medicion']);
    }

    public function test_el_administrador_registra_racion_mortalidad_y_muestreo_pero_no_el_aviso(): void
    {
        $paquete = $this->paquete($this->persona(Rol::ADMINISTRADOR))->json();

        // Con el ajuste por defecto («ambos», F4) el administrador también mide el agua, por su propia ruta y sin turno
        $this->assertSame([PaqueteTrabajo::RACION, PaqueteTrabajo::MORTALIDAD, PaqueteTrabajo::MUESTREO, PaqueteTrabajo::MEDICION], $paquete['secciones']);
        $this->assertSame(route('admin.mediciones-agua.store', $paquete['lagos'][0]['id'], absolute: false), $paquete['lagos'][0]['url_medicion']);
        $this->assertArrayNotHasKey('aviso_pendiente', $paquete['lagos'][0]);
        $this->assertArrayNotHasKey('url_aviso_pesca', $paquete['lagos'][0]);
        $this->assertSame([], $paquete['turnos']);   // el administrador no trabaja por turnos
    }

    public function test_el_celador_recibe_ronda_y_medicion_de_todos_los_lagos_con_sus_turnos(): void
    {
        $celador = $this->persona(Rol::CELADOR);
        $turno = $this->ponerEnTurno($celador, Rol::CELADOR);

        $paquete = $this->paquete($celador)->json();

        $this->assertSame([PaqueteTrabajo::RONDA, PaqueteTrabajo::MEDICION], $paquete['secciones']);
        $this->assertSame(['A-Lago con peces', 'B-Lago vacío'], array_column($paquete['lagos'], 'nombre'));
        $this->assertSame(route('noche.mediciones.store', $this->vacio, absolute: false), $paquete['lagos'][1]['url_medicion']);
        $this->assertArrayNotHasKey('url_racion', $paquete['lagos'][0]);
        $this->assertArrayNotHasKey('url_mortalidad', $paquete['lagos'][0]['siembras'][0]);
        $this->assertSame(route('noche.rondas.store', absolute: false), $paquete['urls']['ronda']);
        $this->assertSame(Ronda::TIPOS, array_column($paquete['tipos_ronda'], 'codigo'));
        $this->assertSame(array_keys(PaqueteTrabajo::RANGOS_MEDICION), array_column($paquete['medicion'], 'campo'));
        $this->assertSame([], $paquete['concentrados']);
        $this->assertSame([['rol' => Rol::CELADOR, 'inicia_at' => $turno->inicia_at->utc()->toIso8601String(), 'termina_at' => $turno->termina_at->utc()->toIso8601String()]], $paquete['turnos']);
    }

    public function test_el_propietario_no_tiene_registros_de_campo(): void
    {
        $paquete = $this->paquete($this->persona(Rol::PROPIETARIO))->json();

        $this->assertSame([], $paquete['secciones']);
        $this->assertSame([], $paquete['lagos']);
        $this->assertSame([], $paquete['concentrados']);
        $this->assertSame([], $paquete['urls']);
    }

    public function test_el_paquete_trae_solo_la_finca_activa_y_nada_sensible(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $this->persona(Rol::PROPIETARIO);   // otra persona de la misma finca

        $otra = Tenant::create(['nombre' => 'Finca Ajena', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $lagoAjeno = Lago::create(['nombre' => 'Lago Ajeno', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lagoAjeno->id, 'especie_id' => $this->especie('bocachico'), 'fecha' => now()->subDays(5),
            'cantidad_alevinos' => 10, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 5, 'biomasa_kg' => 1]);
        StockAlimento::create(['tipo_concentrado' => 'Concentrado Ajeno', 'cantidad_kg' => 50]);
        app(TenantActual::class)->establecer($this->finca->id);

        $respuesta = $this->paquete($trabajador)
            ->assertHeader('Cache-Control', 'no-store, private');
        $json = $respuesta->getContent();

        foreach (['Finca Ajena', 'Lago Ajeno', 'Concentrado Ajeno', 'valor_hora', '9999', 'email', '@', 'password', 'precio', 'venta'] as $dato) {
            $this->assertStringNotContainsString($dato, $json, $dato);
        }
        $this->assertSame('Finca Norte', $respuesta->json('finca.nombre'));
        $this->assertSame($trabajador->name, $respuesta->json('usuario.nombre'));
    }

    public function test_el_paquete_respeta_el_tope_de_lagos(): void
    {
        config(['sin_conexion.max_lagos' => 2]);
        Lago::create(['nombre' => 'C-Tercer lago', 'tipo' => 'tierra']);

        $paquete = $this->paquete($this->persona(Rol::CELADOR))->json();

        $this->assertSame(['A-Lago con peces', 'B-Lago vacío'], array_column($paquete['lagos'], 'nombre'));
        $this->assertTrue($paquete['lagos_truncados']);
        $this->assertSame(2, $paquete['max_lagos']);
    }

    public function test_sin_sesion_no_hay_paquete_ni_token(): void
    {
        $this->getJson(route('campo.paquete'))->assertUnauthorized();
        $this->getJson(route('campo.token'))->assertUnauthorized();
    }

    public function test_el_token_es_el_de_la_sesion_y_no_se_guarda_en_cache(): void
    {
        $respuesta = $this->actingAs($this->persona(Rol::TRABAJADOR))->getJson(route('campo.token'))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertSame(session()->token(), $respuesta->json('token'));
        $this->assertSame(40, strlen($respuesta->json('token')));
    }

    public function test_el_cascaron_es_publico_y_no_lleva_datos_ni_sesion(): void
    {
        // Ni siquiera con sesión: la página es la misma para todos (el service worker la guarda tal cual)
        $this->actingAs($this->persona(Rol::TRABAJADOR));
        $respuesta = $this->get(route('campo'))->assertOk()->assertHeader('Cache-Control', 'no-cache, private');
        $html = $respuesta->getContent();

        $this->assertStringContainsString('data-campo', $html);
        $this->assertStringContainsString(__('campo.titulo'), $html);
        foreach (['Finca Norte', 'A-Lago con peces', 'csrf-token', 'name="_token"', 'data-cola-clave'] as $dato) {
            $this->assertStringNotContainsString($dato, $html, $dato);
        }
        // Sin sesión: no abre ni renueva la cookie de sesión ni la del token
        $this->assertNull($respuesta->headers->getCookies()[0] ?? null);
    }

    public function test_los_rangos_de_medicion_del_paquete_son_los_del_servidor(): void
    {
        $celador = $this->persona(Rol::CELADOR);
        $this->ponerEnTurno($celador, Rol::CELADOR);
        $url = route('noche.mediciones.store', $this->conSiembra);

        foreach (PaqueteTrabajo::RANGOS_MEDICION as $campo => [$minimo, $maximo, $paso]) {
            $this->actingAs($celador)->post($url, [$campo => $maximo])->assertSessionHasNoErrors();
            $this->actingAs($celador)->post($url, [$campo => $maximo + $paso])->assertSessionHasErrors($campo);
            $this->actingAs($celador)->post($url, [$campo => $minimo - $paso])->assertSessionHasErrors($campo);
        }
        $this->assertSame(count(PaqueteTrabajo::RANGOS_MEDICION), MedicionAgua::count());
    }

    public function test_un_registro_enviado_como_la_pantalla_rapida_se_guarda_una_sola_vez(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);
        $paquete = $this->paquete($trabajador)->json();
        $token = $this->actingAs($trabajador)->getJson(route('campo.token'))->json('token');
        $uuid = (string) Str::uuid();

        $enviar = fn () => $this->actingAs($trabajador)->withHeaders([
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $token,
            Middleware::CABECERA_ID => $uuid,
            Middleware::CABECERA_MOMENTO => now()->subMinutes(30)->utc()->toIso8601String(),
            Middleware::CABECERA_HORA_TELEFONO => now()->utc()->toIso8601String(),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => now()->subMinutes(31)->utc()->toIso8601String(),
        ])->post($paquete['lagos'][0]['siembras'][0]['url_mortalidad'], ['fecha' => now()->toDateString(), 'cantidad' => 5]);

        $enviar()->assertCreated()->assertJson(['estado' => 'guardado']);
        $this->flushHeaders();
        $enviar()->assertOk()->assertJson(['estado' => 'duplicado']);
        $this->assertSame(945, $this->siembra->fresh()->peces_vivos);
    }
}
