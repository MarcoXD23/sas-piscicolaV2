<?php

namespace Tests\Feature;

use App\Console\Commands\Respaldar;
use App\Models\AdministradorPlataforma;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AvisoSalud;
use App\Support\Salud\Comprobaciones;
use App\Support\TenantActual;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        config(['respaldos.carpeta' => null, 'salud.exigir_copia' => false]);
        $this->medirDisco(libreGb: 50, totalGb: 100);
    }

    protected function tearDown(): void
    {
        Comprobaciones::$medirDisco = null;
        foreach ($this->carpetasTemporales as $carpeta) {
            array_map('unlink', glob($carpeta.'/*') ?: []);
            @rmdir($carpeta);
        }
        parent::tearDown();
    }

    /** @var list<string> */
    private array $carpetasTemporales = [];

    /** Disco simulado (en GB). */
    private function medirDisco(float $libreGb, float $totalGb): void
    {
        Comprobaciones::$medirDisco = fn () => ['libre' => $libreGb * 1024 ** 3, 'total' => $totalGb * 1024 ** 3];
    }

    /** Carpeta de respaldos con una copia de hace N horas. */
    private function copiaDeHace(float $horas): void
    {
        $carpeta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'salud-'.bin2hex(random_bytes(4));
        mkdir($carpeta);
        $this->carpetasTemporales[] = $carpeta;
        $archivo = $carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'prueba.dump';
        file_put_contents($archivo, 'copia');
        touch($archivo, (int) now()->subMinutes((int) round($horas * 60))->getTimestamp());
        config(['respaldos.carpeta' => $carpeta]);
    }

    /** Trabajo en la cola que espera desde hace N minutos. */
    private function trabajoEsperando(int $minutos): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null,
            'available_at' => now()->subMinutes($minutos)->getTimestamp(), 'created_at' => now()->subMinutes($minutos)->getTimestamp(),
        ]);
    }

    /** Conexión de base de datos que no responde (puerto cerrado). */
    private function romperBase(): void
    {
        config(['database.connections.rota' => ['port' => 1, 'connect_timeout' => 2] + config('database.connections.pgsql'), 'salud.conexion' => 'rota']);
    }

    /** Disco que no deja escribir. */
    private function romperAlmacenamiento(): void
    {
        $roto = \Mockery::mock(Filesystem::class);
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
            'programador' => fn () => $this->travel(11)->minutes(),
            'copia' => fn () => $this->copiaDeHace(37),
            'disco' => fn () => $this->medirDisco(libreGb: 5, totalGb: 100),
            'cola' => fn () => $this->trabajoEsperando(16),
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

        // Ni con una falla se revela el motivo (pasado el tiempo de la caché)
        $this->romperBase();
        $this->travel(31)->seconds();
        $this->assertSame('falla', $this->get('/salud')->getContent());
    }

    public function test_el_aviso_se_envia_una_vez_mientras_sigue_fallando_y_otra_al_recuperarse(): void
    {
        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);

        $this->artisan('piscicola:vigilar')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(11)->minutes();   // el programador dejó de correr
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
        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
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
        $this->artisan('piscicola:estado')
            ->expectsOutputToContain('Base de datos')
            ->expectsOutputToContain('Programador de tareas (corrió en los últimos 10 minutos)')
            ->expectsOutputToContain('Copia de seguridad (la última tiene 36 horas o menos)')
            ->expectsOutputToContain('Espacio libre en disco (al menos 10 % y 1 GB)')
            ->expectsOutputToContain('Cola de trabajos (ninguno esperando más de 15 minutos y ninguno fallido)')
            ->expectsOutputToContain('Todo funciona.')
            ->assertSuccessful();

        // Lo medido, en la misma fila que cada umbral
        $this->artisan('piscicola:estado')
            ->expectsOutputToContain('Bien · Sin RESPALDOS_CARPETA configurada (no se exige fuera de producción)')
            ->expectsOutputToContain('Bien · 50,0 GB libres (50,0 %)')
            ->expectsOutputToContain('Bien · 0 pendientes, 0 atascados, 0 fallidos')
            ->assertSuccessful();

        $this->romperBase();
        $this->artisan('piscicola:estado')->expectsOutputToContain('FALLA')->assertFailed();
    }

    public function test_umbral_del_programador_10_minutos(): void
    {
        $this->assertSame(10, config('salud.minutos_programador'));
        $this->travel(9)->minutes();
        $this->assertTrue(Comprobaciones::programador());
        $this->travel(2)->minutes();
        $this->assertFalse(Comprobaciones::programador());
    }

    public function test_umbral_de_la_copia_36_horas(): void
    {
        $this->assertSame(36, config('salud.horas_copia'));
        $this->copiaDeHace(35);
        $this->assertTrue(Comprobaciones::copia());
        $this->artisan('piscicola:estado')->expectsOutputToContain('hace 35 horas')->assertSuccessful();

        $this->copiaDeHace(37);
        $this->assertFalse(Comprobaciones::copia());
        $this->artisan('piscicola:estado')->expectsOutputToContain('FALLA')->assertFailed();
    }

    public function test_la_copia_falla_si_la_carpeta_no_tiene_copias_o_falta_en_produccion(): void
    {
        $this->copiaDeHace(1);
        array_map('unlink', glob(config('respaldos.carpeta').'/*'));
        $this->assertFalse(Comprobaciones::copia());

        // Sin RESPALDOS_CARPETA: solo falla si se exige (por defecto, en producción)
        config(['respaldos.carpeta' => null, 'salud.exigir_copia' => false]);
        $this->assertTrue(Comprobaciones::copia());
        config(['salud.exigir_copia' => true]);
        $this->assertFalse(Comprobaciones::copia());
        $this->artisan('piscicola:estado')->expectsOutputToContain('Sin RESPALDOS_CARPETA configurada')->assertFailed();
    }

    public function test_umbral_del_disco_por_porcentaje(): void
    {
        $this->medirDisco(libreGb: 9.9, totalGb: 100);   // 9,9 %: más de 1 GB pero menos del 10 %
        $this->assertFalse(Comprobaciones::disco());
        $this->medirDisco(libreGb: 10, totalGb: 100);
        $this->assertTrue(Comprobaciones::disco());
    }

    public function test_umbral_del_disco_por_gb(): void
    {
        $this->medirDisco(libreGb: 0.9, totalGb: 4);   // 22 %: más del 10 % pero menos de 1 GB
        $this->assertFalse(Comprobaciones::disco());
        $this->medirDisco(libreGb: 1.1, totalGb: 4);
        $this->assertTrue(Comprobaciones::disco());
    }

    public function test_umbral_de_la_cola_15_minutos(): void
    {
        $this->trabajoEsperando(14);
        $this->assertTrue(Comprobaciones::cola());
        $this->trabajoEsperando(16);
        $this->assertFalse(Comprobaciones::cola());
        $this->artisan('piscicola:estado')->expectsOutputToContain('2 pendientes, 1 atascados, 0 fallidos')->assertFailed();
    }

    public function test_un_trabajo_fallido_hace_fallar_la_cola(): void
    {
        config(['salud.correo_aviso' => 'guardia@servidor.test']);
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'prueba', 'failed_at' => now(),
        ]);
        $this->assertFalse(Comprobaciones::cola());

        $this->artisan('piscicola:vigilar')
            ->expectsOutputToContain('FALLA · Cola de trabajos (ninguno esperando más de 15 minutos y ninguno fallido)')
            ->assertFailed();
        Notification::assertSentTo(new AnonymousNotifiable, AvisoSalud::class, fn (AvisoSalud $aviso) => $aviso->fallas === ['cola']);
    }

    public function test_los_umbrales_salen_de_la_configuracion_y_se_muestran(): void
    {
        config(['salud.minutos_programador' => 20, 'salud.horas_copia' => 48, 'salud.disco_porcentaje_minimo' => 5, 'salud.disco_gb_minimo' => 2.5, 'salud.minutos_cola' => 30]);

        $this->travel(15)->minutes();
        $this->trabajoEsperando(25);
        $this->artisan('piscicola:vigilar')
            ->expectsOutputToContain('Bien · Programador de tareas (corrió en los últimos 20 minutos)')
            ->expectsOutputToContain('Bien · Copia de seguridad (la última tiene 48 horas o menos)')
            ->expectsOutputToContain('Bien · Espacio libre en disco (al menos 5 % y 2,5 GB)')
            ->expectsOutputToContain('Bien · Cola de trabajos (ninguno esperando más de 30 minutos y ninguno fallido)')
            ->assertSuccessful();

        // Las variables de entorno existen en .env.example (vacías = valor por defecto)
        $ejemplo = str_replace("\r\n", "\n", (string) file_get_contents(base_path('.env.example')));
        foreach (['SALUD_MINUTOS_PROGRAMADOR', 'SALUD_HORAS_COPIA', 'SALUD_DISCO_PORCENTAJE', 'SALUD_DISCO_GB', 'SALUD_MINUTOS_COLA', 'SALUD_CACHE_SEGUNDOS', 'SALUD_EXIGIR_COPIA'] as $variable) {
            $this->assertStringContainsString("\n".$variable."=\n", $ejemplo);
        }
    }

    public function test_salud_guarda_su_resultado_30_segundos(): void
    {
        $this->assertSame(30, config('salud.segundos_cache'));
        $this->get('/salud')->assertOk();

        $this->romperBase();
        $this->travel(29)->seconds();
        $this->assertSame('ok', $this->get('/salud')->assertOk()->getContent());

        $this->travel(2)->seconds();
        $this->assertSame('falla', $this->get('/salud')->assertStatus(503)->getContent());
    }

    public function test_sin_salud_correo_aviso_el_aviso_va_a_los_administradores_de_plataforma(): void
    {
        AdministradorPlataforma::create(['name' => 'Uno', 'email' => 'uno@software.test', 'password' => 'ClaveLarga12345']);
        AdministradorPlataforma::create(['name' => 'Dos', 'email' => 'dos@software.test', 'password' => 'ClaveLarga12345']);
        config(['salud.correo_aviso' => null]);
        $this->medirDisco(libreGb: 0.5, totalGb: 100);

        $this->artisan('piscicola:vigilar')->assertFailed();

        Notification::assertSentTo(new AnonymousNotifiable, AvisoSalud::class, function (AvisoSalud $aviso, $canales, $destino) {
            $correos = $destino->routes['mail'];
            sort($correos);

            return $aviso->fallas === ['disco'] && $correos === ['dos@software.test', 'uno@software.test'];
        });
    }

    public function test_los_errores_se_guardan_en_archivos_diarios_con_rotacion(): void
    {
        $this->assertSame('daily', config('logging.channels.daily.driver'));
        $this->assertSame(14, (int) config('logging.channels.daily.max_files'));
        $this->assertStringContainsString('LOG_STACK=daily', (string) file_get_contents(base_path('.env.example')));
    }
}
