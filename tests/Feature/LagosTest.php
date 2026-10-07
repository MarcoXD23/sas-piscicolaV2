<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LagosTest extends TestCase
{
    use RefreshDatabase;

    public function test_propietario_y_administrador_pueden_ver_lagos(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);

        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->asignarRoles([Rol::ADMINISTRADOR]);

        $trabajador = User::factory()->create(['tenant_id' => $tenant->id]);
        $trabajador->asignarRoles([Rol::TRABAJADOR]);

        $celador = User::factory()->create(['tenant_id' => $tenant->id]);
        $celador->asignarRoles([Rol::CELADOR]);

        $this->actingAs($propietario)->get('/admin/lagos')->assertOk();
        $this->actingAs($admin)->get('/admin/lagos')->assertOk();
        $this->actingAs($trabajador)->get('/admin/lagos')->assertOk();
        $this->actingAs($celador)->get('/admin/lagos')->assertForbidden();
    }

    public function test_puede_crear_un_lago_con_tipos_validos(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);

        $this->actingAs($propietario)->post('/admin/lagos', [
            'nombre' => 'L01',
            'tipo' => 'geomembrana',
        ])->assertRedirect('/admin/lagos');

        $this->assertDatabaseHas('lagos', [
            'tenant_id' => $tenant->id,
            'nombre' => 'L01',
            'tipo' => 'geomembrana',
        ]);
    }

    public function test_rechaza_tipo_invalido(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $propietario = User::factory()->create(['tenant_id' => $tenant->id]);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);

        $this->actingAs($propietario)->post('/admin/lagos', [
            'nombre' => 'L02',
            'tipo' => 'piscina', // inválido
        ])->assertInvalid(['tipo']);
    }

    public function test_aislamiento_de_lago_por_tenant(): void
    {
        $tenant1 = Tenant::create(['nombre' => 'F1', 'codigo' => 'F1']);
        $tenant2 = Tenant::create(['nombre' => 'F2', 'codigo' => 'F2']);

        app(TenantActual::class)->establecer($tenant1->id);
        Lago::create(['nombre' => 'Lago Unico', 'tipo' => 'tierra']);

        app(TenantActual::class)->establecer($tenant2->id);

        // Debería poder crear otro "Lago Unico" en la finca 2
        $lago2 = Lago::create(['nombre' => 'Lago Unico', 'tipo' => 'tierra']);
        $this->assertEquals($tenant2->id, $lago2->tenant_id);

        // Pero no dos en la misma
        $this->expectException(QueryException::class);
        Lago::create(['nombre' => 'Lago Unico', 'tipo' => 'tierra']);
    }
}
