<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tratamiento;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalidadAguaYSanidadTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 06:00'));

        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $this->lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
    }

    private function medir(array $datos)
    {
        return $this->actingAs($this->administrador)
            ->post(route('admin.mediciones-agua.store', $this->lago), $datos + ['medido_at' => now()->format('Y-m-d\TH:i')]);
    }

    public function test_registra_una_medicion_en_rango_sin_alertas(): void
    {
        $this->medir(['temperatura_c' => 28, 'oxigeno_mg_l' => 5.2, 'ph' => 7.4])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('estado', 'Medición registrada: todos los parámetros están en rango.');

        $medicion = MedicionAgua::sole();
        $this->assertSame($this->administrador->id, $medicion->user_id);
        $this->assertSame([], $medicion->alertas(['mojarra roja']));
    }

    public function test_genera_alertas_segun_las_especies_del_lago(): void
    {
        $this->medir(['temperatura_c' => 33, 'oxigeno_mg_l' => 2.5, 'ph' => 9.1, 'amonio_mg_l' => 1.5, 'nitritos_mg_l' => 0.2])
            ->assertSessionHasNoErrors();

        $alertas = MedicionAgua::sole()->alertas(['mojarra roja']);
        $this->assertCount(4, $alertas); // temperatura, oxígeno, pH y amonio; nitritos en rango
        $this->assertStringContainsString('Temperatura de 33 °C', $alertas[0]);

        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))
            ->assertSee('Alertas de la última medición')
            ->assertSee('Oxígeno de 2,5 mg/L por debajo del mínimo de Mojarra roja');
    }

    public function test_la_misma_temperatura_es_alerta_para_trucha_y_no_para_mojarra(): void
    {
        $medicion = new MedicionAgua(['temperatura_c' => 20]);

        $this->assertCount(1, $medicion->alertas(['mojarra roja']));
        $this->assertCount(1, $medicion->alertas(['trucha arcoiris']));
        $this->assertSame([], (new MedicionAgua(['temperatura_c' => 14]))->alertas(['trucha arcoiris']));
    }

    public function test_exige_al_menos_un_parametro_y_valores_posibles(): void
    {
        $this->medir([])->assertInvalid(['temperatura_c']);
        $this->medir(['ph' => 15])->assertInvalid(['ph']);
        $this->medir(['temperatura_c' => 28, 'medido_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertInvalid(['medido_at']);
        $this->assertSame(0, MedicionAgua::count());
    }

    public function test_tratamiento_con_retiro_marca_el_lago_hasta_que_termina_el_periodo(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.tratamientos.store', $this->lago), [
            'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-10-09', 'motivo' => 'Parásitos externos',
            'producto' => 'Producto X', 'dosis' => '1 g/m³', 'via' => 'baño', 'dias_retiro' => 10,
        ])->assertSessionHasNoErrors();

        $tratamiento = Tratamiento::sole();
        $this->assertSame('2026-10-19', $tratamiento->finRetiro()->toDateString());

        $this->assertTrue($this->lago->tratamientosEnRetiro()->exists());
        $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertSee('Retiro sanitario');
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertSee('Retiro sanitario hasta el 19/10/2026');

        // El último día de retiro todavía cuenta; el siguiente ya no
        $this->travelTo(Carbon::parse('2026-10-19 23:00'));
        $this->assertTrue($this->lago->tratamientosEnRetiro()->exists());
        $this->travelTo(Carbon::parse('2026-10-20 00:01'));
        $this->assertFalse($this->lago->tratamientosEnRetiro()->exists());
    }

    public function test_valida_fechas_y_via_del_tratamiento(): void
    {
        $base = ['motivo' => 'M', 'producto' => 'P', 'dosis' => 'D', 'via' => 'baño', 'dias_retiro' => 0];

        $this->actingAs($this->administrador)->post(route('admin.tratamientos.store', $this->lago),
            $base + ['fecha_inicio' => '2026-10-07', 'fecha_fin' => '2026-10-06'])->assertInvalid(['fecha_fin']);
        $this->actingAs($this->administrador)->post(route('admin.tratamientos.store', $this->lago),
            ['via' => 'inyección', 'fecha_inicio' => '2026-10-07', 'fecha_fin' => '2026-10-07'] + $base)->assertInvalid(['via']);
        $this->actingAs($this->administrador)->post(route('admin.tratamientos.store', $this->lago),
            $base + ['fecha_inicio' => '2026-10-08', 'fecha_fin' => '2026-10-09'])->assertInvalid(['fecha_inicio']);

        $this->assertSame(0, Tratamiento::count());
    }

    public function test_el_trabajador_ve_el_lago_pero_no_registra_agua_ni_sanidad(): void
    {
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->administrador->tenant_id]))->asignarRoles([Rol::TRABAJADOR]);

        $this->actingAs($trabajador)->get(route('admin.lagos.show', $this->lago))->assertOk()->assertDontSee('Medir agua');
        $this->actingAs($trabajador)->post(route('admin.mediciones-agua.store', $this->lago), ['temperatura_c' => 28])->assertForbidden();
        $this->actingAs($trabajador)->post(route('admin.tratamientos.store', $this->lago), [])->assertForbidden();
    }
}
