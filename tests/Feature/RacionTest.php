<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_trabajador_registra_racion_y_descuenta_stock(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $trabajador = User::factory()->create(['tenant_id' => $tenant->id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->sembrar($lago);
        
        StockAlimento::create([
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'cantidad_kg' => 100,
        ]);

        $this->actingAs($trabajador)->post("/admin/lagos/{$lago->id}/raciones", [
            'fecha' => now()->format('Y-m-d'),
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'cantidad_kg' => 20.5,
        ])->assertRedirect();

        $this->assertDatabaseHas('movimientos_alimento', [
            'lago_id' => $lago->id,
            'tipo_movimiento' => 'salida',
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'cantidad_kg' => 20.5,
        ]);

        $this->assertDatabaseHas('stocks_alimento', [
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'cantidad_kg' => 79.5, // 100 - 20.5
        ]);
    }

    public function test_impide_salida_si_supera_el_stock(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $trabajador = User::factory()->create(['tenant_id' => $tenant->id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->sembrar($lago);
        
        StockAlimento::create([
            'tipo_concentrado' => 'Cachama 24%',
            'cantidad_kg' => 50,
        ]);

        $this->actingAs($trabajador)->post("/admin/lagos/{$lago->id}/raciones", [
            'fecha' => now()->format('Y-m-d'),
            'tipo_concentrado' => 'Cachama 24%',
            'cantidad_kg' => 51,
        ])->assertInvalid(['cantidad_kg']);
    }

    public function test_no_se_alimenta_un_lago_sin_peces_ni_se_descuenta_la_bodega(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        $propietario->asignarRoles([Rol::PROPIETARIO]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 50]);

        $this->actingAs($propietario)->post(route('admin.raciones.store', $lago), [
            'fecha' => now()->format('Y-m-d'),
            'tipo_concentrado' => 'Engorde',
            'cantidad_kg' => 5,
        ])->assertInvalid(['cantidad_kg']);

        $this->assertEquals(50, (float) StockAlimento::sole()->cantidad_kg);
        $this->assertDatabaseCount('movimientos_alimento', 0);
    }

    private function sembrar(Lago $lago): Siembra
    {
        return Siembra::create(['lago_id' => $lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subDays(10),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
    }
}
