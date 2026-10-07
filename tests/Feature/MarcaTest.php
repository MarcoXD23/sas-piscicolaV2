<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ícono y marca (trucha arcoíris con corona): los archivos que carga la app existen, pesan menos de 200 KB
 * y tienen la medida declarada; el original pesado se queda en docs/marca.
 */
class MarcaTest extends TestCase
{
    use RefreshDatabase;

    private const LIMITE = 200 * 1024;

    private function manifest(): array
    {
        return json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_el_manifest_apunta_a_los_iconos_de_la_marca_incluido_el_maskable(): void
    {
        $iconos = collect($this->manifest()['icons']);

        $this->assertSame(['/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.webp'], $iconos->pluck('src')->all());
        $this->assertSame('maskable', $iconos->firstWhere('src', '/icons/icon-maskable-512.webp')['purpose']);
        $this->assertSame('image/webp', $iconos->firstWhere('src', '/icons/icon-maskable-512.webp')['type']);

        foreach ($iconos as $icono) {
            $ruta = public_path(ltrim($icono['src'], '/'));
            $this->assertFileExists($ruta);
            $this->assertLessThan(self::LIMITE, filesize($ruta), "{$icono['src']} supera 200 KB");
            $info = getimagesize($ruta);
            $this->assertSame($icono['sizes'], "{$info[0]}x{$info[1]}");
            $this->assertSame($icono['type'], $info['mime']);
        }
    }

    public function test_el_layout_y_el_login_usan_el_favicon_el_apple_touch_icon_y_el_logo(): void
    {
        $html = $this->get(route('login'))->assertOk()
            ->assertSee('href="/favicon.ico"', false)
            ->assertSee('href="/icons/icon-192.png"', false)
            ->assertSee('href="/icons/apple-touch-icon.png"', false)
            ->assertSee('src="/icons/icon-192.png"', false)
            ->assertSee('alt="'.__('layout.logo_alt').'"', false)
            ->getContent();

        // Cada archivo de ícono que referencia la página existe y pesa menos de 200 KB
        preg_match_all('/(?:href|src)="(\/(?:favicon\.ico|icons\/[^"]+))"/', $html, $m);
        $this->assertNotEmpty($m[1]);
        foreach (array_unique($m[1]) as $ruta) {
            $archivo = public_path(ltrim($ruta, '/'));
            $this->assertFileExists($archivo);
            $this->assertLessThan(self::LIMITE, filesize($archivo), "{$ruta} supera 200 KB");
        }

        [$ancho, $alto] = getimagesize(public_path('icons/apple-touch-icon.png'));
        $this->assertSame([180, 180], [$ancho, $alto]);
    }

    public function test_el_favicon_trae_16_32_y_48_px(): void
    {
        $ico = file_get_contents(public_path('favicon.ico'));
        $cantidad = unpack('v', substr($ico, 4, 2))[1];
        $medidas = [];
        for ($i = 0; $i < $cantidad; $i++) {
            $medidas[] = ord($ico[6 + 16 * $i]) ?: 256;
        }
        sort($medidas);

        $this->assertSame([16, 32, 48], $medidas);
        $this->assertLessThan(self::LIMITE, strlen($ico));
    }

    public function test_el_original_se_queda_en_docs_y_la_app_nunca_lo_carga(): void
    {
        $this->assertFileExists(base_path('docs/marca/icono-original.png'));
        $this->assertFileDoesNotExist(public_path('icono-original.png'));
        $this->assertFileDoesNotExist(public_path('icons/icono-original.png'));

        foreach ([resource_path('views'), resource_path('js'), public_path()] as $carpeta) {
            $archivos = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS));
            foreach ($archivos as $archivo) {
                if (in_array($archivo->getExtension(), ['php', 'js', 'html', 'webmanifest', 'css'], true)) {
                    $contenido = file_get_contents($archivo->getPathname());
                    $this->assertStringNotContainsString('icono-original', $contenido, "{$archivo->getFilename()} carga el original");
                    // Tampoco quedan los íconos provisionales anteriores
                    $this->assertStringNotContainsString('icono.svg', $contenido, "{$archivo->getFilename()} usa el ícono viejo");
                    $this->assertStringNotContainsString('/icons/icono-', $contenido, "{$archivo->getFilename()} usa el ícono viejo");
                }
            }
        }
    }
}
