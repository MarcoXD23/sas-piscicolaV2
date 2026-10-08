<?php

namespace Tests\Feature;

use App\Http\Controllers\Plataforma\DosPasosController;
use App\Models\AdministradorPlataforma;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DosPasos;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verificación en dos pasos del panel de plataforma: obligatoria, el ingreso pide el código y no se desactiva
 * desde el panel (solo con piscicola:plataforma-quitar-dos-pasos en el servidor).
 */
class PlataformaDosPasosTest extends TestCase
{
    use RefreshDatabase;

    private AdministradorPlataforma $dueno;

    private Tenant $finca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'norte']);
        $this->dueno = AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
    }

    /** Activa la verificación con una clave conocida. @return array{0: string, 1: list<string>} clave y códigos de recuperación */
    private function activar(): array
    {
        $clave = Totp::nuevaClave();
        $codigos = DosPasos::activar($this->dueno, $clave, Totp::codigo($clave, Totp::pasoActual() - 1));

        return [$clave, $codigos];
    }

    private function contrasena(): void
    {
        $this->post(route('plataforma.login'), ['email' => 'dueno@software.test', 'password' => 'ClaveLarga12345'])
            ->assertRedirect(route('plataforma.dos-pasos.create'));
    }

    public function test_sin_dos_pasos_activa_el_panel_obliga_a_activarla(): void
    {
        $this->post(route('plataforma.login'), ['email' => 'dueno@software.test', 'password' => 'ClaveLarga12345'])
            ->assertRedirect(route('plataforma.dos-pasos.activar'));
        $this->assertAuthenticatedAs($this->dueno, 'plataforma');

        // Ni ver ni actuar en el panel hasta activarla
        $this->get(route('plataforma.fincas.index'))->assertRedirect(route('plataforma.dos-pasos.activar'));
        $this->post(route('plataforma.fincas.suspender', $this->finca))->assertRedirect(route('plataforma.dos-pasos.activar'));
        $this->assertNull($this->finca->fresh()->suspendida_at);

        $this->get(route('plataforma.dos-pasos.activar'))->assertOk()
            ->assertSee(__('plataforma.dos_pasos.obligatoria'))
            ->assertSee('<svg', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        // Salir sí se puede
        $this->post(route('plataforma.logout'))->assertRedirect(route('plataforma.login'));
    }

    public function test_activar_con_un_codigo_valido_muestra_los_codigos_una_sola_vez(): void
    {
        $this->post(route('plataforma.login'), ['email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
        $this->get(route('plataforma.dos-pasos.activar'))->assertOk();
        $clave = session('plataforma_dos_pasos_clave_nueva');

        $this->post(route('plataforma.dos-pasos.confirmar'), ['codigo' => '000000'])->assertInvalid(['codigo']);
        $this->assertFalse($this->dueno->fresh()->tieneDosPasos());

        $this->post(route('plataforma.dos-pasos.confirmar'), ['codigo' => Totp::codigo($clave, Totp::pasoActual())])
            ->assertRedirect(route('plataforma.dos-pasos.codigos'));
        $this->assertTrue($this->dueno->fresh()->tieneDosPasos());

        $html = $this->get(route('plataforma.dos-pasos.codigos'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->getContent();
        $this->assertSame(DosPasos::CODIGOS_RECUPERACION, preg_match_all('/<li[^>]*>\s*[a-z0-9]{5}-[a-z0-9]{5}\s*<\/li>/', $html));
        // Una sola vez
        $this->get(route('plataforma.dos-pasos.codigos'))->assertRedirect(route('plataforma.fincas.index'));
        $this->get(route('plataforma.fincas.index'))->assertOk();
    }

    public function test_el_ingreso_pide_el_codigo_y_la_contrasena_sola_no_abre_sesion(): void
    {
        [$clave] = $this->activar();

        $this->contrasena();
        $this->assertGuest('plataforma');
        $this->get(route('plataforma.fincas.index'))->assertRedirect(route('plataforma.login'));

        $this->get(route('plataforma.dos-pasos.create'))->assertOk()->assertSee(__('cuenta.dos_pasos.ingreso_codigo'));
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => '123456'])->assertInvalid(['codigo' => __('cuenta.dos_pasos.codigo_invalido')]);
        $this->assertGuest('plataforma');

        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => Totp::codigo($clave, Totp::pasoActual())])
            ->assertRedirect(route('plataforma.fincas.index'));
        $this->assertAuthenticatedAs($this->dueno, 'plataforma');
        $this->get(route('plataforma.fincas.index'))->assertOk();
    }

    public function test_el_mismo_codigo_no_sirve_dos_veces(): void
    {
        [$clave] = $this->activar();
        $codigo = Totp::codigo($clave, Totp::pasoActual());

        $this->contrasena();
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => $codigo])->assertRedirect(route('plataforma.fincas.index'));
        $this->post(route('plataforma.logout'));
        $this->app['auth']->shouldUse('web');

        $this->contrasena();
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => $codigo])->assertInvalid(['codigo']);
        $this->assertGuest('plataforma');
    }

    public function test_el_pendiente_vence_a_los_5_minutos(): void
    {
        [$clave] = $this->activar();
        $this->contrasena();

        $this->travel(DosPasosController::MINUTOS + 1)->minutes();
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => Totp::codigo($clave, Totp::pasoActual())])
            ->assertRedirect(route('plataforma.login'))->assertSessionHasErrors(['email' => __('cuenta.dos_pasos.vencido')]);
        $this->assertGuest('plataforma');
        $this->get(route('plataforma.dos-pasos.create'))->assertRedirect(route('plataforma.login'));
    }

    public function test_demasiados_intentos_se_bloquean(): void
    {
        [$clave] = $this->activar();
        $this->contrasena();

        for ($i = 0; $i < DosPasosController::INTENTOS; $i++) {
            $this->post(route('plataforma.dos-pasos.store'), ['codigo' => '000000'])->assertInvalid(['codigo']);
        }
        // Ni con el código correcto, hasta que pase el bloqueo
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => Totp::codigo($clave, Totp::pasoActual())])
            ->assertInvalid(['codigo' => __('cuenta.dos_pasos.demasiados', ['segundos' => 60])]);
        $this->assertGuest('plataforma');
    }

    public function test_el_codigo_de_recuperacion_sirve_una_sola_vez(): void
    {
        [, $codigos] = $this->activar();

        $this->contrasena();
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => strtoupper($codigos[0])])->assertRedirect(route('plataforma.fincas.index'));
        $this->assertCount(DosPasos::CODIGOS_RECUPERACION - 1, $this->dueno->fresh()->dos_pasos_recuperacion);
        $this->post(route('plataforma.logout'));
        $this->app['auth']->shouldUse('web');

        $this->contrasena();
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => $codigos[0]])->assertInvalid(['codigo']);
        $this->assertGuest('plataforma');
    }

    public function test_el_comando_la_quita_y_al_entrar_se_exige_otra_vez(): void
    {
        $this->activar();

        $this->artisan('piscicola:plataforma-quitar-dos-pasos', ['correo' => 'DUENO@software.test'])
            ->expectsOutput(__('comandos.plataforma_quitar_dos_pasos.listo', ['correo' => 'dueno@software.test']))->assertSuccessful();
        $this->assertFalse($this->dueno->fresh()->tieneDosPasos());
        $this->assertNull($this->dueno->fresh()->dos_pasos_recuperacion);

        $this->artisan('piscicola:plataforma-quitar-dos-pasos', ['correo' => 'dueno@software.test'])
            ->expectsOutput(__('comandos.plataforma_quitar_dos_pasos.no_tenia', ['correo' => 'dueno@software.test']))->assertSuccessful();
        $this->artisan('piscicola:plataforma-quitar-dos-pasos', ['correo' => 'nadie@software.test'])->assertFailed();

        $this->post(route('plataforma.login'), ['email' => 'dueno@software.test', 'password' => 'ClaveLarga12345'])
            ->assertRedirect(route('plataforma.dos-pasos.activar'));
    }

    public function test_el_ingreso_y_los_dos_pasos_de_plataforma_y_de_las_fincas_no_se_mezclan(): void
    {
        $persona = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'email' => 'propietario@norte.test', 'password' => 'Clave12345']))
            ->asignarRoles([Rol::PROPIETARIO]);
        $clavePersona = Totp::nuevaClave();
        DosPasos::activar($persona, $clavePersona, Totp::codigo($clavePersona, Totp::pasoActual() - 1));
        [$claveDueno] = $this->activar();

        // El pendiente de la finca no sirve en el segundo paso del panel
        $this->post(route('login'), ['email' => 'propietario@norte.test', 'password' => 'Clave12345'])->assertRedirect(route('dos-pasos.create'));
        $this->get(route('plataforma.dos-pasos.create'))->assertRedirect(route('plataforma.login'));
        $this->post(route('plataforma.dos-pasos.store'), ['codigo' => Totp::codigo($clavePersona, Totp::pasoActual())])->assertRedirect(route('plataforma.login'));
        $this->assertGuest('plataforma');

        // Ni el del panel en el de la finca
        $this->flushSession();
        $this->contrasena();
        $this->get(route('dos-pasos.create'))->assertRedirect(route('login'));
        $this->post(route('dos-pasos.store'), ['codigo' => Totp::codigo($claveDueno, Totp::pasoActual())])->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertGuest('plataforma');
    }

    public function test_la_clave_queda_cifrada_y_los_codigos_de_recuperacion_solo_como_hash(): void
    {
        [$clave, $codigos] = $this->activar();

        $fila = DB::table('administradores_plataforma')->where('id', $this->dueno->id)->first();
        $this->assertStringNotContainsString($clave, $fila->dos_pasos_clave);
        $this->assertSame($clave, decrypt($fila->dos_pasos_clave, false));
        foreach ($codigos as $codigo) {
            $this->assertStringNotContainsString($codigo, $fila->dos_pasos_recuperacion);
        }
        // Nunca viajan al convertir el modelo
        $this->assertArrayNotHasKey('dos_pasos_clave', $this->dueno->fresh()->toArray());
        $this->assertArrayNotHasKey('dos_pasos_recuperacion', $this->dueno->fresh()->toArray());
    }
}
