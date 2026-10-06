<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_api_entrega_token_y_roles(): void
    {
        $usuario = User::factory()->conRoles(Rol::TRABAJADOR, Rol::CELADOR)->create(['password' => 'clave-segura-123']);

        $respuesta = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'clave-segura-123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'usuario' => ['id', 'roles']]);

        $this->assertEqualsCanonicalizing([Rol::TRABAJADOR, Rol::CELADOR], $respuesta->json('usuario.roles'));

        $this->withToken($respuesta->json('token'))
            ->getJson('/api/yo')
            ->assertOk()
            ->assertJsonPath('usuario.email', $usuario->email);
    }

    public function test_login_api_rechaza_usuario_pendiente(): void
    {
        $usuario = User::factory()->pendiente()->create(['password' => 'clave-segura-123']);

        $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'clave-segura-123'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_api_sin_token_responde_401(): void
    {
        $this->getJson('/api/yo')->assertUnauthorized();
    }

    public function test_logout_api_revoca_el_token(): void
    {
        $usuario = User::factory()->conRoles(Rol::ADMINISTRADOR)->create();
        $token = $usuario->createToken('prueba')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}

