<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\AvisoPesca;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
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
 * Los registros de la cola sin conexión validan sus fechas con el día real del registro (MomentoRegistro, ya corregido
 * con el desfase del reloj, en la zona de la finca) y no con el de llegada.
 * Escenario: se registra el 8 a las 23:50 y llega el 9 a las 00:10 (hora de Bogotá).
 */
class SinConexionFechaRealTest extends TestCase
{
    use RefreshDatabase;

    private const REAL = '2026-10-08 23:50';

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-09 00:10', 'America/Bogota'));
        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($finca->id);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
        StockAlimento::create(['tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => 500]);
    }

    /** Persona con un turno (hora de Bogotá). */
    private function persona(string $rol, string $inicio, string $fin): User
    {
        $usuario = tap(User::factory()->create(['tenant_id' => app(TenantActual::class)->id()]))->asignarRoles([$rol]);
        Turno::create(['user_id' => $usuario->id, 'rol' => $rol, 'inicia_at' => Carbon::parse($inicio, 'America/Bogota'), 'termina_at' => Carbon::parse($fin, 'America/Bogota')]);

        return $usuario;
    }

    /** Trabajador cuyo turno terminó a medianoche: a las 00:10 ya no está en turno. */
    private function trabajador(): User
    {
        return $this->persona(Rol::TRABAJADOR, '2026-10-08 18:00', '2026-10-09 00:00');
    }

    /** Celador cuyo turno terminó a medianoche. */
    private function celador(): User
    {
        return $this->persona(Rol::CELADOR, '2026-10-08 16:00', '2026-10-09 00:00');
    }

    /**
     * Envío como la cola. $horaReal: cuándo se hizo de verdad (Bogotá). $reloj: minutos que el reloj del teléfono iba
     * adelantado (+) o atrasado (−) al guardar y al enviar (así se ven X-Registrado-En y X-Hora-Telefono).
     */
    private function enviar(User $usuario, string $url, array $datos, string $horaReal = self::REAL, int $reloj = 0): TestResponse
    {
        $this->flushHeaders();
        $iso = fn (Carbon $hora) => $hora->utc()->format('Y-m-d\TH:i:s.v\Z');

        return $this->actingAs($usuario)->withHeaders([
            'Accept' => 'application/json',
            Middleware::CABECERA_ID => (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => $iso(Carbon::parse($horaReal, 'America/Bogota')->addMinutes($reloj)),
            Middleware::CABECERA_HORA_TELEFONO => $iso(now()->addMinutes($reloj)),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => $iso(Carbon::parse($horaReal, 'America/Bogota')->subMinute()),
        ])->post($url, $datos);
    }

    private function racion(string $fecha): array
    {
        return ['fecha' => $fecha, 'tipo_concentrado' => 'Engorde 32%', 'cantidad_kg' => '5'];
    }

    private function fechaPosterior(string $dia = '08/10/2026'): string
    {
        return __('mensajes.sin_conexion.fecha_posterior_al_registro', ['dia' => $dia]);
    }

    public function test_racion_de_las_23_50_vale_y_una_fecha_posterior_al_registro_no(): void
    {
        $trabajador = $this->trabajador();
        $url = route('admin.raciones.store', $this->lago->id);

        $this->enviar($trabajador, $url, $this->racion('2026-10-08'))->assertCreated();
        // El 9 ya es «hoy» al llegar, pero no lo era cuando se registró
        $this->enviar($trabajador, $url, $this->racion('2026-10-09'))
            ->assertUnprocessable()->assertJsonPath('errors.fecha.0', $this->fechaPosterior());
        $this->assertSame(1, MovimientoAlimento::count());
    }

    public function test_mortalidad_de_las_23_50_vale_y_una_fecha_posterior_al_registro_no(): void
    {
        $trabajador = $this->trabajador();
        $url = route('admin.mortalidades.store', $this->siembra->id);

        $this->enviar($trabajador, $url, ['fecha' => '2026-10-08', 'cantidad' => 3])->assertCreated();
        $this->enviar($trabajador, $url, ['fecha' => '2026-10-09', 'cantidad' => 3])
            ->assertUnprocessable()->assertJsonPath('errors.fecha.0', $this->fechaPosterior());
        $this->assertSame(1, Mortalidad::count());
    }

    public function test_medicion_de_las_23_50_vale_y_una_hora_posterior_al_registro_no(): void
    {
        $celador = $this->celador();
        $url = route('noche.mediciones.store', $this->lago);

        // Sin hora en el formulario (como la envían Noche y Registro rápido): se guarda la real
        $this->enviar($celador, $url, ['temperatura_c' => '27'])->assertCreated();
        $this->assertSame('2026-10-08 23:50', MedicionAgua::sole()->medido_at->setTimezone('America/Bogota')->format('Y-m-d H:i'));
        // Una hora anterior vale; una posterior al registro, aunque ya pasó al llegar, no
        $this->enviar($celador, $url, ['temperatura_c' => '27', 'medido_at' => '2026-10-08 23:45'])->assertCreated();
        $this->enviar($celador, $url, ['temperatura_c' => '27', 'medido_at' => '2026-10-09 00:05'])
            ->assertUnprocessable()->assertJsonPath('errors.medido_at.0', __('mensajes.sin_conexion.hora_posterior_al_registro', ['hora' => '08/10/2026 23:50']));
        $this->assertSame(2, MedicionAgua::count());
    }

    public function test_aviso_de_pesca_de_las_23_50_vale_aunque_el_turno_ya_termino_y_uno_futuro_no(): void
    {
        $trabajador = $this->trabajador();
        $url = route('admin.avisos-pesca.store', $this->lago);

        // Dice haber ocurrido en el futuro respecto de la llegada (más del margen): se rechaza
        $this->enviar($trabajador, $url, ['siembra_id' => $this->siembra->id], horaReal: '2026-10-09 00:20')
            ->assertUnprocessable()->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.hora_futura'));

        // Sin campo de fecha: se guarda a la hora real y el turno se revisa a esa hora (a las 00:10 ya terminó)
        $this->enviar($trabajador, $url, ['siembra_id' => $this->siembra->id])->assertCreated();
        $this->assertSame('2026-10-08 23:50', AvisoPesca::sole()->reportado_at->setTimezone('America/Bogota')->format('Y-m-d H:i'));
    }

    public function test_ronda_de_las_23_50_vale_aunque_el_turno_ya_termino_y_una_futura_no(): void
    {
        $celador = $this->celador();
        $url = route('noche.rondas.store');

        $this->enviar($celador, $url, ['tipo' => Ronda::SIN_NOVEDAD], horaReal: '2026-10-09 00:20')
            ->assertUnprocessable()->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.hora_futura'));

        $this->enviar($celador, $url, ['tipo' => Ronda::SIN_NOVEDAD])->assertCreated();
        $this->assertSame('2026-10-08 23:50', Ronda::sole()->registrada_at->setTimezone('America/Bogota')->format('Y-m-d H:i'));
    }

    public function test_la_fecha_se_valida_con_la_hora_corregida_y_no_con_la_cruda_del_telefono(): void
    {
        // Turnos largos: aquí solo se prueba la fecha
        $trabajador = $this->persona(Rol::TRABAJADOR, '2026-10-08 18:00', '2026-10-09 06:00');
        $celador = $this->persona(Rol::CELADOR, '2026-10-08 18:00', '2026-10-09 06:00');
        $racion = route('admin.raciones.store', $this->lago->id);
        $mortalidad = route('admin.mortalidades.store', $this->siembra->id);
        $medicion = route('noche.mediciones.store', $this->lago);

        // Reloj una hora ADELANTADO, registro real del 8 a las 23:50: el teléfono decía 00:50 del 9.
        // Con la hora cruda la fecha del 9 pasaría; con la corregida (23:50 del 8) se rechaza.
        $this->enviar($trabajador, $racion, $this->racion('2026-10-09'), reloj: 60)->assertUnprocessable()->assertJsonPath('errors.fecha.0', $this->fechaPosterior());
        $this->enviar($trabajador, $racion, $this->racion('2026-10-08'), reloj: 60)->assertCreated();
        $this->enviar($trabajador, $mortalidad, ['fecha' => '2026-10-09', 'cantidad' => 1], reloj: 60)->assertUnprocessable()->assertJsonPath('errors.fecha.0', $this->fechaPosterior());
        $this->enviar($trabajador, $mortalidad, ['fecha' => '2026-10-08', 'cantidad' => 1], reloj: 60)->assertCreated();
        $this->enviar($celador, $medicion, ['temperatura_c' => '27', 'medido_at' => '2026-10-09 00:05'], reloj: 60)->assertUnprocessable();
        $this->enviar($celador, $medicion, ['temperatura_c' => '27'], reloj: 60)->assertCreated();

        // Reloj una hora ATRASADO, registro real del 9 a las 00:05: el teléfono decía 23:05 del 8.
        // Con la hora cruda la fecha del 9 se rechazaría; con la corregida (00:05 del 9) vale.
        $this->enviar($trabajador, $racion, $this->racion('2026-10-09'), horaReal: '2026-10-09 00:05', reloj: -60)->assertCreated();
        $this->enviar($trabajador, $mortalidad, ['fecha' => '2026-10-09', 'cantidad' => 1], horaReal: '2026-10-09 00:05', reloj: -60)->assertCreated();
        $this->enviar($celador, $medicion, ['temperatura_c' => '27', 'medido_at' => '2026-10-09 00:03'], horaReal: '2026-10-09 00:05', reloj: -60)->assertCreated();

        $this->assertSame(2, MovimientoAlimento::count());
        $this->assertSame(2, Mortalidad::count());
        $this->assertSame(['2026-10-08 23:50', '2026-10-09 00:03'], MedicionAgua::orderBy('medido_at')->get()
            ->map(fn ($m) => $m->medido_at->setTimezone('America/Bogota')->format('Y-m-d H:i'))->all());
    }

    public function test_con_conexion_todo_sigue_igual(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR, '2026-10-08 18:00', '2026-10-09 06:00');
        $administrador = tap(User::factory()->create(['tenant_id' => app(TenantActual::class)->id()]))->asignarRoles([Rol::ADMINISTRADOR]);

        // A las 00:10 del 9 la fecha de hoy es el 9; mañana no
        $this->actingAs($trabajador)->post(route('admin.raciones.store', $this->lago->id), $this->racion('2026-10-09'))->assertSessionHasNoErrors();
        $this->actingAs($trabajador)->post(route('admin.raciones.store', $this->lago->id), $this->racion('2026-10-10'))->assertSessionHasErrors('fecha');
        $this->actingAs($trabajador)->post(route('admin.mortalidades.store', $this->siembra->id), ['fecha' => '2026-10-10', 'cantidad' => 1])
            ->assertSessionHasErrors('fecha');
        $this->assertNotSame($this->fechaPosterior('09/10/2026'), session('errors')->first('fecha'));

        // Medición desde Lagos: hora futura, el mensaje de siempre
        $this->actingAs($administrador)->post(route('admin.mediciones-agua.store', $this->lago), ['temperatura_c' => '27', 'medido_at' => '2026-10-09 00:30'])
            ->assertSessionHasErrors(['medido_at' => __('mensajes.medicion_agua.medicion_no_puede_tener_fecha')]);
        $this->actingAs($administrador)->post(route('admin.mediciones-agua.store', $this->lago), ['temperatura_c' => '27', 'medido_at' => '2026-10-09 00:05'])
            ->assertSessionHasNoErrors();
    }
}
