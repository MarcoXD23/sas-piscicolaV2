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

class SiembrasTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_una_siembra_y_calcula_stock_inicial(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        app(TenantActual::class)->establecer($tenant->id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);

        $this->actingAs($propietario)->post("/admin/lagos/{$lago->id}/siembras", [
            'especie_id' => $this->especie('mojarra roja'),
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

    public function test_la_misma_especie_solo_se_repite_en_un_lago_como_lote_de_otra_fecha(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        app(TenantActual::class)->establecer($tenant->id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);

        Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $this->especie('cachama blanca'),
            'fecha' => now(),
            'cantidad_alevinos' => 500,
            'peso_promedio_inicial_g' => 3,
            'peces_vivos' => 500,
            'peso_actual_g' => 3,
            'biomasa_kg' => 1.5,
        ]);

        $this->actingAs($propietario)->post("/admin/lagos/{$lago->id}/siembras", [
            'especie_id' => $this->especie('cachama blanca'),
            'fecha' => now()->format('Y-m-d'),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
        ])->assertInvalid(['especie_id' => 'Este lago ya tiene un lote activo de Cachama blanca']);
        $this->assertSame(1, Siembra::count());

        // Otro lote de la misma especie con fecha distinta sí se permite
        $this->actingAs($propietario)->post("/admin/lagos/{$lago->id}/siembras", [
            'especie_id' => $this->especie('cachama blanca'),
            'fecha' => now()->subDays(3)->format('Y-m-d'),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $lago->siembrasActivas()->count());
    }
}
