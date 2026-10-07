<?php

namespace Tests\Feature;

use App\Console\Commands\Respaldar;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Copias de seguridad: piscicola:respaldar y piscicola:restaurar.
 * Las reglas (contraseña, carpeta, retención, copia dañada, base de la app) se prueban con Process::fake().
 * La prueba de punta a punta respalda y restaura de verdad con pg_dump y pg_restore (PG_BIN o el PATH).
 */
class RespaldoTest extends TestCase
{
    use RefreshDatabase;

    private const BASE_RESTAURADA = 'sas_piscicola_v2_test_restaurada';

    private string $carpeta;

    protected function setUp(): void
    {
        parent::setUp();

        // Fuera del proyecto, como exige el comando
        $this->carpeta = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sas_respaldos_prueba_'.bin2hex(random_bytes(4));
        config(['respaldos.carpeta' => $this->carpeta, 'respaldos.dias' => 14]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->carpeta);

        parent::tearDown();
    }

    /** Simula pg_dump (escribe el archivo) y pg_restore --list (responde con el índice o con un error). */
    private function simularHerramientas(bool $copiaLegible = true): void
    {
        Process::fake(function (PendingProcess $proceso) use ($copiaLegible) {
            $comando = (array) $proceso->command;

            if (str_contains($comando[0], 'pg_dump')) {
                $archivo = substr((string) collect($comando)->first(fn ($arg) => str_starts_with($arg, '--file=')), 7);
                file_put_contents($archivo, 'PGDMP copia simulada');

                return Process::result();
            }

            return $copiaLegible
                ? Process::result(output: ";\n; Archive created at ...\n215; 1259 TABLE public lagos")
                : Process::result(errorOutput: 'pg_restore: error: input file does not appear to be a valid archive', exitCode: 1);
        });
    }

    public function test_la_contrasena_de_la_base_no_va_en_la_linea_de_comando(): void
    {
        config(['database.connections.pgsql.password' => 'clave-base-secreta-77']);
        $this->simularHerramientas();

        $this->artisan('piscicola:respaldar')->assertSuccessful();

        Process::assertRan(function (PendingProcess $proceso) {
            $comando = implode(' ', (array) $proceso->command);

            return str_contains($comando, 'pg_dump')
                && str_contains($comando, '--format=custom')
                && str_contains($comando, '--no-password')
                && ! str_contains($comando, 'clave-base-secreta-77')
                && ($proceso->environment['PGPASSWORD'] ?? null) === 'clave-base-secreta-77';
        });
        $this->assertCount(1, glob($this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'*.dump'));
    }

    public function test_rechaza_una_carpeta_dentro_del_proyecto_o_relativa(): void
    {
        Process::fake();

        foreach ([base_path('storage/respaldos'), base_path(), 'respaldos', $this->carpeta.'/../'.basename(base_path())] as $carpeta) {
            config(['respaldos.carpeta' => $carpeta]);
            $this->artisan('piscicola:respaldar')->assertFailed();
        }

        config(['respaldos.carpeta' => null]);
        $this->artisan('piscicola:respaldar')->expectsOutputToContain('RESPALDOS_CARPETA')->assertFailed();

        Process::assertNothingRan();
        $this->assertDirectoryDoesNotExist(base_path('storage/respaldos'));
    }

    public function test_la_retencion_borra_solo_las_copias_viejas_del_comando(): void
    {
        File::ensureDirectoryExists($this->carpeta);
        $vieja = $this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'20200101_020000.dump';
        $reciente = $this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'reciente.dump';
        $ajena = $this->carpeta.DIRECTORY_SEPARATOR.'otra_base.dump';
        $nota = $this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'nota.txt';
        foreach ([$vieja, $reciente, $ajena, $nota] as $archivo) {
            file_put_contents($archivo, 'x');
        }
        $hace20Dias = now()->subDays(20)->getTimestamp();
        foreach ([$vieja, $ajena, $nota] as $archivo) {
            touch($archivo, $hace20Dias);
        }
        touch($reciente, now()->subDays(3)->getTimestamp());
        $this->simularHerramientas();

        $this->artisan('piscicola:respaldar')->expectsOutputToContain('Copias viejas borradas: 1')->assertSuccessful();

        $this->assertFileDoesNotExist($vieja);
        $this->assertFileExists($reciente);
        $this->assertFileExists($ajena);
        $this->assertFileExists($nota);
        $this->assertCount(2, glob($this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'*.dump'));
    }

    public function test_una_copia_que_no_se_puede_leer_se_borra_y_el_comando_falla(): void
    {
        File::ensureDirectoryExists($this->carpeta);
        $vieja = $this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'20200101_020000.dump';
        file_put_contents($vieja, 'x');
        touch($vieja, now()->subDays(20)->getTimestamp());
        $this->simularHerramientas(copiaLegible: false);

        $this->artisan('piscicola:respaldar')->expectsOutputToContain('no se pudo verificar')->assertFailed();

        // Ni queda la copia dañada ni se borran las viejas (la retención solo corre tras una copia buena)
        $this->assertSame([$vieja], glob($this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'*.dump'));
    }

