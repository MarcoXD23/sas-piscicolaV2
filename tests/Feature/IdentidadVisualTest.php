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
        // La app no trae fotos de fondo: el ingreso y el saludo usan un degradado de CSS
        $this->assertDirectoryDoesNotExist(public_path('images'));

        $this->assertSinImagenesInexistentes($this->get(route('login'))->assertOk()->getContent());

        $finca = \App\Models\Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(\App\Support\TenantActual::class)->establecer($finca->id);
        $propietario = tap(\App\Models\User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([\App\Models\Rol::PROPIETARIO]);
        $this->assertSinImagenesInexistentes($this->actingAs($propietario)->get(route('inicio'))->assertOk()->getContent());
    }

    public function test_el_service_worker_ya_no_guarda_imagenes_de_images(): void
    {
        $sw = file_get_contents(public_path('sw.js'));

        $this->assertStringContainsString("const VERSION = 'v7'", $sw);
        $this->assertStringContainsString("const ESTATICOS = ['/build/', '/icons/'];", $sw);
        $this->assertStringNotContainsString("'/images/'", $sw);
    }

    /** Ni /images/ ni ninguna imagen local (img, ícono o CSS url()) que no exista en public/. */
    private function assertSinImagenesInexistentes(string $html): void
    {
        $this->assertStringNotContainsString('/images/', $html);
        preg_match_all('/(?:src|href)="(\/[^"\/][^"]*\.(?:png|jpe?g|webp|gif|svg|ico))"|url\(["\']?(\/[^"\')]+)["\']?\)/i', $html, $rutas);
        foreach (array_filter(array_merge($rutas[1], $rutas[2])) as $ruta) {
            $this->assertFileExists(public_path(ltrim(parse_url($ruta, PHP_URL_PATH), '/')), "Imagen inexistente: {$ruta}");
        }
    }
}