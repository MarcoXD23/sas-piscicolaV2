<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MortalidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_trabajador_puede_registrar_mortalidad_y_recalcula_biomasa(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $trabajador = User::factory()->create(['tenant_id' => $tenant->id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => now()->subDays(10),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 15,
            'peces_vivos' => 1000,
            'peso_actual_g' => 15,
            'biomasa_kg' => 15,
        ]);

        $this->actingAs($trabajador)->post("/admin/siembras/{$siembra->id}/mortalidades", [
            'fecha' => now()->format('Y-m-d'),
            'cantidad' => 100,
        ])->assertRedirect();

        $this->assertDatabaseHas('mortalidades', [
            'siembra_id' => $siembra->id,
            'cantidad' => 100,
        ]);

        $this->assertDatabaseHas('siembras', [
            'id' => $siembra->id,
            'peces_vivos' => 900,
            'peso_actual_g' => 15.0,
            'biomasa_kg' => 13.5, // 900 * 15g / 1000
        ]);
    }

    public function test_no_permite_mortalidad_mayor_a_peces_vivos(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $trabajador = User::factory()->create(['tenant_id' => $tenant->id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => now(),
            'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 5,
            'peces_vivos' => 100,
            'peso_actual_g' => 5,
            'biomasa_kg' => 0.5,
        ]);

        $this->actingAs($trabajador)->post("/admin/siembras/{$siembra->id}/mortalidades", [
            'fecha' => now()->format('Y-m-d'),
            'cantidad' => 101,
        ])->assertInvalid(['cantidad']);
    }
}
