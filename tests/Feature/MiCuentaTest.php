<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mi cuenta: cambiar la contraseña pidiendo la actual; al cambiarla se cierran las demás sesiones,
 * se invalida «recordarme» y se revocan los tokens de la API.
 */
class MiCuentaTest extends TestCase
{
    use RefreshDatabase;

    private User $persona;

    protected function setUp(): void
    {
        parent::setUp();

        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($finca->id);
        $this->persona = tap(User::factory()->create(['tenant_id' => $finca->id, 'email' => 'ana@finca.test', 'password' => 'clave-actual-123']))
            ->asignarRoles([Rol::TRABAJADOR]);
    }

    private function sesion(string $id, int $usuarioId): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $usuarioId, 'ip_address' => '127.0.0.1', 'user_agent' => 'x', 'payload' => 'x', 'last_activity' => time()]);
    }

    public function test_todos_los_roles_ven_mi_cuenta(): void
    {
        foreach (Rol::TODOS as $rol) {
            $usuario = tap(User::factory()->create(['tenant_id' => $this->persona->tenant_id]))->asignarRoles([$rol]);
            $this->actingAs($usuario)->get(route('mi-cuenta.index'))->assertOk()
                ->assertSee(__('cuenta.contrasena.titulo'))->assertSee($usuario->email);
        }
        $this->actingAs($this->persona)->get(route('inicio'))->assertSee(route('mi-cuenta.index'));
    }

    public function test_cambiar_la_contrasena_cierra_las_demas_sesiones_y_revoca_los_tokens(): void
    {
        $otra = tap(User::factory()->create(['tenant_id' => $this->persona->tenant_id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->sesion('sesion-celular-ana', $this->persona->id);
        $this->sesion('sesion-tablet-ana', $this->persona->id);
        $this->sesion('sesion-de-otra', $otra->id);
        $this->persona->createToken('celular');
        $otra->createToken('celular');
        $recordarAntes = $this->persona->fresh()->remember_token;

        $this->actingAs($this->persona)->put(route('mi-cuenta.contrasena'), [
            'actual' => 'clave-actual-123', 'password' => 'clave-nueva-456', 'password_confirmation' => 'clave-nueva-456',
        ])->assertSessionHasNoErrors()->assertSessionHas('estado', __('cuenta.contrasena.cambiada'));

        $persona = $this->persona->fresh();
        $this->assertTrue(Hash::check('clave-nueva-456', $persona->password));
        $this->assertNotSame($recordarAntes, $persona->remember_token);    // «recordarme» deja de servir en otros equipos
        $this->assertSame(0, $persona->tokens()->count());                 // la app móvil se desconecta
        $this->assertSame(0, DB::table('sessions')->where('user_id', $persona->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $otra->id)->count());   // la de otra persona sigue
        $this->assertSame(1, $otra->tokens()->count());
        $this->assertAuthenticatedAs($persona);                            // esta sesión sigue abierta
    }

    public function test_con_la_contrasena_actual_incorrecta_no_cambia_nada(): void
    {
        $this->persona->createToken('celular');

        $this->actingAs($this->persona)->put(route('mi-cuenta.contrasena'), [
            'actual' => 'otra-clave', 'password' => 'clave-nueva-456', 'password_confirmation' => 'clave-nueva-456',
        ])->assertInvalid(['actual' => 'La contraseña actual no es correcta.']);

        $this->assertTrue(Hash::check('clave-actual-123', $this->persona->fresh()->password));
        $this->assertSame(1, $this->persona->tokens()->count());
    }

    public function test_la_regla_de_contrasenas_se_mantiene(): void
    {
        $cambiar = fn (string $nueva) => $this->actingAs($this->persona)->put(route('mi-cuenta.contrasena'), [
            'actual' => 'clave-actual-123', 'password' => $nueva, 'password_confirmation' => $nueva,
        ]);

        $cambiar('corta1')->assertInvalid(['password']);
        $cambiar('ana@finca.test')->assertInvalid(['password']);   // igual al correo
        $this->actingAs($this->persona)->put(route('mi-cuenta.contrasena'), [
            'actual' => 'clave-actual-123', 'password' => 'clave-nueva-456', 'password_confirmation' => 'distinta-789',
        ])->assertInvalid(['password']);

        $this->assertTrue(Hash::check('clave-actual-123', $this->persona->fresh()->password));
    }

    public function test_tiene_limite_de_intentos_por_persona(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->persona)->put(route('mi-cuenta.contrasena'), ['actual' => 'mala', 'password' => 'x', 'password_confirmation' => 'x'])
                ->assertSessionHasErrors();
        }

        $this->actingAs($this->persona)->put(route('mi-cuenta.contrasena'), ['actual' => 'clave-actual-123', 'password' => 'clave-nueva-456', 'password_confirmation' => 'clave-nueva-456'])
            ->assertStatus(429);
    }

    public function test_sin_sesion_no_se_entra(): void
    {
        $this->get(route('mi-cuenta.index'))->assertRedirect(route('login'));
        $this->put(route('mi-cuenta.contrasena'), [])->assertRedirect(route('login'));
    }
}
