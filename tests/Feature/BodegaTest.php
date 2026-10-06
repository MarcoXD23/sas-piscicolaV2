<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodegaTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_el_propietario_accede_a_bodega(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        $propietario->asignarRoles([Rol::PROPIETARIO]);

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->asignarRoles([Rol::ADMINISTRADOR]);

        $this->actingAs($propietario)->get('/admin/bodega')->assertOk();
        $this->actingAs($admin)->get('/admin/bodega')->assertForbidden();
    }

    public function test_registra_entrada_de_alimento_y_crea_stock(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        $propietario->asignarRoles([Rol::PROPIETARIO]);
        app(\App\Support\TenantActual::class)->establecer($tenant->id);

        $this->actingAs($propietario)->post('/admin/bodega', [
            'fecha' => now()->format('Y-m-d'),
            'proveedor' => 'Solla',
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'bultos' => 10,
            'peso_bulto_kg' => 40,
            'lote' => 'LOTE-123',
        ])->assertRedirect();

        $this->assertDatabaseHas('movimientos_alimento', [
            'tipo_movimiento' => 'entrada',
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'cantidad_kg' => 400, // 10 bultos * 40 kg
        ]);

        $this->assertDatabaseHas('stocks_alimento', [
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'cantidad_kg' => 400.0,
        ]);
    }
}
