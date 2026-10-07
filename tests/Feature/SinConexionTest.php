<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\RegistroSinConexion;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\Turno;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Registros hechos sin conexión y enviados al volver la señal (middleware RegistroSinConexion).
 * Escenario: el celador tuvo turno de 22:00 a 06:00 y hizo la ronda a las 03:00 sin señal; la envía a las 07:30.
 */
class SinConexionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $celador;

    private User $trabajador;

    private Lago $lago;

    private Siembra $siembra;

    private Turno $turnoNoche;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 07:30', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->celador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::CELADOR]);
        $this->trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);

        $this->turnoNoche = $this->turno($this->celador, Rol::CELADOR, '2026-10-07 22:00', '2026-10-08 06:00');
        $this->turno($this->trabajador, Rol::TRABAJADOR, '2026-10-07 06:00', '2026-10-07 14:00');
    }

    /** El turno queda en la finca activa (tenant_id no se asigna a mano). */
    private function turno(User $usuario, string $rol, string $inicio, string $fin): Turno
    {
        return Turno::create(['user_id' => $usuario->id, 'rol' => $rol,
            'inicia_at' => Carbon::parse($inicio, 'America/Bogota'), 'termina_at' => Carbon::parse($fin, 'America/Bogota')]);
    }

    /** Envío como lo hace el teléfono al volver la señal: hora local de Bogotá con su desfase, en ISO 8601. */
    private function enviar(User $usuario, string $url, array $datos, string $horaLocal, ?string $id = null): TestResponse
    {
        // Cada envío lleva solo sus cabeceras (withHeaders las acumula entre peticiones)
        $this->flushHeaders();

        return $this->actingAs($usuario)->withHeaders([
            Middleware::CABECERA_ID => $id ?? (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => Carbon::parse($horaLocal, 'America/Bogota')->toIso8601String(),
        ])->post($url, $datos);
    }

    public function test_la_ronda_hecha_en_turno_sin_senal_se_acepta_aunque_llegue_con_el_turno_cerrado(): void
    {
        $respuesta = $this->enviar($this->celador, route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD], '2026-10-08 03:00');

        $respuesta->assertCreated()->assertJson(['estado' => 'guardado', 'mensaje' => __('mensajes.ronda.ronda_sin_novedad_registrada')]);
        $ronda = Ronda::sole();
        $this->assertSame('2026-10-08 03:00', $ronda->registrada_at->format('Y-m-d H:i'));   // hora real, no la de llegada
        $this->assertSame($this->turnoNoche->id, $ronda->turno_id);

        // La bitácora guarda la hora real y avisa que llegó después
        $bitacora = Bitacora::sole();
        $this->assertSame('2026-10-08 03:00', $bitacora->momentoLocal()->format('Y-m-d H:i'));
        $this->assertStringContainsString('registrado sin conexión, recibido el 08/10/2026 07:30', $bitacora->textoDetalle());

        // El mensaje de éxito no queda pendiente para la próxima pantalla
        $this->assertNull(session('estado'));
    }

    public function test_el_mismo_registro_enviado_dos_veces_se_guarda_una_sola_vez(): void
    {
        $id = (string) Str::uuid();

        $this->enviar($this->celador, route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD], '2026-10-08 03:00', $id)->assertCreated();
        $this->enviar($this->celador, route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD], '2026-10-08 03:00', $id)
            ->assertOk()->assertJson(['estado' => 'duplicado']);

        $this->assertSame(1, Ronda::count());
        $this->assertSame(1, Bitacora::count());
        $this->assertSame(1, RegistroSinConexion::count());
    }

    public function test_si_a_esa_hora_no_tenia_turno_se_rechaza(): void
    {
        // A las 07:00 el turno de noche ya había terminado
        $this->enviar($this->celador, route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD], '2026-10-08 07:00')
            ->assertForbidden()->assertJson(['message' => __('mensajes.verificar_turno.estas_fuera_tu_turno_solo')]);

        $this->assertSame(0, Ronda::count());
        $this->assertSame(0, RegistroSinConexion::count());
    }

    public function test_rechaza_hora_futura_hora_demasiado_vieja_y_datos_incompletos(): void
    {
        $url = route('noche.rondas.store');
        $datos = ['tipo' => Ronda::SIN_NOVEDAD];

        $this->enviar($this->celador, $url, $datos, '2026-10-08 08:00')->assertUnprocessable()
            ->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.hora_futura'));
        $this->enviar($this->celador, $url, $datos, '2026-10-06 03:00')->assertUnprocessable()   // más de 48 horas
            ->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.muy_viejo', ['horas' => 48]));
        $this->enviar($this->celador, $url, $datos, '2026-10-08 03:00', 'no-es-un-uuid')->assertUnprocessable();
        $this->flushHeaders();
        $this->actingAs($this->celador)->withHeaders([Middleware::CABECERA_ID => (string) Str::uuid()])->post($url, $datos)
            ->assertUnprocessable();   // sin la hora del registro

        $this->assertSame(0, Ronda::count());
    }

    public function test_las_reglas_de_siempre_se_aplican_al_llegar_y_el_rechazo_trae_su_motivo(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => 10]);
        $url = route('admin.raciones.store', $this->lago);

        // Sin stock suficiente cuando llega: se rechaza con el motivo y no se marca como recibido
        $this->enviar($this->trabajador, $url, ['fecha' => '2026-10-07', 'tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => 25], '2026-10-07 09:00')
            ->assertUnprocessable()->assertJsonValidationErrors(['cantidad_kg']);
        $this->assertSame(0, RegistroSinConexion::count());
        $this->assertEquals(10, StockAlimento::sole()->cantidad_kg);

        // Con stock: se descuenta y queda en la bitácora a la hora real
        $this->enviar($this->trabajador, $url, ['fecha' => '2026-10-07', 'tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => 4], '2026-10-07 09:00')
            ->assertCreated();
        $this->assertEquals(6, StockAlimento::sole()->cantidad_kg);
        $this->assertSame('2026-10-07 09:00', Bitacora::sole()->momentoLocal()->format('Y-m-d H:i'));
    }

    public function test_mortalidad_aviso_y_medicion_guardan_la_hora_real(): void
    {
        $this->enviar($this->trabajador, route('admin.mortalidades.store', $this->siembra), ['fecha' => '2026-10-07', 'cantidad' => 5], '2026-10-07 10:15')
            ->assertCreated();
        $this->assertSame(995, $this->siembra->fresh()->peces_vivos);

        $this->enviar($this->trabajador, route('admin.avisos-pesca.store', $this->lago), ['siembra_id' => $this->siembra->id], '2026-10-07 11:40')
            ->assertCreated();
        $this->assertSame('2026-10-07 11:40', AvisoPesca::sole()->reportado_at->format('Y-m-d H:i'));

        $this->enviar($this->celador, route('noche.mediciones.store', $this->lago), ['temperatura_c' => 26.5, 'oxigeno_mg_l' => 5.1], '2026-10-08 04:20')
            ->assertCreated();
        $this->assertSame('2026-10-08 04:20', MedicionAgua::sole()->medido_at->format('Y-m-d H:i'));
    }

    public function test_el_aireador_no_acepta_registros_sin_conexion(): void
    {
        // La ruta del aireador no tiene el middleware: el turno se revisa ahora (07:30, ya cerrado)
        $this->enviar($this->celador, route('noche.aireador.encender', $this->lago), [], '2026-10-08 03:00')->assertForbidden();
    }

    public function test_el_envio_normal_con_senal_no_cambia(): void
    {
        $this->travelTo(Carbon::parse('2026-10-08 03:00', 'America/Bogota'));

        $this->actingAs($this->celador)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])
            ->assertRedirect(route('noche.index'))->assertSessionHas('estado');

        $this->assertSame(0, RegistroSinConexion::count());
        $this->assertStringNotContainsString('sin conexión', Bitacora::sole()->textoDetalle());
    }

    public function test_cada_finca_tiene_sus_propios_ids_de_registro(): void
    {
        $id = (string) Str::uuid();
        $this->enviar($this->celador, route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD], '2026-10-08 03:00', $id)->assertCreated();

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($otra->id);
        $celadorOtra = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::CELADOR]);
        $this->turno($celadorOtra, Rol::CELADOR, '2026-10-07 22:00', '2026-10-08 06:00');

        // El mismo id en otra finca no se confunde con el de esta
        $this->enviar($celadorOtra, route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD], '2026-10-08 03:00', $id)->assertCreated();

        app(TenantActual::class)->establecer($this->finca->id);
        $this->assertSame(1, Ronda::count());   // cada finca solo ve la suya
        $this->assertSame(2, TenantActual::sinFiltro(fn () => RegistroSinConexion::count()));
    }
}
