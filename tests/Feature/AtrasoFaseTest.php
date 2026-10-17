<?php

namespace Tests\Feature;

use App\Models\Cosecha;
use App\Models\Fase;
use App\Models\Lago;
use App\Models\ReglaFase;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EstadoFase;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * F5: atraso en días de la fase en el detalle del lago. Los días en la fase cuentan desde la fecha más reciente entre la
 * llegada del lote al lago y el día en que el lago tomó su fase; se comparan con los días esperados de la regla (que
 * pone cada finca). Datos ficticios.
 */
class AtrasoFaseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private Fase $fase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-10 08:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
        // Una fase de prueba con su regla: 30 días esperados y salida a 100 g (valores de la finca, no del código)
        $this->fase = Fase::create(['nombre' => 'Fase de prueba', 'orden' => 9]);
        ReglaFase::create(['fase_id' => $this->fase->id, 'especie_id' => $this->especie('mojarra roja'), 'peso_entrada_g' => 10, 'peso_salida_g' => 100, 'dias_esperados' => 30]);
    }

    private function lote(Lago $lago, string $fecha, float $peso): Siembra
    {
        return Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => $fecha, 'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 100, 'peso_actual_g' => $peso, 'biomasa_kg' => $peso / 10]);
    }

    public function test_cuenta_los_dias_en_la_fase_y_el_atraso(): void
    {
        // El lago tiene la fase desde antes: cuenta desde la llegada del lote (10/09 → 61 días, 31 de atraso)
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra', 'fase_id' => $this->fase->id, 'fase_desde' => '2026-08-01']);
        $lote = $this->lote($lago, '2026-09-10', 60);

        $dias = EstadoFase::paraSiembra($lago, $lote)['dias'];
        $this->assertSame(['en_fase' => 61, 'desde' => '2026-09-10', 'esperados' => 30, 'atraso' => 31], $dias);

        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $lago))->assertOk()
            ->assertSee(__('fases.comparacion.dias'))
            ->assertSee(trans_choice('fases.comparacion.dias_atraso', 31, ['dias' => '31']));

        // Si ya llegó al peso de salida no hay atraso
        $lote->update(['peso_actual_g' => 100]);
        $this->assertSame(0, EstadoFase::paraSiembra($lago, $lote->fresh())['dias']['atraso']);
    }

    public function test_al_cambiar_la_fase_del_lago_los_dias_cuentan_desde_ese_dia(): void
    {
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->assertNull($lago->fase_desde);
        $lote = $this->lote($lago, '2026-09-10', 60);

        $this->travelTo(Carbon::parse('2026-11-01 08:00', 'America/Bogota'));
        $this->actingAs($this->administrador)->put(route('admin.lagos.update', $lago), ['nombre' => 'L01', 'tipo' => 'tierra', 'fase_id' => $this->fase->id])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-01', $lago->fresh()->fase_desde->toDateString());

        $this->travelTo(Carbon::parse('2026-11-10 08:00', 'America/Bogota'));
        $dias = EstadoFase::paraSiembra($lago->fresh(), $lote)['dias'];
        $this->assertSame(['en_fase' => 9, 'desde' => '2026-11-01', 'esperados' => 30, 'atraso' => 0], $dias);

        // Editar otra cosa del lago no cambia la fecha; quitar la fase la borra
        $this->actingAs($this->administrador)->put(route('admin.lagos.update', $lago), ['nombre' => 'L01 nuevo', 'tipo' => 'tierra', 'fase_id' => $this->fase->id]);
        $this->assertSame('2026-11-01', $lago->fresh()->fase_desde->toDateString());
        $this->actingAs($this->administrador)->put(route('admin.lagos.update', $lago), ['nombre' => 'L01 nuevo', 'tipo' => 'tierra', 'fase_id' => '']);
        $this->assertNull($lago->fresh()->fase_desde);
    }

    public function test_un_lote_creado_por_traslado_cuenta_desde_su_llegada(): void
    {
        $lago = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra', 'fase_id' => $this->fase->id, 'fase_desde' => '2026-01-01']);
        $origen = $this->lote(Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']), '2026-06-01', 50);

        $this->actingAs($this->administrador)->post(route('admin.traslados.store', $origen), [
            'lago_destino_id' => $lago->id, 'fecha' => '2026-11-05', 'cantidad' => 40, 'peso_promedio_g' => 50, 'modo' => 'aparte',
        ])->assertSessionHasNoErrors();

        $nuevo = $lago->siembrasActivas()->sole();
        $this->assertSame(['en_fase' => 5, 'desde' => '2026-11-05', 'esperados' => 30, 'atraso' => 0], EstadoFase::paraSiembra($lago, $nuevo)['dias']);
    }

    public function test_la_supervivencia_cuenta_lo_recibido_lo_cosechado_y_lo_trasladado(): void
    {
        ReglaFase::query()->update(['supervivencia_porcentaje' => 95]);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra', 'fase_id' => $this->fase->id]);
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $tercero = Lago::create(['nombre' => 'L03', 'tipo' => 'tierra', 'fase_id' => $this->fase->id]);
        $lote = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 500,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 500, 'peso_actual_g' => 50, 'biomasa_kg' => 25]);
        $origen = Siembra::create(['lago_id' => $otro->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);

        // Recibe 200 peces (500 + 200 = 700), mueren 50, salen 100 a un lote aparte y se cosechan 50: quedan 500
        $this->actingAs($this->administrador)->post(route('admin.traslados.store', $origen), [
            'lago_destino_id' => $lago->id, 'fecha' => '2026-10-01', 'cantidad' => 200, 'peso_promedio_g' => 50, 'modo' => 'sumar',
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.mortalidades.store', $lote), ['fecha' => '2026-10-05', 'cantidad' => 50])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.traslados.store', $lote), [
            'lago_destino_id' => $tercero->id, 'fecha' => '2026-10-10', 'cantidad' => 100, 'peso_promedio_g' => 60, 'modo' => 'aparte',
        ])->assertSessionHasNoErrors();
        Cosecha::create(['siembra_id' => $lote->id, 'lago_id' => $lago->id, 'user_id' => $this->administrador->id, 'destino' => 'Prueba',
            'fecha' => '2026-10-20', 'peces' => 50, 'kg' => 4, 'peso_promedio_g' => 80]);
        $lote->refresh()->update(['peces_vivos' => 500]);

        // (500 vivos + 50 cosechados + 100 trasladados) ÷ (500 sembrados + 200 recibidos) = 92,9 % (antes daba 100 %)
        $this->assertSame(['real' => 92.9, 'esperada' => 95.0, 'baja' => true], EstadoFase::paraSiembra($lago, $lote->fresh())['supervivencia']);

        // El lote aparte no tiene sembrados: sus 100 peces son los recibidos del traslado que lo creó
        $aparte = $tercero->siembrasActivas()->sole();
        $this->assertSame(['real' => 100.0, 'esperada' => 95.0, 'baja' => false], EstadoFase::paraSiembra($tercero, $aparte)['supervivencia']);

        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $lago))->assertOk()
            ->assertSee(__('fases.comparacion.porcentaje', ['valor' => '92,9']))->assertSee(__('fases.comparacion.supervivencia_baja'));
    }

    public function test_sin_dias_esperados_no_se_juzga(): void
    {
        ReglaFase::query()->update(['dias_esperados' => null]);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra', 'fase_id' => $this->fase->id, 'fase_desde' => '2026-08-01']);
        $lote = $this->lote($lago, '2026-08-01', 60);

        $dias = EstadoFase::paraSiembra($lago, $lote)['dias'];
        $this->assertSame(101, $dias['en_fase']);
        $this->assertNull($dias['esperados']);
        $this->assertNull($dias['atraso']);
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $lago))->assertOk()
            ->assertSee(__('fases.comparacion.dias_esperados', ['esperados' => __('fases.comparacion.sin_dato')]))
            ->assertDontSee('de atraso');
    }
}
