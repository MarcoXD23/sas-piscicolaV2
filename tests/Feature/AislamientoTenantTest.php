<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AislamientoTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_scope_filtra_por_el_tenant_actual(): void
    {
        $fincaA = Tenant::factory()->create();
        $fincaB = Tenant::factory()->create();
        User::factory()->count(2)->create(['tenant_id' => $fincaA->id]);
        User::factory()->count(3)->create(['tenant_id' => $fincaB->id]);

        app(TenantActual::class)->establecer($fincaA->id);

        $this->assertSame(2, User::count());
        $this->assertTrue(User::all()->every(fn (User $u) => $u->tenant_id === $fincaA->id));
    }

    public function test_al_crear_se_asigna_el_tenant_actual(): void
    {
        $finca = Tenant::factory()->create();
        app(TenantActual::class)->establecer($finca->id);

        $usuario = new User(['name' => 'Persona', 'email' => 'persona@example.test', 'password' => 'clave-segura-123']);
        $usuario->save();

        $this->assertSame($finca->id, $usuario->fresh()->tenant_id);
    }

    public function test_propietario_no_ve_personal_de_otra_finca(): void
    {
        $propietarioA = User::factory()->conRoles(Rol::PROPIETARIO)->create();
        $trabajadorA = User::factory()->pendiente()->create(['tenant_id' => $propietarioA->tenant_id, 'name' => 'Trabajador De A']);
        $trabajadorB = User::factory()->pendiente()->create(['name' => 'Trabajador De B']);

        $this->actingAs($propietarioA)
            ->get(route('admin.personal.index'))
            ->assertOk()
            ->assertSee($trabajadorA->name)
            ->assertDontSee($trabajadorB->name);
    }

    public function test_propietario_no_puede_modificar_personal_de_otra_finca(): void
    {
        $propietarioA = User::factory()->conRoles(Rol::PROPIETARIO)->create();
        $trabajadorB = User::factory()->pendiente()->create();

        $this->actingAs($propietarioA)
            ->put(route('admin.personal.update', $trabajadorB->id), ['roles' => [Rol::TRABAJADOR]])
            ->assertNotFound();

        $this->assertNull($trabajadorB->fresh()->aprobado_at);
        $this->assertCount(0, $trabajadorB->fresh()->roles);
    }

    public function test_api_solo_devuelve_datos_del_propio_tenant(): void
    {
        $usuarioA = User::factory()->conRoles(Rol::ADMINISTRADOR)->create();
        User::factory()->create();

        $this->actingAs($usuarioA, 'sanctum')
            ->getJson('/api/yo')
            ->assertOk()
            ->assertJsonPath('usuario.tenant_id', $usuarioA->tenant_id);
    }
}

