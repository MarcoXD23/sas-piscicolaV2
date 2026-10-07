<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_muestra_la_pantalla_de_login(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Ingresar');
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get(route('inicio'))->assertRedirect(route('login'));
    }

    public function test_usuario_aprobado_inicia_sesion(): void
    {
        $usuario = User::factory()->conRoles(Rol::TRABAJADOR)->create(['password' => 'clave-segura-123']);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'clave-segura-123'])
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_usuario_pendiente_no_puede_iniciar_sesion(): void
    {
        $usuario = User::factory()->pendiente()->create(['password' => 'clave-segura-123']);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'clave-segura-123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_contrasena_incorrecta_no_inicia_sesion(): void
    {
        $usuario = User::factory()->create(['password' => 'clave-segura-123']);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'otra-clave'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registro_crea_usuario_pendiente_sin_roles_en_la_finca(): void
    {
        $finca = Tenant::factory()->create(['codigo' => 'finca-prueba']);

        $this->post(route('registro'), [
            'codigo_finca' => 'finca-prueba',
            'name' => 'Persona Nueva',
            'email' => 'nueva@example.test',
            'password' => 'clave-segura-123',
            'password_confirmation' => 'clave-segura-123',
        ])->assertRedirect(route('login'));

        $usuario = User::query()->where('email', 'nueva@example.test')->firstOrFail();
        $this->assertSame($finca->id, $usuario->tenant_id);
        $this->assertNull($usuario->aprobado_at);
        $this->assertCount(0, $usuario->roles);
        $this->assertGuest();
    }

    public function test_registro_rechaza_codigo_de_finca_inexistente(): void
    {
        $this->post(route('registro'), [
            'codigo_finca' => 'no-existe',
            'name' => 'Persona',
            'email' => 'persona@example.test',
            'password' => 'clave-segura-123',
            'password_confirmation' => 'clave-segura-123',
        ])->assertSessionHasErrors(['registro' => __('mensajes.registro.no_se_pudo')]);   // mensaje genérico: no revela qué códigos existen

        $this->assertDatabaseCount('users', 0);
    }

    public function test_cerrar_sesion(): void
    {
        $usuario = User::factory()->conRoles(Rol::TRABAJADOR)->create();

        $this->actingAs($usuario)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
