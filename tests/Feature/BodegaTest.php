<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodegaTest extends TestCase
{
    use RefreshDatabase;

    private function persona(Tenant $tenant, string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles($roles);
    }

    public function test_propietario_y_administrador_ven_la_bodega_y_el_personal_de_campo_no(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);

        $this->actingAs($this->persona($tenant, Rol::PROPIETARIO))->get('/admin/bodega')->assertOk();
        $this->actingAs($this->persona($tenant, Rol::ADMINISTRADOR))->get('/admin/bodega')->assertOk();
        $this->actingAs($this->persona($tenant, Rol::TRABAJADOR))->get('/admin/bodega')->assertForbidden();
        $this->actingAs($this->persona($tenant, Rol::CELADOR))->get('/admin/bodega')->assertForbidden();
    }

    public function test_el_administrador_registra_la_recepcion_y_crea_stock(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $datos = [
            'fecha' => now()->format('Y-m-d'),
            'proveedor' => 'Proveedor Uno',
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'bultos' => 10,
            'peso_bulto_kg' => 40,
            'lote' => 'LOTE-123',
        ];

        // El propietario despacha; la recepción es del administrador
        $this->actingAs($this->persona($tenant, Rol::PROPIETARIO))->post(route('admin.bodega.recepciones.store'), $datos)->assertForbidden();
        $this->actingAs($this->persona($tenant, Rol::ADMINISTRADOR))->post(route('admin.bodega.recepciones.store'), $datos)->assertRedirect();

        $this->assertDatabaseHas('movimientos_alimento', [
            'tipo_movimiento' => 'entrada',
            'tipo_concentrado' => 'Mojarra 32% Engorde',
            'cantidad_kg' => 400, // 10 bultos × 40 kg
            'despacho_id' => null,
        ]);
        $this->assertDatabaseHas('stocks_alimento', ['tipo_concentrado' => 'Mojarra 32% Engorde', 'cantidad_kg' => 400.0]);
    }
}
