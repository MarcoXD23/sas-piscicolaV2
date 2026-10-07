<?php

namespace Tests\Feature;

use App\Models\Especie;
use App\Models\EspecieFoto;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Fotos\LimpiadorMetadatos;
use App\Support\Fotos\ProcesadorFoto;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Fotos reales de especies: contenido verificado, original sin metadatos y sin recomprimir, versión ligera,
 * máximo 5, topes, permisos, aislamiento entre fincas y borrado de archivos.
 * Las imágenes de prueba las genera la prueba con GD (no hay imágenes de prueba en el repositorio).
 */
class EspecieFotosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private User $propietario;

    private User $trabajador;

    private Especie $especie;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('fotos');

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->especie = Especie::deClave('mojarra roja');
    }

    // --- Imágenes de prueba ---

    private function lienzo(int $ancho, int $alto, bool $ruido = false): \GdImage
    {
        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, imagecolorallocate($imagen, 200, 60, 40));
        imagefilledrectangle($imagen, 0, 0, intdiv($ancho, 3), $alto, imagecolorallocate($imagen, 20, 120, 200));
        if ($ruido) {
            mt_srand(7);
            for ($i = 0; $i < 4000; $i++) {
                $x = mt_rand(0, $ancho - 1);
                $y = mt_rand(0, $alto - 1);
                imagefilledrectangle($imagen, $x, $y, $x + mt_rand(2, 40), $y + mt_rand(2, 40), imagecolorallocate($imagen, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }

        return $imagen;
    }

    /** APP1 EXIF (little endian) con la marca del teléfono, una coordenada GPS falsa y la orientación. */
    private function exifConDatosPersonales(int $orientacion): string
    {
        $marca = "TelefonoSecreto X1\0";
        $gps = "GPS 4.6097N 74.0817W\0";
        // IFD0 con 3 entradas: Make (0x010F), ImageDescription (0x010E, aquí con la «ubicación») y Orientation (0x0112)
        $inicioDatos = 8 + 2 + 3 * 12 + 4;
        $ifd = pack('v', 3)
            .pack('vvVV', 0x010E, 2, strlen($gps), $inicioDatos)
            .pack('vvVV', 0x010F, 2, strlen($marca), $inicioDatos + strlen($gps))
            .pack('vvVvv', 0x0112, 3, 1, $orientacion, 0)
            .pack('V', 0);
        $tiff = 'II'.pack('vV', 42, 8).$ifd.$gps.$marca;
        $datos = "Exif\0\0".$tiff;

        return "\xFF\xE1".pack('n', strlen($datos) + 2).$datos;
    }

    /** JPEG real con EXIF (marca, GPS, orientación), XMP y un comentario. */
    private function jpegConMetadatos(int $ancho = 300, int $alto = 200, int $orientacion = 6): string
    {
        ob_start();
        imagejpeg($this->lienzo($ancho, $alto), null, 90);
        $jpeg = (string) ob_get_clean();

        $xmpDatos = "http://ns.adobe.com/xap/1.0/\0<x:xmpmeta><geo:lat>4.6097</geo:lat><dc:creator>Persona Privada</dc:creator></x:xmpmeta>";
        $xmp = "\xFF\xE1".pack('n', strlen($xmpDatos) + 2).$xmpDatos;
        $comentario = "\xFF\xFE".pack('n', strlen('Comentario privado') + 2).'Comentario privado';

        // Justo después de SOI (y del JFIF que deja GD)
        $finJfif = 2 + 2 + unpack('n', substr($jpeg, 4, 2))[1];

        return substr($jpeg, 0, $finJfif).$this->exifConDatosPersonales($orientacion).$xmp.$comentario.substr($jpeg, $finJfif);
    }

    private function pngConTexto(): string
    {
        ob_start();
        imagepng($this->lienzo(120, 80));
        $png = (string) ob_get_clean();
        $texto = "Author\0Persona Privada";
        $bloque = pack('N', strlen($texto)).'tEXt'.$texto.pack('N', crc32('tEXt'.$texto));

        return substr($png, 0, 33).$bloque.substr($png, 33);   // después de la firma (8) y el IHDR (25)
    }

    private function archivo(string $bytes, string $nombre = 'foto.jpg', string $tipo = 'image/jpeg'): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'foto');
        file_put_contents($ruta, $bytes);

        return new UploadedFile($ruta, $nombre, $tipo, null, true);
    }

    private function subir(UploadedFile $archivo, array $datos = [], ?User $quien = null): TestResponse
    {
        return $this->actingAs($quien ?? $this->administrador)->post(route('admin.especies.fotos.store', $this->especie), ['foto' => $archivo] + $datos);
    }

    // --- Subida y procesamiento ---

    public function test_una_foto_jpeg_guarda_el_original_sin_metadatos_y_sin_recomprimir_y_una_version_ligera(): void
    {
        $bytes = $this->jpegConMetadatos();
        $this->subir($this->archivo($bytes), ['autor' => 'Ana Campo', 'licencia' => 'CC BY 4.0'])->assertSessionHasNoErrors();

        $foto = EspecieFoto::sole();
        $disco = Storage::disk('fotos');
        $original = $disco->get($foto->ruta_original);

        // Nombres aleatorios, en la carpeta de la finca y la especie
        $this->assertMatchesRegularExpression('#^'.$this->finca->id.'/'.$this->especie->id.'/[0-9a-f]{40}\.jpg$#', $foto->ruta_original);
        $this->assertMatchesRegularExpression('#^'.$this->finca->id.'/'.$this->especie->id.'/[0-9a-f]{40}\.webp$#', $foto->ruta_ligera);

        // Sin datos personales ni ubicación…
        foreach (['TelefonoSecreto', 'GPS 4.6097', 'geo:lat', 'Persona Privada', 'Comentario privado'] as $privado) {
            $this->assertStringNotContainsString($privado, $original);
        }
        // …pero con la orientación y los MISMOS datos de imagen (no se recomprimió)
        $this->assertSame(6, LimpiadorMetadatos::orientacionJpeg($original));
        $this->assertSame(substr($bytes, strpos($bytes, "\xFF\xDA")), substr($original, strpos($original, "\xFF\xDA")));
        $this->assertNotFalse(imagecreatefromstring($original));

        // Ligera: WebP real, ya girada (300×200 con orientación 6 queda 200×300), bajo 200 KB
        $ligera = $disco->get($foto->ruta_ligera);
        $info = getimagesizefromstring($ligera);
        $this->assertSame(['image/webp', 200, 300], [$info['mime'], $info[0], $info[1]]);
        $this->assertLessThan(200 * 1024, strlen($ligera));
        $this->assertSame([strlen($original), strlen($ligera), 300, 200, 1], [$foto->bytes_original, $foto->bytes_ligera, $foto->ancho, $foto->alto, $foto->orden]);
        $this->assertSame(['Ana Campo', 'CC BY 4.0', $this->administrador->id], [$foto->autor, $foto->licencia, $foto->user_id]);
    }

    public function test_png_sin_bloques_de_texto_y_foto_grande_reducida_bajo_200_kb(): void
    {
        $png = $this->pngConTexto();
        $this->subir($this->archivo($png, 'foto.png', 'image/png'))->assertSessionHasNoErrors();
        $original = Storage::disk('fotos')->get(EspecieFoto::sole()->ruta_original);
        $this->assertStringNotContainsString('Persona Privada', $original);
        $this->assertStringContainsString('IDAT', $original);
        $this->assertSame(substr($png, strpos($png, 'IDAT') - 4), substr($original, strpos($original, 'IDAT') - 4));

        // Una foto grande y con mucho detalle: la ligera baja a máximo 1600 px y menos de 200 KB
        ob_start();
        imagejpeg($this->lienzo(2400, 1800, ruido: true), null, 95);
        $grande = (string) ob_get_clean();
        $this->subir($this->archivo($grande))->assertSessionHasNoErrors();

        $ligera = Storage::disk('fotos')->get(EspecieFoto::query()->where('orden', 2)->value('ruta_ligera'));
        $info = getimagesizefromstring($ligera);
        $this->assertLessThanOrEqual(1600, max($info[0], $info[1]));
        $this->assertLessThan(200 * 1024, strlen($ligera));
        // El original conserva los mismos datos de imagen; solo pierde el comentario que deja GD («CREATOR: gd-jpeg»)
        $originalGrande = Storage::disk('fotos')->get(EspecieFoto::query()->where('orden', 2)->value('ruta_original'));
        $this->assertSame(substr($grande, strpos($grande, "\xFF\xDA")), substr($originalGrande, strpos($originalGrande, "\xFF\xDA")));
        $this->assertStringContainsString('CREATOR', $grande);
        $this->assertStringNotContainsString('CREATOR', $originalGrande);
    }

    public function test_webp_sin_bloques_exif_ni_xmp(): void
    {
        ob_start();
        imagewebp($this->lienzo(60, 40), null, 80);
        $webp = (string) ob_get_clean();
        // Se agrega un bloque EXIF y otro XMP al final del RIFF (y se corrige su tamaño)
        $extra = 'EXIF'.pack('V', 20).'GPS 4.6097N 74.0817W'.'XMP '.pack('V', 16).'<dc:creator>X</>';
        $conMetadatos = 'RIFF'.pack('V', strlen($webp) - 8 + strlen($extra)).substr($webp, 8).$extra;
        $this->assertNotFalse(imagecreatefromstring($conMetadatos));

        $limpio = LimpiadorMetadatos::limpiar($conMetadatos, 'image/webp');

        $this->assertSame($webp, $limpio);   // los mismos bytes de imagen, sin los bloques agregados
        $this->assertSame(strlen($limpio) - 8, unpack('V', substr($limpio, 4, 4))[1]);
        $this->subir($this->archivo($conMetadatos, 'foto.webp', 'image/webp'))->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('GPS 4.6097', Storage::disk('fotos')->get(EspecieFoto::sole()->ruta_original));
    }

    // --- Rechazos ---

    public function test_rechaza_archivos_falsos_svg_y_otros_formatos_por_su_contenido(): void
    {
        $this->subir($this->archivo('esto no es una foto, solo texto', 'falsa.jpg'))->assertInvalid(['foto' => 'no es una foto JPEG, PNG o WebP válida']);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(1)</script></svg>';
        $this->subir($this->archivo($svg, 'dibujo.svg', 'image/svg+xml'))->assertInvalid(['foto']);
        $this->subir($this->archivo($svg, 'dibujo.jpg', 'image/jpeg'))->assertInvalid(['foto']);
        ob_start();
        imagegif($this->lienzo(20, 20));
        $this->subir($this->archivo((string) ob_get_clean(), 'animada.gif', 'image/gif'))->assertInvalid(['foto']);

        $this->assertSame(0, EspecieFoto::count());
        $this->assertSame([], Storage::disk('fotos')->allFiles());
    }

    public function test_rechaza_mas_de_30_mb_y_dimensiones_excesivas_antes_de_decodificar(): void
    {
        $this->subir(UploadedFile::fake()->create('enorme.jpg', 31 * 1024, 'image/jpeg'))->assertInvalid(['foto' => 'pesa más de 30 MB']);

        // PNG de apenas unos bytes que en su cabecera dice medir 20.000 × 20.000: se rechaza sin decodificarlo
        $ihdr = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
        $png = "\x89PNG\r\n\x1A\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr)).pack('N', 0).'IEND'.pack('N', crc32('IEND'));
        $this->subir($this->archivo($png, 'trampa.png', 'image/png'))->assertInvalid(['foto' => 'La foto mide 20000 × 20000 píxeles']);

        // El tope sale de memory_limit y nunca pasa del máximo fijo
        $this->assertLessThanOrEqual(50_000_000, ProcesadorFoto::pixelesMax());
        $this->assertSame(0, EspecieFoto::count());
    }

    public function test_maximo_5_fotos_por_especie_en_la_aplicacion_y_en_la_base(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->subir($this->archivo($this->jpegConMetadatos(40, 30, 1)))->assertSessionHasNoErrors();
        }
        $this->subir($this->archivo($this->jpegConMetadatos(40, 30, 1)))->assertInvalid(['foto' => 'ya tiene 5 fotos']);
        $this->assertSame([1, 2, 3, 4, 5], EspecieFoto::query()->orderBy('orden')->pluck('orden')->all());
        $this->assertCount(10, Storage::disk('fotos')->allFiles());

        // La base tampoco acepta una sexta (orden de 1 a 5 y único por especie)
        $fila = EspecieFoto::first()->only(['especie_id', 'user_id', 'ruta_original', 'ruta_ligera', 'tipo_original', 'bytes_original', 'bytes_ligera', 'ancho', 'alto']);
        foreach ([6, 3] as $orden) {
            try {
                DB::transaction(function () use ($fila, $orden) {
                    EspecieFoto::create($fila + ['orden' => $orden]);
                    DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                });
                $this->fail("La base aceptó una foto con orden {$orden}");
            } catch (QueryException) {
            }
        }
    }

    public function test_tope_de_espacio_por_finca(): void
    {
        config(['fotos.tope_finca_mb' => 1]);
        EspecieFoto::create(['especie_id' => $this->especie->id, 'user_id' => $this->administrador->id, 'ruta_original' => 'x.jpg', 'ruta_ligera' => 'x.webp',
            'tipo_original' => 'image/jpeg', 'bytes_original' => 900 * 1024, 'bytes_ligera' => 50 * 1024, 'ancho' => 10, 'alto' => 10, 'orden' => 1]);

        $this->subir($this->archivo($this->jpegConMetadatos()))->assertInvalid(['foto' => 'La finca ya usa']);
        $this->assertSame(1, EspecieFoto::count());
    }

    // --- Permisos y aislamiento ---

    public function test_solo_el_administrador_sube_y_el_original_solo_lo_ven_propietario_y_administrador(): void
    {
        $this->subir($this->archivo($this->jpegConMetadatos()), [], $this->trabajador)->assertForbidden();
        $this->subir($this->archivo($this->jpegConMetadatos()), [], $this->propietario)->assertForbidden();
        $this->subir($this->archivo($this->jpegConMetadatos()))->assertSessionHasNoErrors();
        $foto = EspecieFoto::sole();

        $celador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::CELADOR]);
        foreach ([$this->trabajador, $celador, $this->propietario, $this->administrador] as $persona) {
            $this->actingAs($persona)->get($foto->url())->assertOk();
        }
        $this->actingAs($this->trabajador)->get($foto->urlOriginal())->assertForbidden();
        $this->actingAs($celador)->get($foto->urlOriginal())->assertForbidden();
        $this->actingAs($this->propietario)->get($foto->urlOriginal())->assertOk();
        $this->actingAs($this->administrador)->get($foto->urlOriginal())->assertOk();

        // Ordenar y borrar: solo el administrador
        $this->actingAs($this->propietario)->delete(route('admin.especies.fotos.destroy', [$this->especie, $foto]))->assertForbidden();
        // Sin sesión, nada
        auth()->logout();
        $this->get($foto->url())->assertRedirect(route('login'));
    }

    public function test_cabeceras_de_la_ruta_que_sirve_las_fotos(): void
    {
        $this->subir($this->archivo($this->jpegConMetadatos()))->assertSessionHasNoErrors();
        $foto = EspecieFoto::sole();

        $ligera = $this->actingAs($this->trabajador)->get($foto->url())->assertOk();
        $ligera->assertHeader('Content-Type', 'image/webp')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $ligera->headers->get('Cache-Control'));

        $this->actingAs($this->administrador)->get($foto->urlOriginal())->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs($this->administrador)->get(route('especies.fotos.ver', [$foto, 'cualquiera']))->assertNotFound();
    }

    public function test_aislamiento_otra_finca_no_ve_ni_borra_las_fotos(): void
    {
        $this->subir($this->archivo($this->jpegConMetadatos()))->assertSessionHasNoErrors();
        $foto = EspecieFoto::sole();

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $adminOtra = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $especieOtra = Especie::deClave('mojarra roja');

        $this->actingAs($adminOtra)->get($foto->url())->assertNotFound();
        $this->actingAs($adminOtra)->get($foto->urlOriginal())->assertNotFound();
        $this->actingAs($adminOtra)->delete(route('admin.especies.fotos.destroy', [$especieOtra, $foto]))->assertNotFound();
        $this->assertCount(2, Storage::disk('fotos')->allFiles());
    }

    // --- Orden, portada y borrado ---

    public function test_la_primera_foto_es_la_portada_y_borrar_quita_los_dos_archivos(): void
    {
        $bagre = Especie::deClave('bagre rayado');
        $this->assertSame('/images/especies/bagre-rayado.webp', $bagre->foto());   // sin fotos propias: la de referencia
        $this->assertNull($this->especie->foto());                                // la mojarra roja no tiene imagen

        $this->subir($this->archivo($this->jpegConMetadatos(40, 30, 1)), ['autor' => 'Primera'])->assertSessionHasNoErrors();
        $this->subir($this->archivo($this->jpegConMetadatos(40, 30, 1)), ['autor' => 'Segunda'])->assertSessionHasNoErrors();
        [$primera, $segunda] = EspecieFoto::query()->orderBy('orden')->get()->all();
        $this->assertSame($primera->url(), $this->especie->fresh()->foto());

        // Subir la segunda la vuelve portada
        $this->actingAs($this->administrador)->post(route('admin.especies.fotos.orden', [$this->especie, $segunda]), ['direccion' => 'subir'])->assertSessionHasNoErrors();
        $this->assertSame($segunda->url(), $this->especie->fresh()->foto());
        $this->actingAs($this->trabajador)->get(route('especies.index'))->assertSee($segunda->url(), false);

        // Borrar una foto quita sus dos archivos y renumera
        $this->actingAs($this->administrador)->delete(route('admin.especies.fotos.destroy', [$this->especie, $segunda]))->assertSessionHasNoErrors();
        Storage::disk('fotos')->assertMissing([$segunda->ruta_original, $segunda->ruta_ligera]);
        Storage::disk('fotos')->assertExists([$primera->ruta_original, $primera->ruta_ligera]);
        $this->assertSame(1, $primera->fresh()->orden);

        // Borrar una especie sin siembras borra los archivos de sus fotos
        $sabalo = Especie::create(['nombre' => 'Sábalo', 'clima' => Especie::CALIDO]);
        $this->actingAs($this->administrador)->post(route('admin.especies.fotos.store', $sabalo), ['foto' => $this->archivo($this->jpegConMetadatos(40, 30, 1))])->assertSessionHasNoErrors();
        $fotoSabalo = EspecieFoto::where('especie_id', $sabalo->id)->sole();
        $this->actingAs($this->administrador)->delete(route('admin.especies.destroy', $sabalo))->assertSessionHasNoErrors();
        Storage::disk('fotos')->assertMissing([$fotoSabalo->ruta_original, $fotoSabalo->ruta_ligera]);
        $this->assertNull(EspecieFoto::find($fotoSabalo->id));
    }

    public function test_el_autor_la_licencia_y_el_aviso_de_derechos_se_muestran(): void
    {
        $this->actingAs($this->administrador)->get(route('admin.especies.edit', $this->especie))->assertOk()
            ->assertSee('Sube solo fotos que hayas tomado tú o que tengas derecho a usar.');

        $this->subir($this->archivo($this->jpegConMetadatos()), ['autor' => 'Ana Campo', 'licencia' => 'CC BY 4.0'])->assertSessionHasNoErrors();

        $this->actingAs($this->trabajador)->get(route('especies.show', $this->especie))->assertOk()
            ->assertSee('Foto: Ana Campo')->assertSee('Licencia: CC BY 4.0')
            ->assertSee(EspecieFoto::sole()->url(), false)
            ->assertDontSee(EspecieFoto::sole()->urlOriginal(), false);   // el trabajador no recibe el enlace al original
        $this->actingAs($this->propietario)->get(route('especies.show', $this->especie))->assertSee(EspecieFoto::sole()->urlOriginal(), false);
    }

    public function test_una_especie_con_siembras_no_se_borra_ni_pierde_sus_fotos(): void
    {
        $this->subir($this->archivo($this->jpegConMetadatos()))->assertSessionHasNoErrors();
        $lago = \App\Models\Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie->id, 'fecha' => '2026-09-01', 'cantidad_alevinos' => 10,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 5, 'biomasa_kg' => 0.05]);

        $this->actingAs($this->administrador)->delete(route('admin.especies.destroy', $this->especie))->assertSessionHasErrors(['especie']);
        $this->assertSame(1, EspecieFoto::count());
        $this->assertCount(2, Storage::disk('fotos')->allFiles());
    }
}
