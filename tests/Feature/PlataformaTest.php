<?php

namespace Tests\Feature;

use App\Models\AdministradorPlataforma;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlataformaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private AdministradorPlataforma $dueno;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'norte']);
        $this->dueno = AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
    }

    private function persona(string $rol, ?Tenant $finca = null, string $clave = 'Clave12345'): User
    {
        $usuario = User::factory()->create(['tenant_id' => ($finca ?? $this->finca)->id, 'password' => $clave]);
        $usuario->asignarRoles([$rol]);

        return $usuario;
    }

    /** Ingreso real por el formulario, con sus dos pasos (TestCase::entrarAPlataforma). */
    private function entrarComoDueno(): void
    {
        $this->entrarAPlataforma();
    }

    private function salirComoDueno(): void
    {
        $this->post(route('plataforma.logout'))->assertRedirect(route('plataforma.login'));
        // En producción cada petición empieza con el guard por defecto («web»); en la prueba se restablece a mano
        $this->app['auth']->shouldUse('web');
    }

    public function test_el_dueno_ingresa_con_su_propio_acceso_y_ve_todas_las_fincas(): void
    {
        $otra = Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'sur']);
        $this->persona(Rol::PROPIETARIO);
        $this->persona(Rol::TRABAJADOR);
        $this->persona(Rol::PROPIETARIO, $otra);

        $this->get(route('plataforma.fincas.index'))->assertRedirect(route('plataforma.login'));

        $this->post(route('plataforma.login'), ['email' => 'dueno@software.test', 'password' => 'mala'])->assertSessionHasErrors('email');
        $this->entrarAPlataforma();
        $this->assertAuthenticatedAs($this->dueno, 'plataforma');
        $this->assertGuest('web');

        $this->get(route('plataforma.fincas.index'))->assertOk()
            ->assertSeeInOrder(['Finca Norte', '2', __('plataforma.fincas.activa')])
            ->assertSeeInOrder(['Finca Sur', '1']);
    }

    public function test_ningun_rol_de_finca_ve_el_panel(): void
    {
        foreach ([Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $usuario = $this->persona($rol);

            $this->actingAs($usuario)->get(route('plataforma.fincas.index'))->assertRedirect(route('plataforma.login'));
            $this->actingAs($usuario)->post(route('plataforma.fincas.suspender', $this->finca))->assertRedirect(route('plataforma.login'));
            // Las credenciales de la finca tampoco sirven en el ingreso de plataforma
            $this->post(route('plataforma.login'), ['email' => $usuario->email, 'password' => 'Clave12345'])->assertSessionHasErrors('email');
        }

        $this->assertNull($this->finca->fresh()->suspendida_at);
    }

    public function test_el_dueno_no_entra_a_los_modulos_de_las_fincas(): void
    {
        $this->entrarComoDueno();

        $this->get(route('inicio'))->assertRedirect(route('login'));
        $this->get(route('admin.lagos.index'))->assertRedirect(route('login'));
        $this->getJson('/api/yo')->assertUnauthorized();
    }

    public function test_una_finca_suspendida_no_entra_y_al_reactivarla_vuelve_a_entrar(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);

        $this->entrarComoDueno();
        $this->post(route('plataforma.fincas.suspender', $this->finca), ['motivo_suspension' => 'Pago pendiente'])
            ->assertSessionHas('estado');
        $this->assertTrue($this->finca->fresh()->estaSuspendida());
        $this->assertSame('Pago pendiente', $this->finca->fresh()->motivo_suspension);
        $this->get(route('plataforma.fincas.index'))->assertSee('Pago pendiente')->assertSee(__('plataforma.fincas.reactivar'));

        $this->salirComoDueno();
        $this->post(route('login'), ['email' => $trabajador->email, 'password' => 'Clave12345'])
            ->assertSessionHasErrors(['email' => __('plataforma.finca_suspendida')]);
        $this->assertGuest('web');

        $this->postJson('/api/login', ['email' => $trabajador->email, 'password' => 'Clave12345'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', __('plataforma.finca_suspendida'));

        $this->entrarComoDueno();
        $this->post(route('plataforma.fincas.reactivar', $this->finca))->assertSessionHas('estado');
        $this->assertFalse($this->finca->fresh()->estaSuspendida());

        $this->salirComoDueno();
        $this->post(route('login'), ['email' => $trabajador->email, 'password' => 'Clave12345'])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($trabajador);
    }

    public function test_suspender_cierra_las_sesiones_abiertas_y_los_tokens_de_la_api(): void
    {
        $propietario = $this->persona(Rol::PROPIETARIO);
        $otraFinca = Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'sur']);
        $ajeno = $this->persona(Rol::PROPIETARIO, $otraFinca);
        $tokenPropio = $propietario->createToken('celular')->plainTextToken;
        $ajeno->createToken('celular');

        $this->actingAs($propietario)->get(route('inicio'))->assertOk();

        $this->finca->forceFill(['suspendida_at' => now()])->save();

        // Sesión web abierta: en el siguiente clic se cierra con el mensaje
        $this->actingAs($propietario)->get(route('inicio'))
            ->assertRedirect(route('login'))->assertSessionHasErrors(['email' => __('plataforma.finca_suspendida')]);
        $this->assertGuest('web');

        // Token de la API: responde 403 con el mensaje
        auth()->forgetGuards();
        $this->withToken($tokenPropio)->getJson('/api/yo')->assertForbidden()->assertJsonPath('message', __('plataforma.finca_suspendida'));
    }

    public function test_suspender_desde_el_panel_revoca_solo_los_tokens_de_esa_finca(): void
    {
        $propietario = $this->persona(Rol::PROPIETARIO);
        $ajeno = $this->persona(Rol::PROPIETARIO, Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'sur']));
        $propietario->createToken('celular');
        $ajeno->createToken('celular');

        $this->entrarComoDueno();
        $this->post(route('plataforma.fincas.suspender', $this->finca));

        $this->assertSame(0, $propietario->tokens()->count());
        $this->assertSame(1, $ajeno->tokens()->count());
    }

    public function test_el_comando_crea_el_acceso_de_plataforma_pidiendo_la_contrasena(): void
    {
        $this->artisan('piscicola:crear-admin-plataforma')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.nombre'), 'Soporte')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.correo'), 'Soporte@Software.test')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.clave'), 'Otra-ClaveLarga123')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.confirmacion'), 'Otra-ClaveLarga123')
            ->assertSuccessful();

        $acceso = AdministradorPlataforma::where('email', 'soporte@software.test')->first();
        $this->assertNotNull($acceso);
        $this->assertTrue(Hash::check('Otra-ClaveLarga123', $acceso->password));
        // No es un usuario de finca ni tiene roles
        $this->assertSame(0, User::withoutGlobalScopes()->where('email', 'soporte@software.test')->count());

        $this->artisan('piscicola:crear-admin-plataforma')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.nombre'), 'Corta')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.correo'), 'corta@software.test')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.clave'), 'corta')
            ->expectsQuestion(__('comandos.crear_admin_plataforma.confirmacion'), 'corta')
            ->assertFailed();
    }
}
