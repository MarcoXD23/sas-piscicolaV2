<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
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
}
