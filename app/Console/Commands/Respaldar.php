<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\UsaHerramientasPostgres;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Copia de seguridad de PostgreSQL con pg_dump (formato comprimido de pg_restore).
 * - Solo guarda FUERA del proyecto (RESPALDOS_CARPETA), para que una copia nunca llegue a git.
 * - Verifica la copia con pg_restore --list: si está dañada, la borra y falla.
 * - Borra solo sus propias copias (sas_piscicola_*.dump) con más de RESPALDOS_DIAS días.
 * Se programa a diario en routes/console.php cuando hay carpeta configurada.
 */
class Respaldar extends Command
{
    use UsaHerramientasPostgres;

    public const PREFIJO = 'sas_piscicola_';

    protected $signature = 'piscicola:respaldar';

    public function __construct()
    {
        parent::__construct();
        $this->description = __('comandos.respaldar.descripcion');
    }

    public function handle(): int
    {
        $carpeta = $this->carpeta();
        if ($carpeta === null) {
            return self::FAILURE;
        }

        $archivo = $carpeta.DIRECTORY_SEPARATOR.self::PREFIJO.now('UTC')->format('Ymd_His').'.dump';

        $copia = Process::env($this->entornoPostgres())->timeout(3600)->run([
            $this->binario('pg_dump'),
            ...$this->argumentosConexion(),
            '--format=custom',
            '--file='.$archivo,
            $this->baseActual(),
        ]);

        if ($copia->failed()) {
            @unlink($archivo);
            Log::error('Respaldo: pg_dump falló', ['salida' => $copia->errorOutput()]);
            $this->error(__('comandos.respaldar.fallo_copia', ['detalle' => trim($copia->errorOutput())]));

            return self::FAILURE;
        }

        // Una copia que pg_restore no puede leer no sirve: se borra y se avisa
        $revision = Process::timeout(600)->run([$this->binario('pg_restore'), '--list', $archivo]);
        if ($revision->failed() || trim($revision->output()) === '') {
            @unlink($archivo);
            Log::error('Respaldo: la copia no se pudo verificar', ['archivo' => basename($archivo)]);
            $this->error(__('comandos.respaldar.copia_danada'));

            return self::FAILURE;
        }

        $borradas = $this->aplicarRetencion($carpeta, $archivo);

        Log::info('Respaldo creado', ['archivo' => basename($archivo), 'borradas' => $borradas]);
        $this->info(__('comandos.respaldar.listo', [
            'archivo' => $archivo,
            'tamano' => number_format((filesize($archivo) ?: 0) / 1048576, 2),
            'borradas' => $borradas,
            'dias' => config('respaldos.dias'),
        ]));

        return self::SUCCESS;
    }

    /** Carpeta configurada, creada si falta y fuera del proyecto; null (con el error ya mostrado) si no sirve. */
    private function carpeta(): ?string
    {
        $carpeta = config('respaldos.carpeta');

        if (blank($carpeta)) {
            $this->error(__('comandos.respaldar.sin_carpeta'));

            return null;
        }

        $carpeta = rtrim((string) $carpeta, '\\/');

        // Ruta absoluta y sin "..": así se puede comprobar con certeza que queda fuera del proyecto
        if (! preg_match('#^([a-zA-Z]:[\\\\/]|/|\\\\\\\\)#', $carpeta) || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $carpeta)) {
            $this->error(__('comandos.respaldar.ruta_no_absoluta'));

            return null;
        }

        $proyecto = $this->normalizar(base_path());
        $destino = $this->normalizar($carpeta);

        if ($destino === $proyecto || str_starts_with($destino.'/', $proyecto.'/')) {
            $this->error(__('comandos.respaldar.dentro_del_proyecto'));

            return null;
        }

        if (! is_dir($carpeta) && ! @mkdir($carpeta, 0700, true) && ! is_dir($carpeta)) {
            $this->error(__('comandos.respaldar.carpeta_no_creada', ['carpeta' => $carpeta]));

            return null;
        }

        return $carpeta;
    }

    /** Borra solo las copias de este comando más viejas que la retención (nunca la recién creada). */
    private function aplicarRetencion(string $carpeta, string $nueva): int
    {
        $limite = now()->subDays((int) config('respaldos.dias'))->getTimestamp();
        $borradas = 0;

        foreach (glob($carpeta.DIRECTORY_SEPARATOR.self::PREFIJO.'*.dump') ?: [] as $copia) {
            if ($this->normalizar($copia) !== $this->normalizar($nueva) && filemtime($copia) < $limite && @unlink($copia)) {
                $borradas++;
            }
        }

        return $borradas;
    }
}
