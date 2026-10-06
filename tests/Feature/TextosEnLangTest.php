<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Los textos de la interfaz viven en lang/es: ninguna vista debe tener frases fijas.
 * Se permiten solo unidades y signos (kg, g, mg/L, °C, %, ·).
 */
class TextosEnLangTest extends TestCase
{
    private const UNIDADES = '/^(kg|g|mg\/L|°C|cm|pH|%|m²|m³|h|L|·|—|–|\/|:|\+|−|-|×|\(|\)|,|\.|&times;|&middot;)+$/u';

    private const ATRIBUTOS = 'data-label|unidad|titulo|subtitulo|etiqueta|mensaje|placeholder|aria-label|title|ayuda|vacio|texto|alt|confirmar';

    /** @return list<string> */
    public static function textosFijos(string $fuente): array
    {
        $hallados = [];
        $codigo = [];
        $s = preg_replace('/\{\{--.*?--\}\}/s', ' ', $fuente);
        // Confirmaciones de JavaScript escritas a mano en atributos onclick
        if (preg_match_all('/confirm\(\'([^\']*)\'\)/u', $s, $m)) {
            array_push($hallados, ...$m[1]);
        }
        // Código Blade/PHP: se guarda aparte para revisar sus cadenas y se reemplaza por una marca
        $proteger = function ($m) use (&$codigo) {
            $codigo[] = $m[0];

            return "\x01";
        };
        $s = preg_replace_callback('/\{!!.*?!!\}|\{\{.*?\}\}|@php\b.*?@endphp|<script\b.*?<\/script>|<style\b.*?<\/style>/s', $proteger, $s);
        $s = preg_replace_callback('/@\w+(?:\s*(\((?:[^()\'"]++|\'[^\']*\'|"[^"]*"|(?1))*\)))?/', $proteger, $s);

        if (preg_match_all('/(?<=\s)(?:'.self::ATRIBUTOS.')="([^"]*)"/u', $s, $m)) {
            foreach ($m[1] as $valor) {
                $texto = trim(str_replace("\x01", ' ', $valor));
                if (preg_match('/\p{L}/u', $texto) && ! preg_match(self::UNIDADES, str_replace(' ', '', $texto))) {
                    $hallados[] = $texto;
                }
            }
        }
        $s = preg_replace_callback('/(?<=\s):[\w.-]+="[^"]*"/', $proteger, $s);
        $s = preg_replace('/(?<=\s)[\w.-]+="[^"]*"/', '', $s);

        if (preg_match_all('/>([^<>]+)</u', $s, $m)) {
            foreach ($m[1] as $nodo) {
                foreach (explode("\x01", $nodo) as $pieza) {
                    $pieza = trim($pieza);
                    if (preg_match('/\p{L}/u', $pieza) && ! preg_match(self::UNIDADES, str_replace(' ', '', $pieza))) {
                        $hallados[] = $pieza;
                    }
                }
            }
        }

        // Cadenas dentro del código: frases con mayúscula inicial, tildes o eñes que no pasan por __()
        foreach ($codigo as $trozo) {
            $trozo = preg_replace('/\b(__|trans_choice)\(\s*\'[^\']*\'/', '', $trozo);
            if (preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'/u', $trozo, $m)) {
                foreach ($m[1] as $cadena) {
                    $frase = preg_match('/\p{Lu}\p{Ll}{2,}|\p{Lu}\p{Ll}*\s+\p{Ll}{2,}|[áéíóúñ¿¡]/u', $cadena)
                        || preg_match('/^[\s\p{Ll}()·,.:]*\p{Ll}{2,}\s+\p{Ll}{2,}[\s\p{Ll}()·,.:]*$/u', $cadena);
                    if ($frase && ! str_contains($cadena, '\\')) {
                        $hallados[] = $cadena;
                    }
                }
            }
        }

        return $hallados;
    }

    public function test_las_vistas_no_tienen_textos_fijos(): void
    {
        $vistas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS));
        $problemas = [];

        foreach ($vistas as $archivo) {
            if (str_ends_with($archivo->getFilename(), '.blade.php')) {
                foreach (self::textosFijos(file_get_contents($archivo->getPathname())) as $texto) {
                    $problemas[] = str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $archivo->getPathname()).': '.$texto;
                }
            }
        }

        $this->assertSame([], $problemas, "Textos fijos en vistas (muévelos a lang/es):\n".implode("\n", $problemas));
    }

    public function test_todas_las_claves_usadas_existen_en_lang_es(): void
    {
        $grupos = array_map(fn ($ruta) => basename($ruta, '.php'), glob(lang_path('es/*.php')));
        // Claves dentro de __('...') o en sus ternarios: __($condicion ? 'grupo.a' : 'grupo.b')
        $patron = '/(?:__\(\s*|\?\s+|:\s+)[\'"]((?:'.implode('|', $grupos).')\.[\w.]*\w)[\'"]/u';
        $archivos = new \AppendIterator;
        $archivos->append(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)));
        $archivos->append(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS)));
        $faltantes = [];

        foreach ($archivos as $archivo) {
            if (preg_match_all($patron, file_get_contents($archivo->getPathname()), $m)) {
                foreach (array_unique($m[1]) as $clave) {
                    // Se ignoran nombres de rutas y de vistas que comparten prefijo con un grupo (por ejemplo «noche.index»)
                    if (! Lang::has($clave, 'es', false) && ! Route::has($clave) && ! view()->exists($clave)) {
                        $faltantes[] = $archivo->getFilename().': '.$clave;
                    }
                }
            }
        }

        $this->assertSame([], $faltantes, "Claves sin traducción en lang/es:\n".implode("\n", $faltantes));
    }

    public function test_el_detector_encuentra_textos_fijos_y_respeta_las_traducciones(): void
    {
        $this->assertEqualsCanonicalizing(['Guardar', 'Nombre', 'Sin datos'], self::textosFijos(
            '<x-boton>Guardar</x-boton><x-campo etiqueta="Nombre" /><p>{{ $x ? \'Sin datos\' : \'\' }}</p>'
        ));
        $this->assertSame([], self::textosFijos(
            '<x-boton>{{ __(\'comun.guardar\') }}</x-boton><span>{{ $kg }} kg</span><x-campo :etiqueta="__(\'lagos.nombre\')" class="text-sm" />'
        ));
    }
}
