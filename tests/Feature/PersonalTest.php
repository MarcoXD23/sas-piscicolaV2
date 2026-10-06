<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonalTest extends TestCase
{
    use RefreshDatabase;

    private function propietario(): User
    {
        return User::factory()->conRoles(Rol::PROPIETARIO)->create();
    }

    public function test_aprueba_y_asigna_varios_roles(): void
    {
        $propietario = $this->propietario();
        $persona = User::factory()->pendiente()->create(['tenant_id' => $propietario->tenant_id]);

        $this->actingAs($propietario)
            ->put(route('admin.personal.update', $persona->id), ['roles' => [Rol::TRABAJADOR, Rol::CELADOR]])
            ->assertRedirect(route('admin.personal.index'));

        $persona->refresh();
        $this->assertNotNull($persona->aprobado_at);
        $this->assertEqualsCanonicalizing([Rol::TRABAJADOR, Rol::CELADOR], $persona->roles->pluck('nombre')->all());
        $this->assertDatabaseHas('rol_user', ['user_id' => $persona->id, 'tenant_id' => $propietario->tenant_id]);
    }

    public function test_reemplaza_roles_existentes(): void
    {
        $propietario = $this->propietario();
        $persona = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $propietario->tenant_id]);

        $this->actingAs($propietario)
            ->put(route('admin.personal.update', $persona->id), ['roles' => [Rol::ADMINISTRADOR]]);

        $this->assertSame([Rol::ADMINISTRADOR], $persona->fresh()->roles->pluck('nombre')->all());
    }

    public function test_no_permite_asignar_rol_propietario(): void
    {
        $propietario = $this->propietario();
        $persona = User::factory()->pendiente()->create(['tenant_id' => $propietario->tenant_id]);

        $this->actingAs($propietario)
            ->put(route('admin.personal.update', $persona->id), ['roles' => [Rol::PROPIETARIO]])
            ->assertSessionHasErrors('roles.0');

        $this->assertFalse($persona->fresh()->tieneRol(Rol::PROPIETARIO));
        $this->assertNull($persona->fresh()->aprobado_at);
    }

    public function test_no_permite_roles_inventados_ni_vacio(): void
    {
        $propietario = $this->propietario();
        $persona = User::factory()->pendiente()->create(['tenant_id' => $propietario->tenant_id]);

        $this->actingAs($propietario)
            ->put(route('admin.personal.update', $persona->id), ['roles' => ['supervisor']])
            ->assertSessionHasErrors('roles.0');

        $this->actingAs($propietario)
            ->put(route('admin.personal.update', $persona->id), ['roles' => []])
            ->assertSessionHasErrors('roles');
    }

    public function test_pantalla_no_muestra_el_rol_propietario_ni_a_propietarios(): void
    {
        $propietario = $this->propietario();
        User::factory()->pendiente()->create(['tenant_id' => $propietario->tenant_id, 'name' => 'Persona Pendiente']);

        $respuesta = $this->actingAs($propietario)->get(route('admin.personal.index'));

        $respuesta->assertOk()
            ->assertSee('Persona Pendiente')
            ->assertSee('value="administrador"', false)
            ->assertSee('value="trabajador"', false)
            ->assertSee('value="celador"', false)
            ->assertDontSee('value="propietario"', false)
            ->assertDontSee($propietario->email);
    }

    public function test_no_puede_editar_a_un_propietario(): void
    {
        $propietario = $this->propietario();
        $otroPropietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $propietario->tenant_id]);

        $this->actingAs($propietario)
            ->put(route('admin.personal.update', $otroPropietario->id), ['roles' => [Rol::TRABAJADOR]])
            ->assertNotFound();

        $this->assertSame([Rol::PROPIETARIO], $otroPropietario->fresh()->roles->pluck('nombre')->all());
    }
}

