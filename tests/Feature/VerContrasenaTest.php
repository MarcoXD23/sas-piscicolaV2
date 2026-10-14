<?php

namespace Tests\Feature;

use App\Models\AdministradorPlataforma;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Datos\EliminadorFinca;
use App\Support\DosPasos;
use App\Support\TenantActual;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Botón de ojo para mostrar u ocultar la contraseña (<x-contrasena>, resources/js/contrasena.js): está en cada pantalla
 * con contraseña, con su autocomplete correcto, y no en los códigos de verificación en dos pasos.
 * El cambio de tipo y el volver a ocultar se prueban con node --test (tests/js/contrasena.test.mjs).
 */
class VerContrasenaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id, 'password' => 'clave-segura-123']);
    }

    /**
     * La página trae exactamente esos campos de contraseña, cada uno con su botón de ojo accesible.
     *
     * @param  list<string>  $autocompletes  autocomplete de cada campo, en orden
     */
    private function assertConOjo(string $html, array $autocompletes, string $pantalla): void
    {
        preg_match_all('/<input\b[^>]*\bdata-contrasena\b[^>]*>/', $html, $campos);
        $this->assertCount(count($autocompletes), $campos[0], $pantalla.': campos de contraseña');
        foreach ($campos[0] as $indice => $campo) {
            $this->assertStringContainsString('type="password"', $campo, $pantalla);
            $this->assertStringContainsString('autocomplete="'.$autocompletes[$indice].'"', $campo, $pantalla);
            $this->assertStringNotContainsString('value=', $campo, $pantalla.': nunca devuelve lo escrito');
            preg_match('/id="([^"]+)"/', $campo, $id);
            // Su botón: type="button", controla ese campo, aria-pressed y aria-label en español
            $this->assertMatchesRegularExpression('/<button type="button" data-ver-contrasena aria-controls="'.preg_quote($id[1], '/').'" aria-pressed="false"\s+aria-label="'.preg_quote(__('acceso.mostrar_contrasena'), '/').'"/', $html, $pantalla);
        }
        $this->assertStringContainsString('data-texto-ocultar="'.e(__('acceso.ocultar_contrasena')).'"', $html, $pantalla);
        // En la pantalla no queda ningún campo de contraseña sin el botón
        $this->assertSame(count($autocompletes), preg_match_all('/type="password"/', $html), $pantalla.': contraseñas sin botón');
    }

    private function assertSinOjo(string $html, string $pantalla): void
    {
        $this->assertStringNotContainsString('data-ver-contrasena', $html, $pantalla);
        $this->assertStringNotContainsString('type="password"', $html, $pantalla);
    }

    public function test_ingreso_registro_y_restablecer_contrasena(): void
    {
        $this->assertConOjo($this->get(route('login'))->assertOk()->getContent(), ['current-password'], 'ingreso');
        $this->assertConOjo($this->get(route('registro'))->assertOk()->getContent(), ['new-password', 'new-password'], 'registro (invitación con el código de la finca)');
        $this->assertConOjo($this->get(route('password.reset', ['token' => 'token-de-prueba', 'email' => 'x@finca.test']))->assertOk()->getContent(), ['new-password', 'new-password'], 'restablecer contraseña');
    }

    public function test_ingreso_y_panel_de_plataforma(): void
    {
        $this->assertConOjo($this->get(route('plataforma.login'))->assertOk()->getContent(), ['current-password'], 'ingreso de plataforma');

        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
        $this->entrarAPlataforma();
        // Eliminar una finca pide la contraseña de plataforma (un campo por finca)
        $this->assertConOjo($this->get(route('plataforma.fincas.index'))->assertOk()->getContent(), ['current-password'], 'panel de plataforma');
    }

    public function test_mi_cuenta_y_datos_de_la_finca(): void
    {
        // Cambiar la contraseña: actual, nueva y confirmación
        $this->assertConOjo($this->actingAs($this->propietario)->get(route('mi-cuenta.index'))->assertOk()->getContent(),
            ['current-password', 'new-password', 'new-password'], 'mi cuenta');
        // Con dos pasos activa, quitarla también pide la contraseña
        $clave = Totp::nuevaClave();
        DosPasos::activar($this->propietario, $clave, Totp::codigo($clave, Totp::pasoActual()));
        $this->assertConOjo($this->actingAs($this->propietario->fresh())->get(route('mi-cuenta.index'))->assertOk()->getContent(),
            ['current-password', 'new-password', 'new-password', 'current-password'], 'mi cuenta con dos pasos');

        $this->assertConOjo($this->actingAs($this->propietario)->get(route('admin.datos.index'))->assertOk()->getContent(), ['current-password'], 'datos de la finca');
        EliminadorFinca::solicitar($this->finca, $this->propietario);
        $this->assertConOjo($this->actingAs($this->propietario->fresh())->get(route('admin.datos.eliminacion'))->assertOk()->getContent(), ['current-password'], 'finca en eliminación');
    }

    public function test_no_esta_en_los_codigos_de_dos_pasos(): void
    {
        // Activar en Mi cuenta (código de la app)
        $this->assertSinOjo($this->actingAs($this->propietario)->get(route('mi-cuenta.dos-pasos.activar'))->assertOk()->getContent(), 'activar dos pasos');

        // Segundo paso del ingreso web
        $clave = Totp::nuevaClave();
        DosPasos::activar($this->propietario, $clave, Totp::codigo($clave, Totp::pasoActual()));
        auth()->logout();
        $this->post(route('login'), ['email' => $this->propietario->email, 'password' => 'clave-segura-123'])->assertRedirect(route('dos-pasos.create'));
        $this->assertSinOjo($this->get(route('dos-pasos.create'))->assertOk()->getContent(), 'segundo paso del ingreso');

        // Segundo paso y activar del panel de plataforma
        AdministradorPlataforma::create(['name' => 'Nuevo', 'email' => 'nuevo@software.test', 'password' => 'ClaveLarga12345']);
        $this->post(route('plataforma.login'), ['email' => 'nuevo@software.test', 'password' => 'ClaveLarga12345'])->assertRedirect(route('plataforma.dos-pasos.activar'));
        $this->assertSinOjo($this->get(route('plataforma.dos-pasos.activar'))->assertOk()->getContent(), 'activar dos pasos de plataforma');

        // Las vistas de códigos (segundo paso, activar y códigos de recuperación) no usan el componente
        foreach (['auth/dos-pasos', 'plataforma/dos-pasos', 'plataforma/activar', 'plataforma/codigos', 'mi-cuenta/activar', 'mi-cuenta/codigos'] as $vista) {
            $fuente = (string) file_get_contents(resource_path("views/{$vista}.blade.php"));
            $this->assertStringNotContainsString('x-contrasena', $fuente, $vista);
            $this->assertStringNotContainsString('password', $fuente, $vista);
        }
    }

    public function test_sin_scripts_en_linea_y_con_iconos_propios(): void
    {
        $componente = (string) file_get_contents(resource_path('views/components/contrasena.blade.php'));
        $this->assertStringNotContainsString('<script', $componente);
        $this->assertDoesNotMatchRegularExpression('/\son\w+=/', $componente);   // sin onclick ni parecidos
        $this->assertStringContainsString("import { iniciarVerContrasena } from './contrasena';", (string) file_get_contents(resource_path('js/app.js')));
        // Ojo y ojo tachado, del mismo juego de íconos de trazo
        $iconos = (string) file_get_contents(resource_path('views/components/icono.blade.php'));
        $this->assertStringContainsString("@case('ojo')", $iconos);
        $this->assertStringContainsString("@case('ojo-tachado')", $iconos);
        // Ningún campo de contraseña quedó con el componente de siempre
        foreach (glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE) as $vista) {
            $this->assertStringNotContainsString('tipo="password"', (string) file_get_contents($vista), $vista);
        }
    }
}
