<?php

namespace Tests\Feature;

use App\Exceptions\SinFincaActiva;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\TokenAcceso;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * El filtro por tenant_id falla cerrado: sin finca activa, consultar o crear un modelo de finca lanza SinFincaActiva.
 * La identidad (login, sesión, recuperar contraseña, tokens de la API) se busca sin filtro y sigue funcionando.
 */
class FiltroFincaCerradoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $norte;

    private Tenant $sur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->norte = Tenant::factory()->create();
        $this->sur = Tenant::factory()->create();
        Lago::forceCreate(['tenant_id' => $this->norte->id, 'nombre' => 'N1', 'tipo' => 'tierra']);
        Lago::forceCreate(['tenant_id' => $this->sur->id, 'nombre' => 'S1', 'tipo' => 'tierra']);
    }

    public function test_sin_finca_activa_las_consultas_fallan_en_vez_de_devolver_todas_las_fincas(): void
    {
        $this->assertFalse(app(TenantActual::class)->definido());

        foreach ([fn () => Lago::query()->get(), fn () => Lago::count(), fn () => User::query()->first(), fn () => Lago::find(1)] as $consulta) {
            try {
                $consulta();
                $this->fail('La consulta sin finca activa debió fallar');
            } catch (SinFincaActiva $error) {
                $this->assertStringContainsString('sin finca activa', $error->getMessage());
            }
        }
    }

    public function test_sin_finca_activa_no_se_crea_sin_tenant_id_explicito(): void
    {
        $this->expectException(SinFincaActiva::class);

        Lago::create(['nombre' => 'Huérfano', 'tipo' => 'tierra']);
    }

    public function test_con_finca_activa_solo_ve_y_crea_en_su_finca(): void
    {
        app(TenantActual::class)->establecer($this->norte->id);

        $this->assertSame(['N1'], Lago::query()->pluck('nombre')->all());
        $this->assertSame($this->norte->id, Lago::create(['nombre' => 'N2', 'tipo' => 'tierra'])->tenant_id);
    }

    public function test_sin_filtro_ve_todas_las_fincas_solo_dentro_del_bloque(): void
    {
        $this->assertSame(['N1', 'S1'], TenantActual::sinFiltro(fn () => Lago::query()->orderBy('nombre')->pluck('nombre')->all()));

        // Aun si el bloque falla, el filtro vuelve a cerrarse
        try {
            TenantActual::sinFiltro(fn () => throw new RuntimeException('falla dentro'));
        } catch (RuntimeException) {
        }

        $this->assertFalse(app(TenantActual::class)->filtroDesactivado());
        $this->expectException(SinFincaActiva::class);
        Lago::count();
    }

    public function test_login_web_y_la_sesion_siguen_funcionando(): void
    {
        $usuario = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->norte->id, 'password' => 'clave-segura-123']);

        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'clave-segura-123'])->assertRedirect(route('inicio'));

        // Siguiente petición: el usuario se recupera de la sesión (por id, sin finca activa todavía)
        $this->app['auth']->forgetGuards();
        $this->get(route('inicio'))->assertOk();
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_recuperar_contrasena_sigue_funcionando(): void
    {
        Notification::fake();
        $usuario = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->sur->id]);

        $this->post(route('password.email'), ['email' => $usuario->email])->assertSessionHasNoErrors();

        $token = null;
        Notification::assertSentTo($usuario, RestablecerContrasena::class, function ($aviso) use (&$token) {
            $token = $aviso->token;

            return true;
        });

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $usuario->email,
            'password' => 'clave-nueva-456',
            'password_confirmation' => 'clave-nueva-456',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('clave-nueva-456', $usuario->fresh()->password));
    }

    public function test_la_api_inicia_sesion_y_reconoce_el_token(): void
    {
        $usuario = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->sur->id, 'password' => 'clave-segura-123']);

        $token = $this->postJson('/api/login', ['email' => $usuario->email, 'password' => 'clave-segura-123'])
            ->assertOk()->json('token');

        $this->assertInstanceOf(TokenAcceso::class, $usuario->tokens()->first());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/yo')
            ->assertOk()
            ->assertJsonPath('usuario.tenant_id', $this->sur->id);
    }
}
