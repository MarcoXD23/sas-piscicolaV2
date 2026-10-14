<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ayuda bajo el error de ingreso: no revela si el correo existe (el error es el mismo letra por letra) y lleva a
 * solicitar acceso. Los avisos de finca suspendida y de cuenta sin aprobar solo salen con la contraseña correcta.
 */
class IngresoAyudaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $this->persona = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'password' => 'Clave12345']))->asignarRoles([Rol::TRABAJADOR]);
    }

    /** Error del campo correo tras intentar entrar por la web. */
    private function errorWeb(string $correo, string $clave): string
    {
        $this->from(route('login'))->post(route('login'), ['email' => $correo, 'password' => $clave])->assertRedirect(route('login'));
        // La sesión de las pruebas puede guardar la bolsa de errores ya serializada como arreglo
        $errores = session('errors');
        $error = is_array($errores) ? $errores['default']['messages']['email'][0] : $errores->first('email');
        $this->assertGuest('web');

        return $error;
    }

    public function test_el_error_es_identico_para_un_correo_que_no_existe_y_una_contrasena_equivocada(): void
    {
        $noExiste = $this->errorWeb('nadie@ejemplo.test', 'Clave12345');
        $claveMala = $this->errorWeb($this->persona->email, 'OtraClave99');

        $this->assertSame(__('mensajes.login.correo_contrasena_no_son_correctos'), $noExiste);
        $this->assertSame($noExiste, $claveMala);

        // La API (app del teléfono) responde igual en los dos casos
        $api = fn (string $correo, string $clave) => $this->postJson('/api/login', ['email' => $correo, 'password' => $clave])
            ->assertStatus(422)->json('errors.email.0');
        $this->assertSame($noExiste, $api('nadie@ejemplo.test', 'Clave12345'));
        $this->assertSame($noExiste, $api($this->persona->email, 'OtraClave99'));
    }

    public function test_con_el_error_sale_la_ayuda_con_el_enlace_a_solicitar_acceso(): void
    {
        $this->from(route('login'))->post(route('login'), ['email' => 'nadie@ejemplo.test', 'password' => 'Clave12345']);
        $pagina = $this->get(route('login'))->assertOk();

        $pagina->assertSee('data-ayuda-ingreso', false);
        $pagina->assertSee(__('acceso.ayuda_sin_cuenta'));
        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('registro'), '#').'"[^>]*>'.preg_quote(__('acceso.solicita_acceso_aqui'), '#').'</a>#', $pagina->getContent());
        // Sin REGISTRO_FINCAS_URL no hay enlace para registrar una finca
        $pagina->assertDontSee('data-registra-finca', false);
        $pagina->assertDontSee(__('acceso.registra_tu_finca'));

        // Sin error, no hay ayuda
        $this->get(route('login'))->assertOk()->assertDontSee('data-ayuda-ingreso', false);
    }

    public function test_con_registro_fincas_url_sale_el_enlace_registra_tu_finca(): void
    {
        config(['app.registro_fincas_url' => 'https://ejemplo.test/registra-tu-finca']);

        $this->from(route('login'))->post(route('login'), ['email' => 'nadie@ejemplo.test', 'password' => 'Clave12345']);

        $this->get(route('login'))->assertOk()
            ->assertSee('href="https://ejemplo.test/registra-tu-finca"', false)
            ->assertSee(__('acceso.registra_tu_finca'));
    }

    public function test_el_aviso_de_finca_suspendida_solo_sale_con_la_contrasena_correcta(): void
    {
        $this->finca->forceFill(['suspendida_at' => now()])->save();

        // Contraseña equivocada: el error común y la ayuda, nunca el aviso de suspensión
        $this->assertSame(__('mensajes.login.correo_contrasena_no_son_correctos'), $this->errorWeb($this->persona->email, 'OtraClave99'));
        $this->postJson('/api/login', ['email' => $this->persona->email, 'password' => 'OtraClave99'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', __('mensajes.auth.correo_contrasena_no_son_correctos'));

        // Contraseña correcta: el aviso de suspensión, sin la ayuda
        $this->assertSame(__('plataforma.finca_suspendida'), $this->errorWeb($this->persona->email, 'Clave12345'));
        $this->get(route('login'))->assertOk()->assertSee(__('plataforma.finca_suspendida'))->assertDontSee('data-ayuda-ingreso', false);
    }

    public function test_la_cuenta_sin_aprobar_tampoco_se_revela_sin_la_contrasena_correcta(): void
    {
        $this->persona->forceFill(['aprobado_at' => null])->save();

        $this->assertSame(__('mensajes.login.correo_contrasena_no_son_correctos'), $this->errorWeb($this->persona->email, 'OtraClave99'));
        $this->assertSame(__('mensajes.login.tu_cuenta_aun_no_ha'), $this->errorWeb($this->persona->email, 'Clave12345'));
    }
}
