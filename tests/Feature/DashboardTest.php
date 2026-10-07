<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tarjetas_suman_correctamente_la_biomasa_y_peces_de_las_siembras_activas(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        $propietario->asignarRoles([Rol::PROPIETARIO]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);

        $lago1 = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create([
            'lago_id' => $lago1->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => now(),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10,
            'peces_vivos' => 900,
            'peso_actual_g' => 100,
            'biomasa_kg' => 90, // 900 * 100 / 1000
        ]);

        $lago2 = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        Siembra::create([
            'lago_id' => $lago2->id,
            'especie_id' => $this->especie('cachama blanca'),
            'fecha' => now(),
            'cantidad_alevinos' => 2000,
            'peso_promedio_inicial_g' => 5,
            'peces_vivos' => 1900,
            'peso_actual_g' => 50,
            'biomasa_kg' => 95, // 1900 * 50 / 1000
        ]);

        Lago::create(['nombre' => 'L03', 'tipo' => 'tierra']); // Vacío, no suma

        $response = $this->actingAs($propietario)->get('/');
        
        $response->assertOk();
        // 90 + 95 = 185
        $response->assertSee('185,00'); 
        // 900 + 1900 = 2800
        $response->assertSee('2.800');
        // 2 lagos con siembra
        $response->assertViewHas('lagosActivos', 2);
    }
}