    public function test_restaurar_nunca_escribe_sobre_la_base_de_la_app(): void
    {
        Process::fake();
        File::ensureDirectoryExists($this->carpeta);
        $copia = $this->carpeta.DIRECTORY_SEPARATOR.'copia.dump';
        file_put_contents($copia, 'x');

        $this->artisan('piscicola:restaurar', ['archivo' => $copia, '--base' => config('database.connections.pgsql.database')])
            ->expectsOutputToContain('es la base que usa la app')->assertFailed();
        $this->artisan('piscicola:restaurar', ['archivo' => $copia, '--base' => 'Base-Con;Comillas"'])->assertFailed();
        $this->artisan('piscicola:restaurar', ['archivo' => $copia])->assertFailed();
        $this->artisan('piscicola:restaurar', ['archivo' => $this->carpeta.'/no-existe.dump', '--base' => 'otra_base'])->assertFailed();

        Process::assertNothingRan();
    }

    public function test_respalda_y_restaura_de_verdad_y_los_datos_coinciden(): void
    {
        if (! $this->herramientasDisponibles()) {
            $this->markTestSkipped('No se encontró pg_dump/pg_restore: configura PG_BIN en el .env para correr esta prueba.');
        }

        // pg_dump usa otra conexión: los datos deben estar confirmados (fuera de la transacción de RefreshDatabase)
        DB::commit();
        $antes = $this->huellas(DB::connection());
        $tenants = [];

        try {
            foreach (['Norte', 'Sur'] as $nombre) {
                $finca = Tenant::factory()->create(['nombre' => 'Finca '.$nombre]);
                $tenants[] = $finca->id;
                app(TenantActual::class)->establecer($finca->id);
                User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $finca->id]);
                $lago = Lago::create(['nombre' => 'L-'.$nombre, 'tipo' => 'tierra']);
                Siembra::create(['lago_id' => $lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subDays(10),
                    'cantidad_alevinos' => 1500, 'peso_promedio_inicial_g' => 5.25, 'peces_vivos' => 1480,
                    'peso_actual_g' => 32.5, 'biomasa_kg' => 48.1]);
            }
            app(TenantActual::class)->establecer(null);

            $this->soltarBaseRestaurada();
            $this->artisan('piscicola:respaldar')->assertSuccessful();
            $copia = glob($this->carpeta.DIRECTORY_SEPARATOR.Respaldar::PREFIJO.'*.dump')[0];

            $this->artisan('piscicola:restaurar', ['archivo' => $copia, '--base' => self::BASE_RESTAURADA])->assertSuccessful();

            config(['database.connections.restaurada' => ['database' => self::BASE_RESTAURADA] + config('database.connections.pgsql')]);
            $original = $this->huellas(DB::connection());
            $restaurada = $this->huellas(DB::connection('restaurada'));

            // Mismas tablas, mismas filas y mismo contenido (huella md5 de cada tabla)
            $this->assertNotEmpty($original);
            $this->assertSame($original, $restaurada);
            $this->assertSame(2, $restaurada['lagos']['filas']);
            // La bitácora sigue protegida en la copia restaurada (triggers de solo inserción)
            $this->assertSame($this->triggers(DB::connection()), $this->triggers(DB::connection('restaurada')));
            $this->assertContains('bitacora_sin_vaciado', $this->triggers(DB::connection('restaurada')));

            // Una base que ya tiene tablas no se usa como destino
            $this->artisan('piscicola:restaurar', ['archivo' => $copia, '--base' => self::BASE_RESTAURADA])
                ->expectsOutputToContain('ya tiene tablas')->assertFailed();
        } finally {
            DB::purge('restaurada');
            $this->soltarBaseRestaurada();
            app(TenantActual::class)->establecer(null);
            // Deja la base de pruebas como estaba (los datos se confirmaron fuera de la transacción)
            DB::table('siembras')->whereIn('tenant_id', $tenants)->delete();
            DB::table('lagos')->whereIn('tenant_id', $tenants)->delete();
            DB::table('users')->whereIn('tenant_id', $tenants)->delete();
            DB::table('tenants')->whereIn('id', $tenants)->delete();
            DB::beginTransaction();
        }

        $this->assertSame(array_map(fn ($t) => $t['filas'], $antes), array_map(fn ($t) => $t['filas'], $this->huellas(DB::connection())));
    }

    private function herramientasDisponibles(): bool
    {
        $carpeta = config('respaldos.pg_bin');
        $binario = filled($carpeta) ? rtrim($carpeta, '\\/').DIRECTORY_SEPARATOR.'pg_dump' : 'pg_dump';

        return Process::run([$binario, '--version'])->successful();
    }

    private function soltarBaseRestaurada(): void
    {
        DB::statement('DROP DATABASE IF EXISTS "'.self::BASE_RESTAURADA.'" WITH (FORCE)');
    }

    /** @return array<string, array{filas: int, huella: string}> */
    private function huellas($conexion): array
    {
        $tablas = collect($conexion->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"))->pluck('tablename');

        return $tablas->mapWithKeys(function (string $tabla) use ($conexion) {
            $fila = $conexion->selectOne("SELECT count(*) AS filas, md5(coalesce(string_agg(t::text, '|' ORDER BY t::text), '')) AS huella FROM \"{$tabla}\" t");

            return [$tabla => ['filas' => (int) $fila->filas, 'huella' => $fila->huella]];
        })->all();
    }

    private function triggers($conexion): array
    {
        return collect($conexion->select('SELECT tgname FROM pg_trigger WHERE NOT tgisinternal ORDER BY tgname'))->pluck('tgname')->all();
    }
}
