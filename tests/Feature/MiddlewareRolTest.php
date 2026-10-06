<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiddlewareRolTest extends TestCase
{
    use RefreshDatabase;

    public function test_existen_solo_los_cuatro_roles(): void
    {
        $this->assertEqualsCanonicalizing(Rol::TODOS, Rol::query()->pluck('nombre')->all());
    }

    public function test_propietario_entra_a_personal(): void
    {
        $propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create();

        $this->actingAs($propietario)->get(route('admin.personal.index'))->assertOk();
    }

    public function test_otros_roles_no_entran_a_personal(): void
    {
        foreach ([Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $usuario = User::factory()->conRoles($rol)->create();

            $this->actingAs($usuario)->get(route('admin.personal.index'))->assertForbidden();
        }
    }

    public function test_usuario_con_varios_roles_pasa_si_tiene_uno_permitido(): void
    {
        $usuario = User::factory()->conRoles(Rol::TRABAJADOR, Rol::CELADOR)->create();

        $this->assertTrue($usuario->tieneRol(Rol::CELADOR));
        $this->assertTrue($usuario->tieneRol(Rol::PROPIETARIO, Rol::TRABAJADOR));
        $this->assertFalse($usuario->tieneRol(Rol::PROPIETARIO, Rol::ADMINISTRADOR));
    }

    public function test_enlace_personal_solo_visible_para_propietario(): void
    {
        $propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create();
        $administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create();

        $this->actingAs($propietario)->get(route('inicio'))->assertSee(route('admin.personal.index'));
        $this->actingAs($administrador)->get(route('inicio'))->assertDontSee(route('admin.personal.index'));
    }
}

