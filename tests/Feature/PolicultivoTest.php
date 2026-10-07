<?php

namespace Tests\Feature;

use App\Models\Especie;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicultivoTest extends TestCase
{
    use RefreshDatabase;

    private User $propietario;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $this->propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $this->propietario->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        app(TenantActual::class)->establecer($tenant->id);

        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
    }

    private function sembrar(string $especie, int $alevinos = 1000, array $extra = [])
    {
        return $this->actingAs($this->propietario)->post(route('admin.siembras.store', $this->lago), $extra + [
            // Una especie que no está en el catálogo de la finca no tiene id: se envía uno que no existe
            'especie_id' => Especie::query()->where('clave', $especie)->value('id') ?? 999999,
            'fecha' => now()->format('Y-m-d'),
            'cantidad_alevinos' => $alevinos,
            'peso_promedio_inicial_g' => 5,
        ]);
    }

    public function test_permite_varias_especies_calidas_en_el_mismo_lago(): void
    {
        $this->sembrar('mojarra roja')->assertSessionHasNoErrors();
        $this->sembrar('cachama blanca', 300)->assertSessionHasNoErrors();
        $this->sembrar('bocachico', 250)->assertSessionHasNoErrors();

        $this->assertSame(3, $this->lago->siembrasActivas()->count());
    }

    public function test_mezclar_agua_fria_y_calida_es_una_advertencia_que_se_confirma(): void
    {
        $this->sembrar('mojarra roja')->assertSessionHasNoErrors();
        $this->sembrar('trucha arcoiris')->assertInvalid(['confirmar_clima' => 'Atención: Trucha arcoíris es de Agua fría']);
        $this->assertSame(1, $this->lago->siembrasActivas()->count());

        // Con la confirmación sí se siembra
        $this->sembrar('trucha arcoiris', 1000, ['confirmar_clima' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(2, $this->lago->siembrasActivas()->count());
    }

    public function test_rechaza_especies_fuera_del_catalogo(): void
    {
        $this->sembrar('pez inventado')->assertInvalid(['especie_id']);
        $this->assertSame(0, Siembra::count());
    }

    public function test_finalizar_una_siembra_libera_la_especie_y_conserva_el_historial(): void
    {
        $this->sembrar('mojarra roja')->assertSessionHasNoErrors();
        $siembra = Siembra::firstOrFail();

        $this->actingAs($this->propietario)->post(route('admin.siembras.finalizar', $siembra))
            ->assertRedirect(route('admin.lagos.index'));

        $this->assertNotNull($siembra->fresh()->finalizada_at);
        $this->assertSame(0, $this->lago->siembrasActivas()->count());

        // El lago se puede volver a sembrar con la misma especie
        $this->sembrar('mojarra roja')->assertSessionHasNoErrors();
        $this->assertSame(2, Siembra::count());
        $this->assertSame(1, $this->lago->siembrasActivas()->count());
    }

    public function test_no_se_finaliza_dos_veces(): void
    {
        $this->sembrar('mojarra roja');
        $siembra = Siembra::firstOrFail();
        $siembra->update(['finalizada_at' => now()]);

        $this->actingAs($this->propietario)->post(route('admin.siembras.finalizar', $siembra))
            ->assertInvalid(['siembra']);
    }

    public function test_trabajador_no_puede_finalizar_siembras(): void
    {
        $this->sembrar('mojarra roja');
        $trabajador = User::factory()->create(['tenant_id' => $this->propietario->tenant_id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);

        $this->actingAs($trabajador)->post(route('admin.siembras.finalizar', Siembra::firstOrFail()))
            ->assertForbidden();
    }

    public function test_no_registra_mortalidad_ni_muestreo_en_siembras_finalizadas(): void
    {
        $this->sembrar('mojarra roja');
        $siembra = Siembra::firstOrFail();
        $siembra->update(['finalizada_at' => now()]);

        $this->actingAs($this->propietario)->post(route('admin.mortalidades.store', $siembra), [
            'fecha' => now()->format('Y-m-d'),
            'cantidad' => 5,
        ])->assertInvalid(['cantidad']);

        $this->actingAs($this->propietario)->post(route('admin.muestreos.store', $siembra), [
            'fecha' => now()->format('Y-m-d'),
            'peces_pesados' => 10,
            'peso_total_g' => 100,
        ])->assertInvalid(['peces_pesados']);

        $this->assertSame(1000, $siembra->fresh()->peces_vivos);
        $this->assertSame(0, $siembra->muestreos()->count());
    }

    public function test_el_dashboard_suma_solo_siembras_activas(): void
    {
        $this->sembrar('mojarra roja', 1000);
        $this->sembrar('cachama blanca', 200);
        Siembra::where('especie_id', $this->especie('cachama blanca'))->update(['finalizada_at' => now()]);

        $this->actingAs($this->propietario)->get(route('inicio'))
            ->assertViewHas('pecesVivos', 1000)
            ->assertViewHas('lagosActivos', 1);
    }
}
