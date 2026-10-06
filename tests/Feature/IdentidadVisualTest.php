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
            ->assertSee('href="/icono.svg"', false)
            ->assertSee('href="/icons/apple-touch-icon.png"', false)
            ->assertSee('/images/login-fondo.webp', false);
    }
}
