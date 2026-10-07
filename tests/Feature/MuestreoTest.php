<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MuestreoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_muestreo_y_recalcula_biomasa(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        app(TenantActual::class)->establecer($tenant->id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => now()->subDays(10),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
            'peces_vivos' => 1000,
            'peso_actual_g' => 5,
            'biomasa_kg' => 5,
        ]);

        $this->actingAs($propietario)->post("/admin/siembras/{$siembra->id}/muestreos", [
            'fecha' => now()->format('Y-m-d'),
            'peces_pesados' => 10,
            'peso_total_g' => 150, // Promedio será 15g
        ])->assertRedirect();

        $this->assertDatabaseHas('muestreos', [
            'siembra_id' => $siembra->id,
            'peces_pesados' => 10,
            'peso_total_g' => 150.0,
            'peso_promedio_g' => 15.0,
        ]);

        $this->assertDatabaseHas('siembras', [
            'id' => $siembra->id,
            'peces_vivos' => 1000,
            'peso_actual_g' => 15.0,
            'biomasa_kg' => 15.0, // 1000 * 15g / 1000
        ]);
    }

    public function test_trabajador_no_puede_registrar_muestreo(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $trabajador = User::factory()->create(['tenant_id' => $tenant->id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);
        app(TenantActual::class)->establecer($tenant->id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => now(),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
            'peces_vivos' => 1000,
            'peso_actual_g' => 5,
            'biomasa_kg' => 5,
        ]);

        $this->actingAs($trabajador)->post("/admin/siembras/{$siembra->id}/muestreos", [
            'fecha' => now()->format('Y-m-d'),
            'peces_pesados' => 10,
            'peso_total_g' => 150,
        ])->assertForbidden();
    }
}
