<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\Muestreo;
use App\Models\RegistroSinConexion;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Campo\PaqueteTrabajo;
use App\Support\Formato;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Muestreo en Registro rápido (solo el administrador, sin turno): sección del paquete, envío como la cola (id
 * idempotente, corrección de hora, última hora del servidor, nota de bitácora) y el peso actual que solo cambia con
 * el muestreo más reciente por fecha. La validación del teléfono se prueba con node --test (tests/js/campo.test.mjs).
 */
class RegistroRapidoMuestreoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private Lago $lago;

    private Siembra $siembra;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 10:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 40, 'biomasa_kg' => 40]);
        $this->administrador = $this->persona(Rol::ADMINISTRADOR);
    }

    private function persona(string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => app(TenantActual::class)->id()]))->asignarRoles([$rol]);
    }

    /**
     * Envío como la cola desde Registro rápido. $horaReal: cuándo se registró (hora de Bogotá); el teléfono tenía la
     * hora del servidor de un minuto antes.
     */
    private function enviar(array $campos, ?User $usuario = null, string $horaReal = '2026-10-08 09:30', ?string $uuid = null, ?Siembra $siembra = null): TestResponse
    {
        $usuario ??= $this->administrador;
        $this->flushHeaders();
        $token = $this->actingAs($usuario)->getJson(route('campo.token'))->json('token');
        $iso = fn (string $hora, int $minutos = 0) => Carbon::parse($hora, 'America/Bogota')->addMinutes($minutos)->utc()->format('Y-m-d\TH:i:s.v\Z');

        return $this->actingAs($usuario)->withHeaders([
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $token,
            Middleware::CABECERA_ID => $uuid ?? (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => $iso($horaReal),
            Middleware::CABECERA_HORA_TELEFONO => now()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => $iso($horaReal, -1),
        ])->post(route('admin.muestreos.store', ($siembra ?? $this->siembra)->id), $campos);
    }

    private function siembraFresca(): Siembra
    {
        return Siembra::find($this->siembra->id);
    }

    public function test_solo_el_administrador_ve_la_seccion_muestreo(): void
    {
        $paquete = $this->actingAs($this->administrador)->getJson(route('campo.paquete'))->assertOk()->json();

        $this->assertContains(PaqueteTrabajo::MUESTREO, $paquete['secciones']);
        $this->assertFalse($paquete['exige_turno'][PaqueteTrabajo::MUESTREO]);
        $this->assertSame(['peces_pesados' => ['min' => 1, 'max' => 100000], 'peso_total_g' => ['min' => 0.1, 'max' => 999999],
            'unidades' => ['g', 'kg', 'lb'], 'factores' => ['g' => [100, 1], 'kg' => [100000, 1], 'lb' => [45359237, 1000]], 'unidad_defecto' => 'g'], $paquete['muestreo']);
        $siembra = $paquete['lagos'][0]['siembras'][0];
        $this->assertEquals(40, $siembra['peso_actual_g']);
        $this->assertSame(route('admin.muestreos.store', $this->siembra->id, absolute: false), $siembra['url_muestreo']);

        foreach ([Rol::TRABAJADOR, Rol::CELADOR, Rol::PROPIETARIO] as $rol) {
            $otro = $this->persona($rol);
            if ($rol === Rol::TRABAJADOR) {
                $this->ponerEnTurno($otro, $rol);
            }
            $paquete = $this->actingAs($otro)->getJson(route('campo.paquete'))->assertOk()->json();
            $this->assertNotContains(PaqueteTrabajo::MUESTREO, $paquete['secciones'], $rol);
            $this->assertSame([], $paquete['muestreo'], $rol);
            $this->assertStringNotContainsString('url_muestreo', json_encode($paquete), $rol);
            $this->assertStringNotContainsString('peso_actual_g', json_encode($paquete), $rol);
        }
    }

    public function test_el_trabajador_no_puede_enviar_un_muestreo(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => 10, 'peso_total_g' => 600], $trabajador)->assertForbidden();
        $this->assertSame(0, Muestreo::count());
    }

    public function test_enviado_como_la_cola_se_guarda_con_su_nota_y_no_se_duplica(): void
    {
        $uuid = (string) Str::uuid();
        $respuesta = $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => '20', 'peso_total_g' => '1250.5'], uuid: $uuid);

        $respuesta->assertCreated()->assertJson(['estado' => 'guardado', 'mensaje' => __('mensajes.muestreo.registrado_con_promedio', [
            'promedio' => Formato::numero(62.53, 2), 'anterior' => Formato::numero(40, 2), 'diferencia' => '+'.Formato::numero(22.53, 2),
        ])]);
        $this->assertEquals(62.53, (float) $this->siembraFresca()->peso_actual_g);
        $this->assertEquals(62.53, (float) $this->siembraFresca()->biomasa_kg);
        $bitacora = Bitacora::sole();
        $this->assertSame('muestreo', $bitacora->accion);
        $this->assertSame('2026-10-08 09:30', $bitacora->momentoLocal()->format('Y-m-d H:i'));   // hora real, no la de llegada
        $this->assertStringContainsString('registrado sin conexión, recibido el 08/10/2026 10:00', $bitacora->textoDetalle());

        // El mismo id otra vez (la cola reintentó): no se guarda de nuevo
        $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => '20', 'peso_total_g' => '1250.5'], uuid: $uuid)->assertOk()->assertJson(['estado' => 'duplicado']);
        $this->assertSame(1, Muestreo::count());
        $this->assertSame(1, RegistroSinConexion::count());
    }

    public function test_los_limites_del_paquete_son_los_del_servidor(): void
    {
        [$minPeces, $maxPeces] = PaqueteTrabajo::RANGOS_MUESTREO['peces_pesados'];
        [$minPeso, $maxPeso] = PaqueteTrabajo::RANGOS_MUESTREO['peso_total_g'];

        foreach ([
            ['peces_pesados' => $minPeces - 1, 'peso_total_g' => 100],
            ['peces_pesados' => $maxPeces + 1, 'peso_total_g' => 100],
            ['peces_pesados' => 2.5, 'peso_total_g' => 100],
            ['peces_pesados' => 10, 'peso_total_g' => $minPeso - 0.05],
            ['peces_pesados' => 10, 'peso_total_g' => $maxPeso + 0.1],
        ] as $campos) {
            $this->enviar(['fecha' => '2026-10-08'] + $campos)->assertUnprocessable();
        }
        $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => $minPeces, 'peso_total_g' => $maxPeso])->assertCreated();
        $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => $maxPeces, 'peso_total_g' => $minPeso])->assertCreated();

        // Fecha: no antes de la siembra, no después del día del registro
        $this->enviar(['fecha' => '2026-08-31', 'peces_pesados' => 10, 'peso_total_g' => 500])->assertUnprocessable();
        $this->enviar(['fecha' => '2026-10-09', 'peces_pesados' => 10, 'peso_total_g' => 500])->assertUnprocessable();
        $this->assertSame(2, Muestreo::count());
    }

    public function test_la_siembra_finalizada_mientras_tanto_se_rechaza_con_su_motivo(): void
    {
        $this->siembra->update(['finalizada_at' => now()]);

        $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => 10, 'peso_total_g' => 600])
            ->assertUnprocessable()->assertJsonPath('errors.peces_pesados.0', __('mensajes.muestreo.esta_siembra_ya_esta_finalizada'));
        $this->assertSame(0, Muestreo::count());
        $this->assertSame(0, RegistroSinConexion::count());
    }

    public function test_un_registro_anterior_a_la_ultima_hora_del_servidor_se_rechaza(): void
    {
        $this->flushHeaders();
        $token = $this->actingAs($this->administrador)->getJson(route('campo.token'))->json('token');
        $this->actingAs($this->administrador)->withHeaders([
            'Accept' => 'application/json',
            'X-CSRF-TOKEN' => $token,
            Middleware::CABECERA_ID => (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => Carbon::parse('2026-10-08 06:00', 'America/Bogota')->toIso8601String(),
            Middleware::CABECERA_HORA_TELEFONO => now()->toIso8601String(),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => Carbon::parse('2026-10-08 09:00', 'America/Bogota')->toIso8601String(),
        ])->post(route('admin.muestreos.store', $this->siembra->id), ['fecha' => '2026-10-08', 'peces_pesados' => 10, 'peso_total_g' => 600])
            ->assertUnprocessable();
        $this->assertSame(0, Muestreo::count());
    }

    public function test_uno_con_fecha_anterior_queda_en_el_historial_sin_pisar_el_peso(): void
    {
        $this->enviar(['fecha' => '2026-10-07', 'peces_pesados' => 10, 'peso_total_g' => 800])->assertCreated();   // 80 g
        $this->assertEquals(80, (float) $this->siembraFresca()->peso_actual_g);

        // Llega después uno hecho antes (el 5): queda en el historial; peso y biomasa no cambian
        $this->enviar(['fecha' => '2026-10-05', 'peces_pesados' => 10, 'peso_total_g' => 600])
            ->assertCreated()->assertJson(['mensaje' => __('mensajes.muestreo.guardado_en_historial', ['actual' => Formato::numero(80, 2)])]);
        $this->assertSame('Guardado en el historial. El peso actual sigue siendo '.Formato::numero(80, 2).' g.', __('mensajes.muestreo.guardado_en_historial', ['actual' => Formato::numero(80, 2)]));
        $siembra = $this->siembraFresca();
        $this->assertEquals(80, (float) $siembra->peso_actual_g);
        $this->assertEquals(80, (float) $siembra->biomasa_kg);
        $this->assertSame(['2026-10-05', '2026-10-07'], Muestreo::orderBy('fecha')->get()->map(fn ($m) => $m->fecha->toDateString())->all());
        // La bitácora anota los dos
        $this->assertSame(2, Bitacora::where('accion', 'muestreo')->count());
    }

    public function test_uno_con_fecha_mas_reciente_si_actualiza_el_peso(): void
    {
        $this->enviar(['fecha' => '2026-10-05', 'peces_pesados' => 10, 'peso_total_g' => 600])->assertCreated();
        $this->enviar(['fecha' => '2026-10-07', 'peces_pesados' => 10, 'peso_total_g' => 800])->assertCreated()
            ->assertJson(['mensaje' => __('mensajes.muestreo.registrado_con_promedio', ['promedio' => Formato::numero(80, 2), 'anterior' => Formato::numero(60, 2), 'diferencia' => '+'.Formato::numero(20, 2)])]);

        $this->assertEquals(80, (float) $this->siembraFresca()->peso_actual_g);
        $this->assertEquals(80, (float) $this->siembraFresca()->biomasa_kg);
    }

    public function test_el_mismo_dia_gana_el_que_llega_despues(): void
    {
        $this->enviar(['fecha' => '2026-10-07', 'peces_pesados' => 10, 'peso_total_g' => 800])->assertCreated();
        $this->enviar(['fecha' => '2026-10-07', 'peces_pesados' => 10, 'peso_total_g' => 700])->assertCreated();

        $this->assertEquals(70, (float) $this->siembraFresca()->peso_actual_g);
        $this->assertEquals(70, (float) $this->siembraFresca()->biomasa_kg);
        $this->assertSame(2, Muestreo::count());
    }

    public function test_el_muestreo_de_las_23_50_enviado_despues_de_medianoche_conserva_su_dia(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 00:10', 'America/Bogota'));

        // Hecho el 8 a las 23:50 con fecha del 8: se acepta
        $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => 10, 'peso_total_g' => 600], horaReal: '2026-10-08 23:50')->assertCreated();
        // Con fecha del 9 (posterior al día en que se registró) no se acepta, aunque ya sea 9 al llegar
        $this->enviar(['fecha' => '2026-10-09', 'peces_pesados' => 10, 'peso_total_g' => 600], horaReal: '2026-10-08 23:50')
            ->assertUnprocessable()->assertJsonValidationErrors('fecha');
        // Con conexión, a las 00:10 del 9, la fecha del 9 sí vale
        $this->flushHeaders();
        $this->actingAs($this->administrador)->post(route('admin.muestreos.store', $this->siembra->id), ['fecha' => '2026-10-09', 'peces_pesados' => 10, 'peso_total_g' => 700])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Muestreo::count());
    }

    public function test_aislamiento_por_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($otra->id);
        $administradorOtra = $this->persona(Rol::ADMINISTRADOR);
        $lagoOtra = Lago::create(['nombre' => 'X99', 'tipo' => 'tierra']);
        $siembraOtra = Siembra::create(['lago_id' => $lagoOtra->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 500, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 500, 'peso_actual_g' => 30, 'biomasa_kg' => 15]);

        // El paquete trae solo las siembras de su finca
        $paquete = $this->actingAs($administradorOtra)->getJson(route('campo.paquete'))->assertOk()->json();
        $this->assertSame([$siembraOtra->id], collect($paquete['lagos'])->flatMap(fn ($lago) => array_column($lago['siembras'], 'id'))->all());
        $this->assertStringNotContainsString('L01', json_encode($paquete));

        // No puede muestrear la siembra de la otra finca
        $this->enviar(['fecha' => '2026-10-08', 'peces_pesados' => 10, 'peso_total_g' => 600], $administradorOtra, siembra: $this->siembra)->assertNotFound();
        app(TenantActual::class)->establecer($this->finca->id);
        $this->assertSame(0, Muestreo::count());
        $this->assertEquals(40, (float) $this->siembraFresca()->peso_actual_g);
    }
}
