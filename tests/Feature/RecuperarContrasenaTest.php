<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\RestablecerContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RecuperarContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();
        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $this->usuario = User::factory()->create(['tenant_id' => $finca->id, 'email' => 'ana@ejemplo.test', 'name' => 'Ana']);
        $this->usuario->asignarRoles([Rol::TRABAJADOR]);
    }

    private function restablecer(string $token, string $clave = 'ClaveNueva123'): TestResponse
    {
        return $this->from(route('password.reset', $token))->post(route('password.update'), [
            'token' => $token, 'email' => 'ana@ejemplo.test', 'password' => $clave, 'password_confirmation' => $clave,
        ]);
    }

    public function test_el_login_enlaza_a_recuperar_contrasena(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee(__('acceso.recuperar.titulo'));
    }

    public function test_se_envia_un_correo_con_el_boton_restablecer_contrasena(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'ANA@ejemplo.test'])
            ->assertSessionHas('estado', __('acceso.recuperar.enviado'));

        Notification::assertSentTo($this->usuario, RestablecerContrasena::class, function (RestablecerContrasena $aviso) {
            $correo = $aviso->toMail($this->usuario);
            $html = (string) $correo->render();

            return str_contains($html, 'Restablecer contraseña')
                && str_contains($html, 'copia y pega este enlace')
                && ! str_contains($html, 'Regards')
                && $correo->subject === __('acceso.correo_recuperar.asunto', ['app' => config('app.name')])
                && $correo->actionText === 'Restablecer contraseña'
                && str_contains($correo->actionUrl, '/restablecer-contrasena/'.$aviso->token)
                && in_array(__('acceso.correo_recuperar.vence', ['minutos' => 30]), $correo->outroLines, true);
        });
    }

    public function test_el_mensaje_no_revela_si_el_correo_existe(): void
    {
        Notification::fake();

        $existe = $this->post(route('password.email'), ['email' => 'ana@ejemplo.test']);
        $noExiste = $this->post(route('password.email'), ['email' => 'nadie@ejemplo.test']);
        // Pedido de nuevo enseguida (el sistema no envía otro correo antes de 60 segundos)
        $repetido = $this->post(route('password.email'), ['email' => 'ana@ejemplo.test']);

        foreach ([$existe, $noExiste, $repetido] as $respuesta) {
            $respuesta->assertSessionHasNoErrors()->assertSessionHas('estado', __('acceso.recuperar.enviado'));
        }
        Notification::assertSentToTimes($this->usuario, RestablecerContrasena::class, 1);
    }

    public function test_un_enlace_valido_cambia_la_contrasena_cierra_accesos_y_permite_ingresar(): void
    {
        $this->usuario->createToken('celular');
        $recordar = $this->usuario->remember_token;
        $token = Password::createToken($this->usuario);

        $this->get(route('password.reset', ['token' => $token, 'email' => 'ana@ejemplo.test']))->assertOk()->assertSee('ana@ejemplo.test');
        $this->restablecer($token)->assertRedirect(route('login'))->assertSessionHas('estado', __('acceso.recuperar.restablecida'));

        $this->usuario->refresh();
        $this->assertTrue(Hash::check('ClaveNueva123', $this->usuario->password));
        $this->assertNotSame($recordar, $this->usuario->remember_token);
        $this->assertSame(0, $this->usuario->tokens()->count());

        $this->post(route('login'), ['email' => 'ana@ejemplo.test', 'password' => 'ClaveNueva123'])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($this->usuario);
    }

    public function test_un_enlace_vencido_no_sirve(): void
    {
        $token = Password::createToken($this->usuario);
        $antes = $this->usuario->password;

        $this->travel(31)->minutes();

        $this->restablecer($token)->assertSessionHasErrors(['email' => __('acceso.recuperar.enlace_invalido')]);
        $this->assertSame($antes, $this->usuario->fresh()->password);
    }

    public function test_un_enlace_solo_sirve_una_vez(): void
    {
        $token = Password::createToken($this->usuario);

        $this->restablecer($token, 'PrimeraClave123')->assertSessionHasNoErrors();
        $this->restablecer($token, 'SegundaClave123')->assertSessionHasErrors(['email' => __('acceso.recuperar.enlace_invalido')]);

        $this->assertTrue(Hash::check('PrimeraClave123', $this->usuario->fresh()->password));
    }

    public function test_un_enlace_inventado_o_de_otro_correo_recibe_el_mismo_mensaje(): void
    {
        $this->restablecer('token-inventado')->assertSessionHasErrors(['email' => __('acceso.recuperar.enlace_invalido')]);

        $token = Password::createToken($this->usuario);
        $this->post(route('password.update'), [
            'token' => $token, 'email' => 'nadie@ejemplo.test', 'password' => 'ClaveNueva123', 'password_confirmation' => 'ClaveNueva123',
        ])->assertSessionHasErrors(['email' => __('acceso.recuperar.enlace_invalido')]);
    }

    public function test_hay_limite_de_intentos_para_pedir_enlaces_y_para_restablecer(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "persona{$i}@ejemplo.test"])->assertRedirect();
        }
        $this->post(route('password.email'), ['email' => 'otra@ejemplo.test'])->assertStatus(429);

        for ($i = 0; $i < 5; $i++) {
            $this->restablecer("intento-{$i}")->assertRedirect();
        }
        $this->restablecer('intento-6')->assertStatus(429);

        // Pasado el minuto se puede volver a intentar
        $this->travel(61)->seconds();
        $this->post(route('password.email'), ['email' => 'otra@ejemplo.test'])->assertRedirect();
    }

    public function test_un_usuario_con_sesion_no_ve_la_recuperacion(): void
    {
        $this->actingAs($this->usuario)->get(route('password.request'))->assertRedirect();
    }
}
