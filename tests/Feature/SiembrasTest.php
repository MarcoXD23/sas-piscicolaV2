<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiembrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_una_siembra_y_calcula_stock_inicial(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        $propietario->asignarRoles([Rol::PROPIETARIO]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);

        $this->actingAs($propietario)->post("/admin/lagos/{$lago->id}/siembras", [
            'especie' => 'mojarra roja',
            'fecha' => now()->format('Y-m-d'),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('siembras', [
            'lago_id' => $lago->id,
            'peces_vivos' => 1000, // Stock inicial = alevinos
            'peso_actual_g' => 5.0, // Igual al inicial
            'biomasa_kg' => 5.0, // 1000 * 5g / 1000 = 5kg
        ]);
    }

    public function test_no_permite_siembra_si_ya_hay_una_activa(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        $propietario->asignarRoles([Rol::PROPIETARIO]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        
        Siembra::create([
            'lago_id' => $lago->id,
            'especie' => 'cachama blanca',
            'fecha' => now(),
            'cantidad_alevinos' => 500,
            'peso_promedio_inicial_g' => 3,
            'peces_vivos' => 500,
            'peso_actual_g' => 3,
            'biomasa_kg' => 1.5,
        ]);

        $this->actingAs($propietario)->post("/admin/lagos/{$lago->id}/siembras", [
            'especie' => 'mojarra roja',
            'fecha' => now()->format('Y-m-d'),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
        ])->assertInvalid(['lago']);
    }
}
