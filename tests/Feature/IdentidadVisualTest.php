<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Areas;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentidadVisualTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_imagenes_publicas_pesan_menos_de_200_kb(): void
    {
        $extensiones = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico'];
        $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path(), \FilesystemIterator::SKIP_DOTS));
        $revisadas = 0;

        foreach ($archivos as $archivo) {
            if (str_contains($archivo->getPathname(), DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR)
                || ! in_array(strtolower($archivo->getExtension()), $extensiones, true)) {
                continue;
            }

            $revisadas++;
            $this->assertLessThan(200 * 1024, $archivo->getSize(), "La imagen {$archivo->getFilename()} supera 200 KB.");
        }

        $this->assertGreaterThan(0, $revisadas);
    }

    public function test_el_manifest_apunta_a_iconos_existentes(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('El SAS Piscícola', $manifest['name']);
        $this->assertNotEmpty($manifest['icons']);

        foreach ($manifest['icons'] as $icono) {
            $this->assertFileExists(public_path(ltrim($icono['src'], '/')));
            [$ancho, $alto] = getimagesize(public_path(ltrim($icono['src'], '/')));
            $this->assertSame($icono['sizes'], "{$ancho}x{$alto}");
        }
    }

    public function test_el_layout_enlaza_favicon_y_manifest(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('href="/manifest.webmanifest"', false)
            ->assertSee('href="/favicon.ico"', false)
            ->assertSee('href="/icons/icon-192.png"', false)
            ->assertSee('href="/icons/apple-touch-icon.png"', false);
    }

    public function test_el_login_y_el_inicio_no_nombran_imagenes_inexistentes(): void
    {
        // Fondos: fotos CC0 y de dominio público (ver docs/marca/LEEME.md)
        $this->assertSinImagenesInexistentes($this->get(route('login'))->assertOk()->assertSee('/images/fondo-login.webp', false)->getContent());

        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($finca->id);
        $propietario = tap(User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->assertSinImagenesInexistentes($this->actingAs($propietario)->get(route('inicio'))->assertOk()->assertSee('/images/fondo-estanque.webp', false)->getContent());
    }

    public function test_el_service_worker_guarda_las_imagenes_y_estrena_version(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Cada versión borra las copias anteriores (la v9 retiró los fondos SVG; la v10 agregó Registro rápido; la v11, su acceso en offline.html;
        // la v12 estrena la libreta de campo; la v13, la tipografía Nunito Sans; la v14, los íconos de Lucide y el color por área)
        $this->assertStringContainsString("const VERSION = 'v14'", $sw);
        $this->assertStringContainsString("const ESTATICOS = ['/build/', '/images/', '/icons/'];", $sw);
    }

    public function test_la_tipografia_es_nunito_sans_alojada_en_el_proyecto_y_liviana(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $paquete = (string) file_get_contents(base_path('package.json'));

        // Del paquete del proyecto, nunca de un CDN; la anterior ya no está
        $this->assertStringContainsString("@import '@fontsource-variable/nunito-sans';", $css);
        $this->assertMatchesRegularExpression("/--font-sans: 'Nunito Sans Variable', system-ui/", $css);
        $this->assertStringContainsString('"@fontsource-variable/nunito-sans"', $paquete);
        foreach ([$css, $paquete, (string) file_get_contents(resource_path('views/layouts/app.blade.php'))] as $fuente) {
            $this->assertStringNotContainsStringIgnoringCase('atkinson', $fuente);
            $this->assertStringNotContainsString('fonts.googleapis', $fuente);
            $this->assertDoesNotMatchRegularExpression('/@import\s+url\(\s*[\'"]?https?:/i', $fuente);
        }

        // El CSS compilado trae la fuente desde /build, y el archivo latino (el que baja una página en español) es liviano
        $manifiesto = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);
        $compilado = (string) file_get_contents(public_path('build/'.$manifiesto['resources/css/app.css']['file']));
        $this->assertStringContainsString('Nunito Sans Variable', $compilado);
        $this->assertMatchesRegularExpression('#/build/assets/nunito-sans-latin-wght-normal-[\w-]+\.woff2#', $compilado);
        preg_match('#/build/assets/(nunito-sans-latin-wght-normal-[\w-]+\.woff2)#', $compilado, $latino);
        $this->assertLessThan(40 * 1024, filesize(public_path('build/assets/'.$latino[1])));

        // Las cifras siguen alineadas en columna, la impresión conserva su regla y los PDF siguen con DejaVu Sans (dompdf)
        $this->assertGreaterThan(50, substr_count(implode('', array_map('file_get_contents', glob(resource_path('views/**/*.blade.php')) ?: [])).implode('', array_map('file_get_contents', glob(resource_path('views/*/*/*.blade.php')) ?: [])), 'tabular-nums'));
        $this->assertStringContainsString('@media print', $css);
        $this->assertStringContainsString("font-family: 'DejaVu Sans'", (string) file_get_contents(resource_path('views/pdf/reporte.blade.php')));

        // Licencia anotada en la marca
        $leeme = (string) file_get_contents(base_path('docs/marca/LEEME.md'));
        $this->assertStringContainsString('Nunito Sans', $leeme);
        $this->assertStringContainsString('SIL Open Font License 1.1', $leeme);
    }

    public function test_los_fondos_son_fotos_libres_livianas_y_anotadas(): void
    {
        $leeme = (string) file_get_contents(base_path('docs/marca/LEEME.md'));

        foreach (['fondo-login.webp' => 'CC0', 'fondo-estanque.webp' => 'Dominio público'] as $fondo => $licencia) {
            $ruta = public_path('images/'.$fondo);
            $this->assertFileExists($ruta);
            $this->assertLessThan(200 * 1024, filesize($ruta), $fondo);
            $this->assertSame('image/webp', getimagesize($ruta)['mime']);
            // En el LEEME, en su fila: con licencia que no exige crédito (CC0 o dominio público) y fuente en Commons
            $this->assertMatchesRegularExpression('/\| `'.preg_quote($fondo, '/').'` \|[^
]*commons\.wikimedia\.org[^
]*'.preg_quote($licencia, '/').'/u', $leeme);
        }
        $this->assertFileDoesNotExist(public_path('images/fondo-login.svg'));
        $this->assertFileDoesNotExist(public_path('images/fondo-estanque.svg'));
    }

    /** Ninguna imagen local (img, ícono o CSS url()) que no exista en public/. */
    private function assertSinImagenesInexistentes(string $html): void
    {
        preg_match_all('/(?:src|href)="(\/[^"\/][^"]*\.(?:png|jpe?g|webp|gif|svg|ico))"|url\(["\']?(\/[^"\')]+)["\']?\)/i', $html, $rutas);
        foreach (array_filter(array_merge($rutas[1], $rutas[2])) as $ruta) {
            $this->assertFileExists(public_path(ltrim(parse_url($ruta, PHP_URL_PATH), '/')), "Imagen inexistente: {$ruta}");
        }
    }

    /** @return list<string> Nombres de los íconos del componente */
    private function iconosDelComponente(): array
    {
        preg_match_all("/@case\('([a-z-]+)'\)/", (string) file_get_contents(resource_path('views/components/icono.blade.php')), $m);

        return $m[1];
    }

    /** @return list<string> archivos de vistas, código y JavaScript (sin el componente de íconos) */
    private function fuentes(): array
    {
        $archivos = array_merge(
            glob(resource_path('views/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE),
            glob(app_path('{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE),
            glob(resource_path('js/*.js')),
        );

        return array_values(array_filter($archivos, fn ($a) => ! str_ends_with(str_replace('\\', '/', $a), 'components/icono.blade.php')));
    }

    public function test_cada_icono_pedido_existe_y_no_sobra_ninguno(): void
    {
        $iconos = $this->iconosDelComponente();
        $this->assertCount(30, $iconos);
        $codigo = implode("\n", array_map('file_get_contents', $this->fuentes()));

        // Pedidos de forma fija: <x-icono nombre="…">, icono="…" en los componentes, 'icono' => '…' y los de cada área
        preg_match_all('/<x-icono\s+nombre="([a-z-]+)"/', $codigo, $fijos);
        preg_match_all('/(?<![\w-])icono="([a-z-]+)"/', $codigo, $atributos);
        preg_match_all("/'icono' => '([a-z-]+)'/", $codigo, $arreglos);
        $pedidos = array_unique([...$fijos[1], ...$atributos[1], ...$arreglos[1], ...array_values(Areas::ICONOS)]);
        $faltan = array_values(array_diff($pedidos, $iconos));
        $this->assertSame([], $faltan, 'Íconos pedidos que no existen (se verían vacíos): '.implode(', ', $faltan));

        // Ninguno de más: cada ícono del componente se nombra en alguna vista, código o script
        $sobran = array_values(array_filter($iconos, fn (string $n) => ! preg_match("/['\"]".preg_quote($n, '/')."['\"]/", $codigo)));
        $this->assertSame([], $sobran, 'Íconos que la app no usa: '.implode(', ', $sobran));
    }

    public function test_los_iconos_son_de_lucide_con_su_licencia(): void
    {
        $componente = (string) file_get_contents(resource_path('views/components/icono.blade.php'));
        $this->assertStringContainsString('Lucide 1.56', $componente);
        $this->assertStringContainsString('viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"', $componente);
        $leeme = (string) file_get_contents(base_path('docs/marca/LEEME.md'));
        $this->assertStringContainsString('Lucide 1.56', $leeme);
        $this->assertStringContainsString('**ISC**', $leeme);
        $this->assertStringContainsString('ISC License', (string) file_get_contents(base_path('docs/marca/licencia-iconos.txt')));
    }

    /** Valor de un token de app.css (la primera vez que aparece desde $desde). */
    private function token(string $css, string $nombre, int $desde = 0): string
    {
        preg_match('/'.preg_quote($nombre, '/').':\s*(#[0-9a-f]{6});/i', substr($css, $desde), $m);

        return strtolower($m[1] ?? '');
    }

    private static function contraste(string $a, string $b): float
    {
        $lum = function (string $hex): float {
            [$r, $g, $b] = array_map(fn ($i) => hexdec(substr($hex, $i, 2)) / 255, [1, 3, 5]);
            $f = fn ($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;

            return 0.2126 * $f($r) + 0.7152 * $f($g) + 0.0722 * $f($b);
        };
        [$alta, $baja] = [max($lum($a), $lum($b)), min($lum($a), $lum($b))];

        return ($alta + 0.05) / ($baja + 0.05);
    }

    public function test_los_colores_de_estado_no_cambiaron_y_los_de_area_se_leen(): void
    {
        $css = (string) file_get_contents(resource_path('css/app.css'));
        $noche = strpos($css, 'body:has(.tema-noche) :is(main, body > nav, dialog)');
        $cabecera = strpos($css, ".cabecera {\n");

        // Estados (bien, atención, alerta, información), en el día y en la noche: los mismos de antes
        foreach (['--color-bien' => ['#0f655f', '#7fd8c9'], '--color-atencion' => ['#8a3b0b', '#f2c46e'], '--color-alerta' => ['#9b1c1c', '#ff9d92'], '--color-info' => ['#1e40af', '#a9c4ff']] as $token => [$dia, $oscuro]) {
            $this->assertSame($dia, $this->token($css, $token), $token);
            $this->assertSame($oscuro, $this->token($css, $token, $noche), $token.' (noche)');
        }

        foreach (array_keys(Areas::AREAS) as $area) {
            $claro = $this->token($css, "--color-area-{$area}");
            foreach (['papel' => '#f4f7f6', 'ficha' => '#ffffff'] as $fondo => $color) {
                $this->assertGreaterThanOrEqual(4.5, self::contraste($claro, $color), "{$area} sobre {$fondo}");
            }
            foreach (['noche' => [$noche, '#0f2523'], 'cabecera' => [$cabecera, '#0f2a2a']] as $donde => [$desde, $fondo]) {
                $oscuro = $this->token($css, "--color-area-{$area}", $desde);
                $this->assertNotSame('', $oscuro, "{$area} en {$donde}");
                $this->assertGreaterThanOrEqual(4.5, self::contraste($oscuro, $fondo), "{$area} en {$donde}");
            }
        }
    }

    public function test_cada_pantalla_tiene_area_o_es_neutra_a_proposito(): void
    {
        // No son pantallas: descargas, JSON, fotos y comprobaciones
        $noSonPantallas = ['admin.datos.descargar', '*.excel', '*.pdf', 'asistente.*', 'campo.paquete', 'campo.token', 'especies.fotos.ver', 'salud', 'sanctum.*'];
        $sinClasificar = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($ruta) => in_array('GET', $ruta->methods(), true) && $ruta->getName())
            ->map(fn ($ruta) => $ruta->getName())
            ->reject(fn (string $nombre) => Str::is($noSonPantallas, $nombre) || Areas::clasificada($nombre))
            ->values()->all();

        $this->assertSame([], $sinClasificar, 'Pantallas sin área en App\Support\Areas (se ven con el estilo neutro): '.implode(', ', $sinClasificar));
        $this->assertSame('personal', Areas::deRuta('admin.jornaleros.index'));   // Jornaleros va en Personal
        $this->assertNull(Areas::deRuta('una.pantalla.nueva'));                     // sin clasificar: neutra, sin romperse
    }

    public function test_el_menu_y_las_pantallas_llevan_su_area_sin_depender_solo_del_color(): void
    {
        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($finca->id);
        $persona = tap(User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);

        // Pantalla con área: el <main>, el recuadro del título y las entradas del menú
        $ventas = $this->actingAs($persona)->get(route('admin.ventas.index'))->assertOk()->getContent();
        $enlace = fn (string $ruta, string $despues) => '#href="'.preg_quote(route($ruta), '#').'"\s*'.$despues.'#';
        $this->assertMatchesRegularExpression('#<main id="contenido"\s+data-area="ventas"#', $ventas);
        $this->assertStringContainsString('data-chip-area="ventas"', $ventas);
        $this->assertMatchesRegularExpression($enlace('admin.jornaleros.index', 'data-area="personal"'), $ventas);
        $this->assertMatchesRegularExpression($enlace('admin.bodega.index', 'data-area="bodega"'), $ventas);
        // La pantalla actual del menú se marca también sin color: aria-current (con negrita y raya)
        $this->assertMatchesRegularExpression($enlace('admin.ventas.index', 'data-area="ventas"\s+aria-current="page"'), $ventas);

        // Pantalla neutra: sin área ni recuadro, y se ve igual que siempre
        $inicio = $this->actingAs($persona)->get(route('inicio'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('#<main id="contenido"\s+class=#', $inicio);
        $this->assertStringNotContainsString('data-chip-area', $inicio);
        $this->assertMatchesRegularExpression($enlace('inicio', 'aria-current="page"'), $inicio);
    }
}
