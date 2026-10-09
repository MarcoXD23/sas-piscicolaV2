<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Panel «Agua de los lagos» del inicio: última medición vigente de cada lago con siembra (propietario y administrador). */
class InicioAguaTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $this->administrador = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->administrador->asignarRoles([Rol::ADMINISTRADOR]);
        app(TenantActual::class)->establecer($tenant->id);

        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create([
            'lago_id' => $this->lago->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => now(),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10,
            'peces_vivos' => 900,
            'peso_actual_g' => 100,
            'biomasa_kg' => 90,
        ]);
    }

    private function medir(array $valores, $momento = null): MedicionAgua
    {
        return MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->administrador->id, 'medido_at' => $momento ?? now()] + $valores);
    }

    public function test_muestra_oxigeno_y_temperatura_de_la_ultima_medicion(): void
    {
        $this->medir(['oxigeno_mg_l' => 6.4, 'temperatura_c' => 27.5]);

        $this->actingAs($this->administrador)->get(route('inicio'))->assertOk()
            ->assertSee(__('inicio.agua.titulo'))
            ->assertSee('6,4')
            ->assertSee('27,5')
            ->assertSee(__('inicio.estado_agua.bien'));
    }

    public function test_el_oxigeno_bajo_es_alerta_como_en_las_alertas_de_la_finca(): void
    {
        $this->medir(['oxigeno_mg_l' => 1.5, 'temperatura_c' => 27]);

        $respuesta = $this->actingAs($this->administrador)->get(route('inicio'))->assertOk();

        $this->assertSame('oxigeno', $respuesta->viewData('aguaLagos')->first()['problemas'][0]['parametro']);
        $respuesta->assertSee(__('inicio.estado_agua.alerta'));
    }

    public function test_una_medicion_vieja_no_se_muestra_como_si_fuera_de_hoy(): void
    {
        $this->medir(['oxigeno_mg_l' => 6.4, 'temperatura_c' => 27.5], now()->subDays(3));

        $respuesta = $this->actingAs($this->administrador)->get(route('inicio'))->assertOk();

        $this->assertNull($respuesta->viewData('aguaLagos')->first()['medicion']);
        $respuesta->assertSee(__('inicio.agua.sin_medicion'))->assertDontSee('27,5');
    }

    public function test_el_trabajador_no_recibe_el_panel_de_agua(): void
    {
        $trabajador = User::factory()->create(['tenant_id' => $this->administrador->tenant_id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);
        $this->medir(['oxigeno_mg_l' => 6.4, 'temperatura_c' => 27.5]);

        $this->actingAs($trabajador)->get(route('inicio'))->assertOk()
            ->assertViewHas('aguaLagos', fn ($agua) => $agua->isEmpty())
            ->assertDontSee(__('inicio.agua.titulo'));
    }
}
