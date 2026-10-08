<?php

namespace Tests\Feature;

use App\Models\AdministradorPlataforma;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AvisoSalud;
use App\Support\Salud\Comprobaciones;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Estado del sistema: /salud (solo «ok» o «falla»), piscicola:estado (detalle) y piscicola:vigilar (aviso por correo
 * una sola vez mientras siga fallando).
 */
class SaludTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        Comprobaciones::latido();   // el programador acaba de correr
    }

    /** Conexión de base de datos que no responde (puerto cerrado). */
    private function romperBase(): void
    {
        config(['database.connections.rota' => ['port' => 1, 'connect_timeout' => 2] + config('database.connections.pgsql'), 'salud.conexion' => 'rota']);
    }

    /** Disco que no deja escribir. */
    private function romperAlmacenamiento(): void
    {
        $roto = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $roto->shouldReceive('put')->andThrow(new RuntimeException('disco lleno'));
        $roto->shouldReceive('exists')->andReturn(false);
        Storage::set('roto', $roto);
        config(['salud.disco' => 'roto']);
    }

    public function test_salud_sano_responde_ok_sin_detalles(): void
    {
        $respuesta = $this->get('/salud')->assertOk();

        $this->assertSame('ok', $respuesta->getContent());
        $this->assertStringStartsWith('text/plain', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'));
    }

    public function test_cada_falla_simulada_responde_falla_sin_decir_cual(): void
    {
        foreach ([
            'base' => fn () => $this->romperBase(),
            'almacenamiento' => fn () => $this->romperAlmacenamiento(),
            'programador' => fn () => $this->travel(61)->minutes(),
        ] as $nombre => $romper) {
            $this->refreshApplication();
            $this->setUp();
            $romper();

            $this->assertFalse(Comprobaciones::correr()[$nombre], $nombre);
            $respuesta = $this->get('/salud')->assertStatus(503);
            $this->assertSame('falla', $respuesta->getContent(), $nombre);
        }
    }

    public function test_la_ruta_no_revela_informacion_de_la_app_ni_de_las_fincas(): void
    {
        $finca = Tenant::create(['nombre' => 'Finca Secreta', 'codigo' => 'SECRETA']);
        app(TenantActual::class)->establecer($finca->id);
        User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $finca->id, 'email' => 'dueno@secreta.test']);
        Lago::create(['nombre' => 'LagoSecreto', 'tipo' => 'tierra']);
        app(TenantActual::class)->establecer(null);

        $html = $this->get('/salud')->getContent();
        $this->assertSame('ok', $html);
        foreach (['Finca Secreta', 'SECRETA', 'secreta.test', 'LagoSecreto', 'PostgreSQL', config('database.connections.pgsql.database')] as $dato) {
            $this->assertStringNotContainsString($dato, $html);
        }

        // Ni con una falla se revela el motivo
        $this->romperBase();
        $this->assertSame('falla', $this->get('/salud')->getContent());
    }

    public function test_el_aviso_se_envia_una_vez_mientras_sigue_fallando_y_otra_al_recuperarse(): void
    {
        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);

        $this->artisan('piscicola:vigilar')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(61)->minutes();   // el programador dejó de correr
        $this->artisan('piscicola:vigilar')->assertFailed();
        $this->artisan('piscicola:vigilar')->assertFailed();
        $this->artisan('piscicola:vigilar')->assertFailed();
        Notification::assertSentTimes(AvisoSalud::class, 1);
        Notification::assertSentTo(new AnonymousNotifiable, AvisoSalud::class, function (AvisoSalud $aviso, $canales, $destino) {
            return $aviso->fallas === ['programador'] && ! $aviso->recuperado && $destino->routes['mail'] === ['dueno@software.test'];
        });

        Comprobaciones::latido();   // vuelve a correr
        $this->artisan('piscicola:vigilar')->assertSuccessful();
        $this->artisan('piscicola:vigilar')->assertSuccessful();
        Notification::assertSentTimes(AvisoSalud::class, 2);
        Notification::assertSentTo(new AnonymousNotifiable, AvisoSalud::class, fn (AvisoSalud $aviso) => $aviso->recuperado);
    }

    public function test_el_aviso_va_a_salud_correo_aviso_si_esta_configurado(): void
    {
        config(['salud.correo_aviso' => 'guardia@servidor.test']);
        $this->romperBase();

        $this->artisan('piscicola:vigilar')->assertFailed();

        Notification::assertSentTo(new AnonymousNotifiable, AvisoSalud::class,
            fn (AvisoSalud $aviso, $canales, $destino) => $destino->routes['mail'] === ['guardia@servidor.test'] && $aviso->fallas === ['base']);
        // El correo dice qué comprobación falló, sin datos de la configuración
        $correo = (new AvisoSalud(['base']))->toMail(new AnonymousNotifiable)->render();
        $this->assertStringContainsString('Base de datos', (string) $correo);
        $this->assertStringNotContainsString(config('database.connections.pgsql.password') ?: 'sin-clave-de-prueba', (string) $correo);
    }

    public function test_el_comando_muestra_el_detalle(): void
    {
        config(['respaldos.carpeta' => null]);

        $this->artisan('piscicola:estado')
            ->expectsOutputToContain('Base de datos')
            ->expectsOutputToContain('Programador de tareas')
            ->expectsOutputToContain('Cola: trabajos fallidos')
            ->expectsOutputToContain('Espacio libre en disco')
            ->expectsOutputToContain('Sin RESPALDOS_CARPETA configurada')
            ->expectsOutputToContain('Todo funciona.')
            ->assertSuccessful();

        $this->romperBase();
        $this->artisan('piscicola:estado')->expectsOutputToContain('FALLA')->assertFailed();
    }

    public function test_los_errores_se_guardan_en_archivos_diarios_con_rotacion(): void
    {
        $this->assertSame('daily', config('logging.channels.daily.driver'));
        $this->assertSame(14, (int) config('logging.channels.daily.max_files'));
        $this->assertStringContainsString('LOG_STACK=daily', (string) file_get_contents(base_path('.env.example')));
    }
}
