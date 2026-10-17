<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\Jornada;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Campo\PaqueteTrabajo;
use App\Support\Proceso\Ayuno;
use App\Support\TenantActual;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * F6a, tema 5: ayuno del lago que se va a pescar y día de proceso sin alimentación. Hoy es 2026-10-15 en la finca.
 */
class AyunoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $administrador;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->ajuste(CatalogoAjustes::PROCESO_JORNADAS, CatalogoAjustes::ENCENDIDA);

        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => '2026-04-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 400, 'biomasa_kg' => 400]);
    }

    private function ajuste(string $clave, string|int $valor): void
    {
        AjusteFinca::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    private function programar(string $fecha): Jornada
    {
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => $fecha, 'lagos' => [$this->lago->id]])
            ->assertSessionHasNoErrors();

        return Jornada::query()->latest('id')->firstOrFail();
    }

    public function test_el_lago_entra_en_ayuno_desde_el_dia_anterior(): void
    {
        $this->programar('2026-10-16');

        $this->assertSame('2026-10-16', Ayuno::delLago($this->lago->id)?->toDateString());
        $this->assertNull(Ayuno::delLago($this->lago->id, CarbonImmutable::parse('2026-10-14')));
        $this->assertNotNull(Ayuno::delLago($this->lago->id, CarbonImmutable::parse('2026-10-16')));
        $this->assertNull(Ayuno::delLago($this->lago->id, CarbonImmutable::parse('2026-10-17')));
    }

    public function test_los_dias_de_ayuno_son_configurables(): void
    {
        $this->programar('2026-10-17');
        $this->assertNull(Ayuno::delLago($this->lago->id));

        $this->ajuste(CatalogoAjustes::PROCESO_DIAS_AYUNO, 2);
        $this->assertNotNull(Ayuno::delLago($this->lago->id));

        // Con 0, solo el día de la pesca
        $this->ajuste(CatalogoAjustes::PROCESO_DIAS_AYUNO, 0);
        $this->assertNull(Ayuno::delLago($this->lago->id, CarbonImmutable::parse('2026-10-16')));
        $this->assertNotNull(Ayuno::delLago($this->lago->id, CarbonImmutable::parse('2026-10-17')));
    }

    public function test_cancelar_la_jornada_quita_el_ayuno(): void
    {
        $jornada = $this->programar('2026-10-16');
        $this->actingAs($this->administrador)->post(route('admin.jornadas.cancelar', $jornada))->assertSessionHasNoErrors();

        $this->assertNull(Ayuno::delLago($this->lago->id));
    }

    public function test_con_las_jornadas_apagadas_no_hay_ayuno_ni_dia_sin_alimentacion(): void
    {
        $this->programar('2026-10-15');
        $this->ajuste(CatalogoAjustes::PROCESO_JORNADAS, CatalogoAjustes::APAGADA);

        $this->assertSame([], Ayuno::lagos());
        $this->assertFalse(Ayuno::diaSinAlimentacion());
    }

    public function test_la_fecha_es_la_de_la_finca_no_la_de_utc(): void
    {
        $this->ajuste(CatalogoAjustes::PROCESO_DIAS_AYUNO, 0);
        $this->programar('2026-10-16');

        // 22:00 del 15 en Bogotá son las 03:00 del 16 en UTC: en la finca todavía es el 15
        $this->travelTo(Carbon::parse('2026-10-15 22:00', 'America/Bogota'));
        $this->assertNull(Ayuno::delLago($this->lago->id));
        $this->assertFalse(Ayuno::diaSinAlimentacion());

        $this->travelTo(Carbon::parse('2026-10-16 00:30', 'America/Bogota'));
        $this->assertNotNull(Ayuno::delLago($this->lago->id));
        $this->assertTrue(Ayuno::diaSinAlimentacion());
    }

    public function test_dia_sin_alimentacion_en_inicio_y_en_registro_rapido(): void
    {
        $this->programar('2026-10-15');

        $this->actingAs($this->administrador)->get(route('inicio'))->assertOk()->assertSee(__('jornadas.ayuno.dia_sin_alimentacion'));
        $paquete = PaqueteTrabajo::para($this->administrador);
        $this->assertTrue($paquete['dia_sin_alimentacion']);
        $this->assertSame('2026-10-15', $paquete['lagos'][0]['en_ayuno']);
        $this->assertArrayNotHasKey('racion_sugerida_kg', $paquete['lagos'][0]);
    }

    public function test_sin_la_opcion_el_dia_de_proceso_se_alimenta(): void
    {
        $this->ajuste(CatalogoAjustes::PROCESO_SIN_ALIMENTACION, CatalogoAjustes::APAGADA);
        $this->programar('2026-10-15');

        $this->assertFalse(Ayuno::diaSinAlimentacion());
        $this->actingAs($this->administrador)->get(route('inicio'))->assertOk()->assertDontSee(__('jornadas.ayuno.dia_sin_alimentacion'));
    }

    public function test_inicio_del_trabajador_dice_en_ayuno_en_vez_de_la_racion(): void
    {
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->programar('2026-10-16');

        $this->actingAs($trabajador)->get(route('inicio'))->assertOk()
            ->assertSee(__('jornadas.ayuno.en_ayuno', ['fecha' => '16/10']));
    }

    public function test_registrar_racion_a_un_lago_en_ayuno_avisa_pero_no_bloquea(): void
    {
        $this->programar('2026-10-16');
        StockAlimento::create(['tipo_concentrado' => 'Cachama 30%', 'cantidad_kg' => 100]);

        $this->actingAs($this->administrador)->post(route('admin.raciones.store', $this->lago), [
            'fecha' => '2026-10-15', 'tipo_concentrado' => 'Cachama 30%', 'cantidad_kg' => 10,
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('estado', fn (string $mensaje) => str_contains($mensaje, __('jornadas.ayuno.racion_registrada', ['lago' => 'L01', 'fecha' => '16/10'])));

        $this->assertDatabaseHas('movimientos_alimento', ['lago_id' => $this->lago->id, 'cantidad_kg' => 10]);
    }
}
