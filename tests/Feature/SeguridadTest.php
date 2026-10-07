<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\Asistente\ClienteAsistente;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Correcciones de la auditoría de seguridad (Fase 24): una prueba por cada una.
 */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->finca->id);
    }

    private function persona(string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->finca->id, 'password' => 'clave-segura-1']))->asignarRoles($roles);
    }

    // #1 Errores en producción

    public function test_en_produccion_un_error_no_muestra_detalles_y_avisa_si_app_debug_esta_activo(): void
    {
        Route::get('/prueba-error-interno', fn () => throw new \RuntimeException('SQL secreto de la finca'));
        $this->app['env'] = 'production';
        config(['app.debug' => false]);

        $this->get('/prueba-error-interno')->assertStatus(500)->assertDontSee('SQL secreto de la finca')->assertDontSee('RuntimeException');

        Log::spy();
        config(['app.debug' => true]);
        (new AppServiceProvider($this->app))->boot();
        Log::shouldHaveReceived('critical')->withArgs(fn ($mensaje) => str_contains($mensaje, 'APP_DEBUG'))->once();

        $ejemplo = file_get_contents(base_path('.env.example'));
        $this->assertStringContainsString("APP_DEBUG=false\n", $ejemplo);
        $this->assertStringContainsString("LOG_LEVEL=warning\n", $ejemplo);
    }

    // #2 Usuario de base de datos sin superusuario

    public function test_el_comando_imprime_el_sql_de_los_dos_roles_sin_contrasenas(): void
    {
        $this->artisan('piscicola:usuario-base')
            ->expectsOutputToContain("CREATE ROLE sas_piscicola_dueno LOGIN PASSWORD :'clave_dueno' NOSUPERUSER")
            ->expectsOutputToContain("CREATE ROLE sas_piscicola_app LOGIN PASSWORD :'clave_app' NOSUPERUSER")
            ->expectsOutputToContain('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO sas_piscicola_app')
            ->expectsOutputToContain('REVOKE CREATE ON SCHEMA public FROM PUBLIC')
            ->expectsOutputToContain('REVOKE UPDATE, DELETE, TRUNCATE ON public.bitacora FROM sas_piscicola_app')
            ->expectsOutputToContain('ALTER DEFAULT PRIVILEGES FOR ROLE sas_piscicola_dueno')
            ->assertSuccessful();

        // Nombres raros (inyección en el SQL) se rechazan
        $this->artisan('piscicola:usuario-base', ['--app' => 'app; DROP TABLE users'])->assertFailed();
        $this->artisan('piscicola:usuario-base', ['--app' => 'igual', '--dueno' => 'igual'])->assertFailed();
    }

    // #3 Límites de intentos con contadores separados

    public function test_el_limite_del_ingreso_es_por_correo_aunque_cambie_la_ip(): void
    {
        $usuario = $this->persona(Rol::TRABAJADOR);
        $intento = fn (string $ip) => $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->post(route('login'), ['email' => $usuario->email, 'password' => 'mala']);

        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3', '10.0.0.4', '10.0.0.5'] as $ip) {
            $intento($ip)->assertSessionHasErrors('email');
        }
        $intento('10.0.0.6')->assertStatus(429);
    }

    public function test_un_bloqueo_por_un_correo_no_bloquea_a_los_demas_de_la_misma_ip(): void
    {
        $intruso = $this->persona(Rol::TRABAJADOR);
        $companero = $this->persona(Rol::TRABAJADOR);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), ['email' => $intruso->email, 'password' => 'mala']);
        }
        // El compañero en la misma red de la finca sí puede ingresar
        $this->post(route('login'), ['email' => $companero->email, 'password' => 'clave-segura-1'])->assertRedirect(route('inicio'));
    }

    public function test_el_registro_y_el_ingreso_tienen_contadores_separados(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('registro'), ['codigo_finca' => 'x', 'name' => 'x', 'email' => "x{$i}@x.test", 'password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1']);
        }
        $this->post(route('registro'), [])->assertStatus(429);

        $usuario = $this->persona(Rol::TRABAJADOR);
        $this->post(route('login'), ['email' => $usuario->email, 'password' => 'clave-segura-1'])->assertRedirect(route('inicio'));
    }

    // #4 Cabeceras de seguridad

    public function test_las_respuestas_llevan_las_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->get(route('login'))->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
        $respuesta->assertHeaderMissing('Strict-Transport-Security');   // sin HTTPS no se envía HSTS

        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->postJson('/api/login', [])->assertHeader('X-Frame-Options', 'DENY');
    }

    // #5 Cookie de sesión segura con HTTPS

    public function test_con_https_la_cookie_de_sesion_es_segura(): void
    {
        config(['session.secure' => null]);
        $this->app->instance('request', Request::create('https://localhost/login'));
        (new AppServiceProvider($this->app))->boot();
        $this->assertTrue(config('session.secure'));

        config(['session.secure' => null]);
        $this->app->instance('request', Request::create('http://localhost/login'));
        (new AppServiceProvider($this->app))->boot();
        $this->assertNull(config('session.secure'));
    }

    // #6 Topes numéricos

    public function test_valores_enormes_reciben_un_mensaje_y_no_un_error_500(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create(['lago_id' => $lago->id, 'especie' => 'mojarra roja', 'fecha' => '2026-09-01', 'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 100, 'peso_actual_g' => 100, 'biomasa_kg' => 10]);
        $hoy = '2026-10-15';

        $casos = [
            [route('admin.siembras.store', $lago), ['especie' => 'cachama blanca', 'fecha' => $hoy, 'cantidad_alevinos' => 99999999999, 'peso_promedio_inicial_g' => 5], 'cantidad_alevinos'],
            [route('admin.siembras.store', $lago), ['especie' => 'cachama blanca', 'fecha' => $hoy, 'cantidad_alevinos' => 10, 'peso_promedio_inicial_g' => 99999999], 'peso_promedio_inicial_g'],
            [route('admin.bodega.recepciones.store'), ['fecha' => $hoy, 'proveedor' => 'P', 'tipo_concentrado' => 'E', 'lote' => 'L', 'bultos' => 999999999, 'peso_bulto_kg' => 40], 'bultos'],
            [route('admin.muestreos.store', $siembra), ['fecha' => $hoy, 'peces_pesados' => 10, 'peso_total_g' => 99999999], 'peso_total_g'],
            [route('admin.raciones.store', $lago), ['fecha' => $hoy, 'tipo_concentrado' => 'E', 'cantidad_kg' => 99999999], 'cantidad_kg'],
            [route('admin.cosechas.store', $siembra), ['fecha' => $hoy, 'peces' => 1, 'kg' => 9999999], 'kg'],
        ];

        foreach ($casos as [$url, $datos, $campo]) {
            $this->actingAs($administrador)->post($url, $datos)->assertSessionHasErrors($campo);
        }
    }

    // #7 Registro sin revelar códigos ni correos

    public function test_el_registro_da_el_mismo_mensaje_con_codigo_inexistente_o_correo_usado_y_ofrece_recuperar(): void
    {
        $existente = $this->persona(Rol::TRABAJADOR);
        $datos = ['name' => 'Nuevo', 'password' => 'clave-segura-1', 'password_confirmation' => 'clave-segura-1'];

        $codigoMalo = $this->post(route('registro'), $datos + ['codigo_finca' => 'no-existe', 'email' => 'nuevo@x.test']);
        $correoUsado = $this->post(route('registro'), $datos + ['codigo_finca' => 'F01', 'email' => mb_strtoupper($existente->email)]);

        foreach ([$codigoMalo, $correoUsado] as $respuesta) {
            $respuesta->assertSessionHasErrors(['registro' => __('mensajes.registro.no_se_pudo')]);
            $respuesta->assertSessionDoesntHaveErrors(['codigo_finca', 'email']);
        }

        // La pantalla de registro muestra el mensaje con el enlace a «¿Olvidaste tu contraseña?»
        $this->from(route('registro'))->followingRedirects()
            ->post(route('registro'), $datos + ['codigo_finca' => 'no-existe', 'email' => 'otro@x.test'])
            ->assertSee(__('mensajes.registro.no_se_pudo'))->assertSee(route('password.request'), false);
        $this->assertSame(1, User::withoutGlobalScopes()->count());
    }

    // #8 Tokens de la API que vencen

    public function test_los_tokens_de_la_api_vencen(): void
    {
        $usuario = $this->persona(Rol::TRABAJADOR);
        $token = $usuario->createToken('celular')->plainTextToken;

        $this->withToken($token)->getJson('/api/yo')->assertOk();
        $this->travel(31)->days();
        auth()->forgetGuards();
        $this->withToken($token)->getJson('/api/yo')->assertUnauthorized();
    }

    // #9 Tope diario del asistente

    public function test_el_asistente_tiene_tope_diario_por_persona_y_por_finca(): void
    {
        config(['services.asistente.limite_diario_persona' => 2, 'services.asistente.limite_diario_finca' => 3]);
        $this->app->bind(ClienteAsistente::class, fn () => new class implements ClienteAsistente
        {
            public function configurado(): bool
            {
                return true;
            }

            public function responder(string $instrucciones, string $contexto, array $mensajes): string
            {
                return 'ok';
            }
        });
        [$ana, $beto] = [$this->persona(Rol::PROPIETARIO), $this->persona(Rol::ADMINISTRADOR)];
        $preguntar = fn (User $quien) => $this->actingAs($quien)->postJson(route('asistente.enviar'), ['mensaje' => 'Hola']);

        $preguntar($ana)->assertOk();
        $preguntar($ana)->assertOk();
        $preguntar($ana)->assertStatus(429);   // tope por persona
        $preguntar($beto)->assertOk();
        $preguntar($beto)->assertStatus(429);  // tope de la finca (3)

        $this->travel(1)->days();
        $preguntar($ana)->assertOk();          // al día siguiente se renueva
    }

    // #10 Contraseña distinta del correo

    public function test_la_contrasena_no_puede_ser_el_correo(): void
    {
        $this->post(route('registro'), ['codigo_finca' => 'F01', 'name' => 'Ana', 'email' => 'ana.perez@x.test',
            'password' => 'ANA.PEREZ@x.test', 'password_confirmation' => 'ANA.PEREZ@x.test'])
            ->assertSessionHasErrors(['password' => __('mensajes.contrasena.igual_al_correo')]);
        $this->post(route('registro'), ['codigo_finca' => 'F01', 'name' => 'Ana', 'email' => 'anaperez1@x.test',
            'password' => 'anaperez1', 'password_confirmation' => 'anaperez1'])
            ->assertSessionHasErrors(['password' => __('mensajes.contrasena.igual_al_correo')]);
        $this->post(route('registro'), ['codigo_finca' => 'F01', 'name' => 'Ana', 'email' => 'ana@x.test',
            'password' => 'otra-clave-9', 'password_confirmation' => 'otra-clave-9'])->assertSessionHasNoErrors();
    }
}
