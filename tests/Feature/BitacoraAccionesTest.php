<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las acciones de la bitácora caben en bitacora.accion. En F6a «jornada_lago_agregado» (21 letras) rompía el registro
 * con un error 500: esta prueba lo detecta antes. El tamaño se lee de la base, no se copia aquí.
 */
class BitacoraAccionesTest extends TestCase
{
    use RefreshDatabase;

    private function largoColumna(): int
    {
        return (int) DB::table('information_schema.columns')
            ->where('table_name', 'bitacora')->where('column_name', 'accion')
            ->value('character_maximum_length');
    }

    /** @return list<string> acciones escritas tal cual en las llamadas a Bitacora::registrar() del código */
    private function accionesEnElCodigo(): array
    {
        $acciones = [];
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS));
        foreach ($archivos as $archivo) {
            if (str_ends_with($archivo->getFilename(), '.php')
                && preg_match_all("/Bitacora::registrar\(\s*[^,]+,\s*'([a-z_]+)'/", file_get_contents($archivo->getPathname()), $m)) {
                array_push($acciones, ...$m[1]);
            }
        }

        return array_values(array_unique($acciones));
    }

    public function test_ninguna_accion_pasa_del_tamano_de_la_columna(): void
    {
        $largo = $this->largoColumna();
        $this->assertGreaterThan(0, $largo);

        $largas = collect([...Bitacora::ACCIONES, ...$this->accionesEnElCodigo()])->unique()
            ->filter(fn (string $accion) => mb_strlen($accion) > $largo)->values()->all();

        $this->assertSame([], $largas, "Acciones de bitácora de más de {$largo} caracteres (no caben en bitacora.accion)");
    }

    public function test_toda_accion_usada_en_el_codigo_esta_en_la_lista(): void
    {
        $fuera = array_values(array_diff($this->accionesEnElCodigo(), Bitacora::ACCIONES));

        $this->assertNotSame([], $this->accionesEnElCodigo());
        $this->assertSame([], $fuera, 'Acciones usadas en Bitacora::registrar() que faltan en Bitacora::ACCIONES');
    }
}
