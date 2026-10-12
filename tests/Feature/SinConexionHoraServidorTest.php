<?php

namespace Tests\Feature;

use App\Http\Middleware\HoraServidor;
use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\RegistroSinConexion;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
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
 * Última hora del servidor en los registros sin conexión: el teléfono guarda la hora de cada respuesta
 * (X-Hora-Servidor) y la adjunta al registro (X-Ultima-Hora-Servidor). El servidor rechaza el que diga haber ocurrido
 * antes, con 2 minutos de margen. Cierra el truco de atrasar el reloj y volverlo a poner bien antes de enviar.
 * Escenario: el celador tuvo turno de 22:00 a 06:00; los registros llegan a las 07:30.
 */
class SinConexionHoraServidorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $celador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 07:30', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->celador = $this->celadorConTurno();
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
    }

    /** Celador de la finca activa con turno de 22:00 a 06:00. */
    private function celadorConTurno(): User
    {
        $celador = tap(User::factory()->create(['tenant_id' => app(TenantActual::class)->id()]))->asignarRoles([Rol::CELADOR]);
        Turno::create(['user_id' => $celador->id, 'rol' => Rol::CELADOR,
            'inicia_at' => Carbon::parse('2026-10-07 22:00', 'America/Bogota'), 'termina_at' => Carbon::parse('2026-10-08 06:00', 'America/Bogota')]);

        return $celador;
    }

    private static function iso(string $horaLocal, int $minutos = 0): string
    {
        return Carbon::parse($horaLocal, 'America/Bogota')->addMinutes($minutos)->utc()->format('Y-m-d\TH:i:s.v\Z');
    }

    /**
     * Ronda enviada como la cola del teléfono.
     * $horaReal: cuándo se hizo de verdad (hora de Bogotá). $relojAlGuardar y $relojAlEnviar: minutos que el reloj del
     * teléfono iba adelantado (+) o atrasado (−) al guardar y al enviar. $ultimaHoraServidor: la que el teléfono tenía
     * guardada al registrar (hora de Bogotá; null = no la manda).
     */
    private function enviar(User $usuario, string $horaReal, ?string $ultimaHoraServidor, int $relojAlGuardar = 0, int $relojAlEnviar = 0, ?string $uuid = null): TestResponse
    {
        $this->flushHeaders();

        return $this->actingAs($usuario)->withHeaders(array_filter([
            'Accept' => 'application/json',
            Middleware::CABECERA_ID => $uuid ?? (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => self::iso($horaReal, $relojAlGuardar),
            Middleware::CABECERA_HORA_TELEFONO => now()->addMinutes($relojAlEnviar)->utc()->format('Y-m-d\TH:i:s.v\Z'),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => $ultimaHoraServidor === null ? null : self::iso($ultimaHoraServidor),
        ]))->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD]);
    }

    public function test_cada_respuesta_trae_la_hora_del_servidor_y_la_pagina_tambien(): void
    {
        $esperada = now()->utc()->format('Y-m-d\TH:i:s.v\Z');

        $this->get('/up')->assertHeader(HoraServidor::CABECERA, $esperada);
        $this->actingAs($this->celador)->getJson(route('campo.token'))->assertOk()->assertHeader(HoraServidor::CABECERA, $esperada);
        $this->actingAs($this->celador)->getJson(route('campo.paquete'))->assertOk()->assertHeader(HoraServidor::CABECERA, $esperada);
        $this->actingAs($this->celador)->get(route('noche.index'))->assertOk()
            ->assertHeader(HoraServidor::CABECERA, $esperada)
            ->assertSee('data-hora-servidor="'.$esperada.'"', false);
    }

    public function test_reloj_atrasado_para_caer_en_el_turno_y_vuelto_a_poner_se_rechaza(): void
    {
        // A las 07:05 (turno cerrado) el teléfono tenía la hora del servidor de las 07:00. Atrasa el reloj 4 horas:
        // el registro dice 03:05. Lo vuelve a poner bien y envía a las 07:30: sin desfase que corregir.
        $respuesta = $this->enviar($this->celador, '2026-10-08 07:05', ultimaHoraServidor: '2026-10-08 07:00', relojAlGuardar: -240);

        $respuesta->assertUnprocessable()->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.antes_de_hora_servidor', [
            'momento' => '08/10/2026 03:05', 'servidor' => '08/10/2026 07:00',
        ]));
        $this->assertSame(0, Ronda::count());
        $this->assertSame(0, Bitacora::count());
        $this->assertSame(0, RegistroSinConexion::count());
    }

    public function test_el_truco_funcionaba_sin_la_hora_del_servidor(): void
    {
        // Lo que pasaba antes (y pasaría con SIN_CONEXION_EXIGIR_HORA_SERVIDOR=false y sin la cabecera)
        config(['sin_conexion.exigir_hora_servidor' => false]);
        $this->enviar($this->celador, '2026-10-08 07:05', ultimaHoraServidor: null, relojAlGuardar: -240)->assertCreated();
        $this->assertSame('2026-10-08 03:05', Ronda::sole()->registrada_at->setTimezone('America/Bogota')->format('Y-m-d H:i'));
    }

    public function test_reloj_adelantado_todo_el_tiempo_se_corrige_y_se_acepta(): void
    {
        // El reloj va 60 minutos adelantado al guardar y al enviar; la hora del servidor (02:50) es la real
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 02:50', relojAlGuardar: 60, relojAlEnviar: 60)
            ->assertCreated()->assertJson(['estado' => 'guardado']);

        $this->assertSame('2026-10-08 03:00', Ronda::sole()->registrada_at->setTimezone('America/Bogota')->format('Y-m-d H:i'));
        $this->assertSame(60, Bitacora::sole()->detalle['reloj_corregido_min']);
    }

    public function test_un_registro_legitimo_de_varias_horas_sin_conexion_se_acepta_con_su_nota(): void
    {
        // La última señal fue al empezar el turno (22:30); la ronda, a las 03:00; llega a las 07:30
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-07 22:30')->assertCreated();

        $this->assertStringContainsString('registrado sin conexión, recibido el 08/10/2026 07:30', Bitacora::sole()->textoDetalle());
        $this->assertSame(1, RegistroSinConexion::count());
    }

    public function test_el_margen_es_de_2_minutos(): void
    {
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 03:01')->assertCreated();
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 03:03')->assertUnprocessable();
        $this->assertSame(1, Ronda::count());
    }

    public function test_hora_automatica_corregida_entre_guardar_y_enviar_se_rechaza_con_su_motivo(): void
    {
        // A las 07:00 el teléfono iba 3 horas atrasado sin saberlo (el registro dice 04:00) y tenía la hora del
        // servidor de las 06:58. Antes de enviar, la hora automática lo corrigió: ya no hay desfase que delate el error.
        $respuesta = $this->enviar($this->celador, '2026-10-08 07:00', ultimaHoraServidor: '2026-10-08 06:58', relojAlGuardar: -180);

        // 422: la cola lo pasa a «Rechazados» con este motivo y sus datos, para repetirlo (clasificar(422) en tests/js)
        $motivo = $respuesta->assertUnprocessable()->json('errors.registro.0');
        $this->assertSame(__('mensajes.sin_conexion.antes_de_hora_servidor', ['momento' => '08/10/2026 04:00', 'servidor' => '08/10/2026 06:58']), $motivo);
        $this->assertStringContainsString('fecha y la hora automáticas', $motivo);
        $this->assertSame(0, Ronda::count());
        $this->assertSame(0, RegistroSinConexion::count());
    }

    public function test_sin_la_cabecera_se_rechaza_salvo_que_se_afloje(): void
    {
        $this->assertTrue(config('sin_conexion.exigir_hora_servidor'));
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: null)
            ->assertUnprocessable()->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.sin_hora_servidor'));
        $this->assertSame(0, Ronda::count());

        config(['sin_conexion.exigir_hora_servidor' => false]);
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: null)->assertCreated();
    }

    public function test_una_hora_del_servidor_futura_o_invalida_no_se_acepta(): void
    {
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 09:00')
            ->assertUnprocessable()->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.datos_invalidos'));

        $this->flushHeaders();
        $this->actingAs($this->celador)->withHeaders([
            'Accept' => 'application/json',
            Middleware::CABECERA_ID => (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => self::iso('2026-10-08 03:00'),
            Middleware::CABECERA_HORA_TELEFONO => now()->toIso8601String(),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => 'no-es-una-hora',
        ])->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])
            ->assertUnprocessable()->assertJsonPath('errors.registro.0', __('mensajes.sin_conexion.datos_invalidos'));
        $this->assertSame(0, Ronda::count());
    }

    public function test_el_id_idempotente_sigue_funcionando(): void
    {
        $uuid = (string) Str::uuid();
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 02:00', uuid: $uuid)->assertCreated();
        $this->enviar($this->celador, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 02:00', uuid: $uuid)->assertOk()->assertJson(['estado' => 'duplicado']);
        $this->assertSame(1, Ronda::count());
    }

    public function test_dos_personas_en_el_mismo_telefono_usan_cada_una_su_hora(): void
    {
        $otro = $this->celadorConTurno();

        // Cada persona tiene su clave en el teléfono (finca|persona), y con ella su última hora del servidor
        $this->actingAs($this->celador)->get(route('noche.index'))->assertSee('data-cola-clave="'.$this->finca->id.'|'.$this->celador->id.'"', false);
        $this->actingAs($otro)->get(route('noche.index'))->assertSee('data-cola-clave="'.$this->finca->id.'|'.$otro->id.'"', false);

        // El celador entró a las 07:00 y atrasó el reloj: rechazado. El otro trabajó sin señal desde las 02:00: aceptado.
        $this->enviar($this->celador, '2026-10-08 07:05', ultimaHoraServidor: '2026-10-08 07:00', relojAlGuardar: -240)->assertUnprocessable();
        $this->enviar($otro, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 02:00')->assertCreated();

        $this->assertSame([$otro->id], Ronda::pluck('user_id')->all());
    }

    public function test_aislamiento_por_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($otra->id);
        $celadorOtra = $this->celadorConTurno();

        // El rechazo en una finca no deja nada en ninguna; el registro válido de la otra queda solo en la suya
        $this->enviar($this->celador, '2026-10-08 07:05', ultimaHoraServidor: '2026-10-08 07:00', relojAlGuardar: -240)->assertUnprocessable();
        $this->enviar($celadorOtra, '2026-10-08 03:00', ultimaHoraServidor: '2026-10-08 02:00')->assertCreated();

        app(TenantActual::class)->establecer($this->finca->id);
        $this->assertSame(0, Ronda::count());
        $this->assertSame(0, RegistroSinConexion::count());

        app(TenantActual::class)->establecer($otra->id);
        $this->assertSame(1, Ronda::count());
        $this->assertSame($otra->id, Ronda::sole()->tenant_id);
    }
}
