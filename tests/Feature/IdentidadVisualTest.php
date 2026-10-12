<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $finca = \App\Models\Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(\App\Support\TenantActual::class)->establecer($finca->id);
        $propietario = tap(\App\Models\User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([\App\Models\Rol::PROPIETARIO]);
        $this->assertSinImagenesInexistentes($this->actingAs($propietario)->get(route('inicio'))->assertOk()->assertSee('/images/fondo-estanque.webp', false)->getContent());
    }

    public function test_el_service_worker_guarda_las_imagenes_y_estrena_version(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        // Cada versión borra las copias anteriores (la v9 retiró los fondos SVG; la v10 agregó Registro rápido; la v11, su acceso en offline.html;
        // la v12 estrena la libreta de campo; la v13, la tipografía Nunito Sans)
        $this->assertStringContainsString("const VERSION = 'v13'", $sw);
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
}