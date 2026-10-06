<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Traslado;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrasladosTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    private Lago $origen;

    private Lago $destino;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->origen = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->destino = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
    }

    private function siembra(Lago $lago, string $especie, int $peces, float $peso): Siembra
    {
        return Siembra::create(['lago_id' => $lago->id, 'especie' => $especie, 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => $peces, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces,
            'peso_actual_g' => $peso, 'biomasa_kg' => $peces * $peso / 1000]);
    }

    private function trasladar(Siembra $siembra, array $datos = [])
    {
        return $this->actingAs($this->administrador)->post(route('admin.traslados.store', $siembra), $datos + [
            'lago_destino_id' => $this->destino->id,
            'fecha' => now()->toDateString(),
            'cantidad' => 400,
            'peso_promedio_g' => 50,
        ]);
    }

    public function test_desdoble_a_lago_vacio_crea_siembra_nueva_y_descuenta_el_origen(): void
    {
        $origen = $this->siembra($this->origen, 'mojarra roja', 1000, 50);

        $this->trasladar($origen)->assertSessionHasNoErrors()->assertRedirect(route('admin.lagos.show', $this->origen));

        $origen->refresh();
        $this->assertSame(600, $origen->peces_vivos);
        $this->assertEquals(30.0, (float) $origen->biomasa_kg);
        $this->assertNull($origen->finalizada_at);

        $nueva = $this->destino->siembrasActivas()->sole();
        $this->assertSame('mojarra roja', $nueva->especie);
        $this->assertSame(400, $nueva->peces_vivos);
        $this->assertEquals(20.0, (float) $nueva->biomasa_kg);

        $traslado = Traslado::sole();
        $this->assertSame($origen->id, $traslado->siembra_origen_id);
        $this->assertSame($nueva->id, $traslado->siembra_destino_id);
        $this->assertSame($this->administrador->id, $traslado->user_id);
    }

    public function test_se_suma_a_la_misma_especie_del_destino_con_peso_ponderado(): void
    {
        $origen = $this->siembra($this->origen, 'mojarra roja', 1000, 50);
        $existente = $this->siembra($this->destino, 'mojarra roja', 600, 100);

        $this->trasladar($origen)->assertSessionHasNoErrors();

        $existente->refresh();
        // (600 × 100 + 400 × 50) / 1000 = 80 g
        $this->assertSame(1000, $existente->peces_vivos);
        $this->assertEquals(80.0, (float) $existente->peso_actual_g);
        $this->assertEquals(80.0, (float) $existente->biomasa_kg);
        $this->assertSame(1, $this->destino->siembrasActivas()->count());
    }

    public function test_traslado_total_finaliza_la_siembra_de_origen(): void
    {
        $origen = $this->siembra($this->origen, 'cachama blanca', 400, 50);

        $this->trasladar($origen)->assertSessionHasNoErrors();

        $this->assertSame(0, $origen->fresh()->peces_vivos);
        $this->assertNotNull($origen->fresh()->finalizada_at);
        $this->assertSame(0, $this->origen->siembrasActivas()->count());
    }

    public function test_no_traslada_mas_peces_de_los_que_hay(): void
    {
        $origen = $this->siembra($this->origen, 'mojarra roja', 300, 50);

        $this->trasladar($origen)->assertInvalid(['cantidad']);

        $this->assertSame(300, $origen->fresh()->peces_vivos);
        $this->assertSame(0, Traslado::count());
        $this->assertSame(0, $this->destino->siembras()->count());
    }

    public function test_no_traslada_al_mismo_lago(): void
    {
        $origen = $this->siembra($this->origen, 'mojarra roja', 1000, 50);

        $this->trasladar($origen, ['lago_destino_id' => $this->origen->id])->assertInvalid(['lago_destino_id']);
    }

    public function test_respeta_las_reglas_de_clima_del_policultivo(): void
    {
        $origen = $this->siembra($this->origen, 'trucha arcoiris', 1000, 50);
        $this->siembra($this->destino, 'mojarra roja', 500, 50);

        $this->trasladar($origen)->assertInvalid(['lago_destino_id']);
        $this->assertSame(1000, $origen->fresh()->peces_vivos);
    }

    public function test_no_traslada_desde_siembras_finalizadas(): void
    {
        $origen = $this->siembra($this->origen, 'mojarra roja', 1000, 50);
        $origen->update(['finalizada_at' => now()]);

        $this->trasladar($origen)->assertInvalid(['cantidad']);
    }

    public function test_no_traslada_a_lagos_de_otra_finca(): void
    {
        $origen = $this->siembra($this->origen, 'mojarra roja', 1000, 50);
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajeno = Lago::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'nombre' => 'X1', 'tipo' => 'tierra']);

        $this->trasladar($origen, ['lago_destino_id' => $ajeno->id])->assertInvalid(['lago_destino_id']);
        $this->assertSame(1000, $origen->fresh()->peces_vivos);
    }

    public function test_el_trabajador_no_puede_trasladar(): void
    {
        $origen = $this->siembra($this->origen, 'mojarra roja', 1000, 50);
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->administrador->tenant_id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $this->actingAs($trabajador)->post(route('admin.traslados.store', $origen), [])->assertForbidden();
    }
}
