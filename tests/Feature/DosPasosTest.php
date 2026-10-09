<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DosPasos;
use App\Support\TenantActual;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verificación en dos pasos (TOTP): activar y desactivar, segundo paso en el login web y en la API,
 * códigos vencidos y reutilizados, códigos de recuperación de un solo uso, límite de intentos y permisos.
 */
class DosPasosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'password' => 'clave-segura-123']))->asignarRoles([Rol::PROPIETARIO]);
        $this->trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'password' => 'clave-segura-123']))->asignarRoles([Rol::TRABAJADOR]);
    }

    /** Activa los dos pasos con una clave conocida; devuelve [clave, códigos de recuperación]. */
    private function activar(User $usuario): array
    {
        $clave = Totp::nuevaClave();
        $codigos = DosPasos::activar($usuario, $clave, Totp::codigo($clave, Totp::pasoActual()));

        return [$clave, $codigos];
    }

    /** Código del paso siguiente: el del paso actual ya se usó al activar (no se acepta dos veces). */
    private function codigoSiguiente(string $clave): string
    {
        return Totp::codigo($clave, Totp::pasoActual() + 1);
    }

    private function ingresar(User $usuario)
    {
        return $this->post(route('login'), ['email' => $usuario->email, 'password' => 'clave-segura-123']);
    }

    public function test_activar_guarda_la_clave_cifrada_y_muestra_10_codigos_una_sola_vez(): void
    {
        $this->actingAs($this->trabajador)->get(route('mi-cuenta.dos-pasos.activar'))->assertOk()
            // La clave va en la app de autenticación; aquí solo el código de 6 números
            ->assertSee(__('cuenta.dos_pasos.paso_2'))->assertSee(__('cuenta.dos_pasos.codigo'))
            ->assertSee('<svg', false)->assertHeader('Cache-Control', 'no-store, private');
        $clave = session('dos_pasos_clave_nueva');
        $this->assertSame(32, strlen($clave));

        $this->actingAs($this->trabajador)->post(route('mi-cuenta.dos-pasos.confirmar'), ['codigo' => '000000'])
            ->assertInvalid(['codigo' => 'El código no es válido']);
        $this->assertFalse($this->trabajador->fresh()->tieneDosPasos());

        $this->actingAs($this->trabajador)->post(route('mi-cuenta.dos-pasos.confirmar'), ['codigo' => Totp::codigo($clave, Totp::pasoActual())])
            ->assertRedirect(route('mi-cuenta.dos-pasos.codigos'));

        $usuario = $this->trabajador->fresh();
        $this->assertTrue($usuario->tieneDosPasos());
        $this->assertSame($clave, $usuario->dos_pasos_clave);
        // En la base la clave está cifrada y los códigos solo como hashes
        $fila = DB::table('users')->where('id', $usuario->id)->first();
        $this->assertStringNotContainsString($clave, $fila->dos_pasos_clave);

        $codigos = $this->actingAs($usuario)->get(route('mi-cuenta.dos-pasos.codigos'))->assertOk()->viewData('codigos');
        $this->assertCount(10, $codigos);
        foreach ($codigos as $codigo) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{5}-[a-z0-9]{5}$/', $codigo);
            $this->assertStringNotContainsString($codigo, $fila->dos_pasos_recuperacion);
        }
        // Se muestran una sola vez
        $this->actingAs($usuario)->get(route('mi-cuenta.dos-pasos.codigos'))->assertRedirect(route('mi-cuenta.index'));
    }

    public function test_el_login_web_pide_el_segundo_paso_y_no_entra_sin_un_codigo_valido(): void
    {
        [$clave] = $this->activar($this->trabajador);

        $this->ingresar($this->trabajador)->assertRedirect(route('dos-pasos.create'));
        $this->assertGuest();
        $this->get(route('inicio'))->assertRedirect(route('login'));

        $this->post(route('dos-pasos.store'), ['codigo' => '123456'])->assertInvalid(['codigo']);
        $this->assertGuest();

        $this->post(route('dos-pasos.store'), ['codigo' => $this->codigoSiguiente($clave)])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($this->trabajador);
    }

    public function test_un_codigo_vencido_o_ya_usado_no_sirve(): void
    {
        [$clave] = $this->activar($this->trabajador);

        // De hace 3 pasos (90 segundos): fuera de la tolerancia
        $this->ingresar($this->trabajador);
        $this->post(route('dos-pasos.store'), ['codigo' => Totp::codigo($clave, Totp::pasoActual() - 3)])->assertInvalid(['codigo']);
        $this->assertGuest();

        // Usado una vez, no vuelve a servir
        $codigo = $this->codigoSiguiente($clave);
        $this->post(route('dos-pasos.store'), ['codigo' => $codigo]);
        $this->assertAuthenticatedAs($this->trabajador);
        $this->post(route('logout'));
        $this->ingresar($this->trabajador);
        $this->post(route('dos-pasos.store'), ['codigo' => $codigo])->assertInvalid(['codigo']);
        $this->assertGuest();
    }

    public function test_el_paso_pendiente_vence_a_los_5_minutos(): void
    {
        [$clave] = $this->activar($this->trabajador);
        $this->ingresar($this->trabajador);

        $this->travel(6)->minutes();
        $this->post(route('dos-pasos.store'), ['codigo' => $this->codigoSiguiente($clave)])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get(route('dos-pasos.create'))->assertRedirect(route('login'));
    }

    public function test_los_codigos_de_recuperacion_sirven_una_sola_vez(): void
    {
        [, $codigos] = $this->activar($this->trabajador);

        $this->ingresar($this->trabajador);
        $this->post(route('dos-pasos.store'), ['codigo' => strtoupper($codigos[3])])->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($this->trabajador);
        $this->assertCount(9, $this->trabajador->fresh()->dos_pasos_recuperacion);

        $this->post(route('logout'));
        $this->ingresar($this->trabajador);
        $this->post(route('dos-pasos.store'), ['codigo' => $codigos[3]])->assertInvalid(['codigo']);
        $this->assertGuest();
    }

    public function test_limite_de_intentos_del_segundo_paso(): void
    {
        [$clave] = $this->activar($this->trabajador);
        $this->ingresar($this->trabajador);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('dos-pasos.store'), ['codigo' => '000000'])->assertInvalid(['codigo' => 'El código no es válido']);
        }
        // Ni siquiera el código correcto pasa mientras dura el bloqueo
        $this->post(route('dos-pasos.store'), ['codigo' => $this->codigoSiguiente($clave)])->assertInvalid(['codigo' => 'Demasiados intentos']);
        $this->assertGuest();
        RateLimiter::clear('dos-pasos|'.$this->trabajador->id);
    }

    public function test_desactivar_pide_la_contrasena_y_un_codigo(): void
    {
        [$clave] = $this->activar($this->trabajador);

        $this->actingAs($this->trabajador)->delete(route('mi-cuenta.dos-pasos.desactivar'), ['actual' => 'mala', 'codigo' => $this->codigoSiguiente($clave)])
            ->assertInvalid(['actual']);
        $this->actingAs($this->trabajador)->delete(route('mi-cuenta.dos-pasos.desactivar'), ['actual' => 'clave-segura-123', 'codigo' => '000000'])
            ->assertInvalid(['codigo']);
        $this->assertTrue($this->trabajador->fresh()->tieneDosPasos());

        $this->actingAs($this->trabajador)->delete(route('mi-cuenta.dos-pasos.desactivar'), ['actual' => 'clave-segura-123', 'codigo' => $this->codigoSiguiente($clave)])
            ->assertRedirect(route('mi-cuenta.index'));
        $usuario = $this->trabajador->fresh();
        $this->assertFalse($usuario->tieneDosPasos());
        $this->assertNull($usuario->dos_pasos_clave);
        $this->assertNull($usuario->dos_pasos_recuperacion);

        // Sin los dos pasos se entra solo con la contraseña
        $this->post(route('logout'));
        $this->ingresar($usuario)->assertRedirect(route('inicio'));
        $this->assertAuthenticatedAs($usuario);
    }

    public function test_la_api_exige_el_segundo_paso(): void
    {
        [$clave] = $this->activar($this->trabajador);
        $datos = ['email' => $this->trabajador->email, 'password' => 'clave-segura-123'];

        $this->postJson('/api/login', $datos)->assertUnprocessable()->assertJson(['dos_pasos_requerido' => true])->assertJsonMissing(['token']);
        $this->postJson('/api/login', $datos + ['codigo' => '000000'])->assertUnprocessable()->assertJsonValidationErrors(['codigo']);
        $this->assertSame(0, $this->trabajador->tokens()->count());

        $this->postJson('/api/login', $datos + ['codigo' => $this->codigoSiguiente($clave)])->assertOk()->assertJsonStructure(['token']);
        $this->assertSame(1, $this->trabajador->tokens()->count());

        // Sin los dos pasos, la API sigue igual que antes
        $this->postJson('/api/login', ['email' => $this->propietario->email, 'password' => 'clave-segura-123'])->assertOk()->assertJsonStructure(['token']);
    }

    public function test_la_api_tambien_limita_los_intentos_del_segundo_paso(): void
    {
        [$clave] = $this->activar($this->trabajador);
        $datos = ['email' => $this->trabajador->email, 'password' => 'clave-segura-123'];

        // Sin el límite de ingreso de la ruta (5 por correo), para probar solo el del segundo paso
        $this->withoutMiddleware(ThrottleRequests::class);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', $datos + ['codigo' => '000000'])->assertUnprocessable();
        }
        $this->postJson('/api/login', $datos + ['codigo' => $this->codigoSiguiente($clave)])->assertUnprocessable()
            ->assertJsonPath('errors.codigo.0', fn ($texto) => str_contains($texto, 'Demasiados intentos'));
        RateLimiter::clear('dos-pasos|'.$this->trabajador->id);
    }

    public function test_el_panel_avisa_a_propietario_y_administrador_sin_dos_pasos(): void
    {
        $administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);

        $this->actingAs($this->propietario)->get(route('inicio'))->assertSee(__('cuenta.dos_pasos.aviso_panel'));
        $this->actingAs($administrador)->get(route('inicio'))->assertSee(__('cuenta.dos_pasos.aviso_panel'));
        $this->actingAs($this->trabajador)->get(route('inicio'))->assertDontSee(__('cuenta.dos_pasos.aviso_panel'));

        $this->activar($this->propietario);
        $this->actingAs($this->propietario->fresh())->get(route('inicio'))->assertDontSee(__('cuenta.dos_pasos.aviso_panel'));
    }

    public function test_el_propietario_quita_los_dos_pasos_a_su_personal_y_queda_en_la_bitacora(): void
    {
        $this->activar($this->trabajador);

        $this->actingAs($this->propietario)->get(route('admin.personal.index'))->assertSee(__('cuenta.dos_pasos.quitar'));
        $this->actingAs($this->propietario)->post(route('admin.personal.quitar-dos-pasos', $this->trabajador->id))->assertSessionHasNoErrors();

        $this->assertFalse($this->trabajador->fresh()->tieneDosPasos());
        $registro = Bitacora::where('accion', 'dos_pasos')->sole();
        $this->assertSame($this->trabajador->id, $registro->persona_id);
        $this->assertSame('Verificación en dos pasos quitada a '.$this->trabajador->name, $registro->textoDetalle());

        // Solo el propietario
        $this->activar($this->trabajador->fresh());
        $this->actingAs($this->trabajador)->post(route('admin.personal.quitar-dos-pasos', $this->trabajador->id))->assertForbidden();
    }

    public function test_aislamiento_otra_finca_no_quita_los_dos_pasos(): void
    {
        $this->activar($this->trabajador);
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $duenoOtra = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::PROPIETARIO]);

        $this->actingAs($duenoOtra)->post(route('admin.personal.quitar-dos-pasos', $this->trabajador->id))->assertNotFound();
        $this->assertTrue(TenantActual::sinFiltro(fn () => $this->trabajador->fresh()->tieneDosPasos()));
    }

    public function test_el_comando_quita_los_dos_pasos_del_propietario(): void
    {
        $this->activar($this->propietario);

        $this->artisan('piscicola:quitar-dos-pasos', ['correo' => strtoupper($this->propietario->email)])->assertSuccessful();
        $this->assertFalse($this->propietario->fresh()->tieneDosPasos());
        $this->artisan('piscicola:quitar-dos-pasos', ['correo' => 'nadie@finca.test'])->assertFailed();
    }

    public function test_totp_cumple_los_vectores_del_estandar(): void
    {
        // RFC 6238, clave «12345678901234567890» (en Base32), 6 dígitos
        $clave = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        foreach ([59 => '287082', 1111111109 => '081804', 1234567890 => '005924', 2000000000 => '279037'] as $instante => $esperado) {
            $this->assertSame($esperado, Totp::codigo($clave, Totp::pasoActual($instante)));
        }
        $this->assertSame(Totp::pasoActual(59), Totp::verificar($clave, '287 082', null, 59));
        $this->assertNull(Totp::verificar($clave, '287082', Totp::pasoActual(59), 59));   // ya usado
        $this->assertStringStartsWith('otpauth://totp/', Totp::uri($clave, 'ana@finca.test', 'El SAS Piscícola'));
    }
}
