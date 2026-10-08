<?php

namespace Tests\Feature;

use App\Models\Especie;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guía de especies: muestra las especies del catálogo de la finca (nunca una lista fija).
 */
class EspeciesTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        $tenant = Tenant::firstOrCreate(['codigo' => 'F01'], ['nombre' => 'Finca']);
        app(TenantActual::class)->establecer($tenant->id);

        return tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([$rol]);
    }

    public function test_la_guia_lista_las_especies_activas_de_la_finca(): void
    {
        $trabajador = $this->usuario(Rol::TRABAJADOR);
        Especie::deClave('bocachico')->update(['activo' => false]);
        $propia = Especie::create(['nombre' => 'Sábalo', 'clima' => Especie::CALIDO]);

        $respuesta = $this->actingAs($trabajador)->get(route('especies.index'))->assertOk();

        foreach (Especie::query()->activas()->get() as $especie) {
            $respuesta->assertSee($especie->nombre)->assertSee(route('especies.show', $especie));
        }
        $respuesta->assertSee('Sábalo')->assertSee(route('especies.show', $propia))->assertDontSee('Bocachico');
    }

    public function test_muestra_la_ficha_de_una_especie(): void
    {
        $administrador = $this->usuario(Rol::ADMINISTRADOR);

        $this->actingAs($administrador)->get(route('especies.show', Especie::deClave('bagre rayado')))
            ->assertOk()
            ->assertSee('Pseudoplatystoma magdaleniatum')
            ->assertSee('/images/especies/bagre-rayado.webp', false)
            // Crédito de la foto de referencia: nota, autor, licencia y enlace a la fuente
            ->assertSee(__('especies.foto_credito.notas.mismo_genero'))
            ->assertSee(__('especies.foto_credito.autor', ['tipo' => 'Foto', 'autor' => 'Whaldener Endo']))
            ->assertSee('href="https://creativecommons.org/publicdomain/zero/1.0/"', false)
            ->assertSee('href="https://commons.wikimedia.org/wiki/File:Pseudoplaystoma_duo.jpg"', false);
    }


    public function test_el_pie_de_cada_imagen_de_referencia_dice_lo_que_es(): void
    {
        $administrador = $this->usuario(Rol::ADMINISTRADOR);
        $ficha = fn (string $clave) => $this->actingAs($administrador)->get(route('especies.show', Especie::deClave($clave)))->assertOk();

        // Mojarra negra: es una ilustración, no una foto
        $ficha('mojarra negra')
            ->assertSee(__('especies.foto_credito.autor', ['tipo' => 'Ilustración', 'autor' => 'Duane Raver, U.S. Fish and Wildlife Service']))
            ->assertSee(__('especies.foto_credito.dominio_publico'))
            ->assertDontSee(__('especies.foto_credito.modificada'));

        // CC BY-SA y CC BY: autor, licencia con enlace a su texto, enlace a la fuente y aviso de imagen modificada
        $ficha('mojarra roja')
            ->assertSee(__('especies.foto_credito.autor', ['tipo' => 'Foto', 'autor' => 'Takeaway']))
            ->assertSee('href="https://creativecommons.org/licenses/by-sa/3.0/"', false)->assertSee('CC BY-SA 3.0')
            ->assertSee('href="https://commons.wikimedia.org/wiki/File:Pla_thapthim.jpg"', false)
            ->assertSee(__('especies.foto_credito.modificada'));
        $ficha('bocachico')
            ->assertSee('Zuluaga-Gómez A., Ictiología Universidad Católica de Oriente')
            ->assertSee('href="https://creativecommons.org/licenses/by/2.0/"', false)->assertSee('CC BY 2.0')
            ->assertSee('href="https://commons.wikimedia.org/wiki/File:Prochilodus_magdalenae_(16197296986).jpg"', false)
            ->assertSee(__('especies.foto_credito.modificada'));

        // Notas: el bagre no es la especie exacta; la trucha es un ejemplar juvenil
        $ficha('bagre rayado')->assertSee(__('especies.foto_credito.notas.mismo_genero'));
        $ficha('trucha arcoiris')->assertSee(__('especies.foto_credito.notas.juvenil'));
    }

    public function test_cada_foto_de_referencia_existe_es_liviana_y_tiene_autor_licencia_y_fuente(): void
    {
        $this->usuario(Rol::ADMINISTRADOR);
        $leeme = (string) file_get_contents(base_path('docs/marca/LEEME.md'));

        foreach (Especie::query()->whereNotNull('clave')->get() as $especie) {
            $credito = $especie->creditoFoto();
            $this->assertNotNull($credito, $especie->nombre);
            $this->assertSame($credito['ruta'], $especie->foto());
            $this->assertFileExists(public_path(ltrim($credito['ruta'], '/')));
            $this->assertLessThan(200 * 1024, filesize(public_path(ltrim($credito['ruta'], '/'))), $credito['ruta']);
            $this->assertNotSame('', trim($credito['autor']));
            $this->assertNotSame('', trim($credito['licencia']));
            $this->assertStringStartsWith('https://commons.wikimedia.org/wiki/File:', $credito['fuente']);
            // Anotada en el LEEME de la marca
            $this->assertStringContainsString(basename($credito['ruta']), $leeme);
        }
    }

    public function test_especie_inexistente_da_404(): void
    {
        $this->actingAs($this->usuario(Rol::PROPIETARIO))->get(route('especies.show', 999999))->assertNotFound();
    }

    public function test_todos_los_roles_de_la_finca_ven_la_guia(): void
    {
        foreach ([Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $usuario = $this->usuario($rol);
            $this->actingAs($usuario)->get(route('especies.index'))->assertOk();
            $this->actingAs($usuario)->get(route('especies.show', Especie::deClave('trucha arcoiris')))->assertOk()->assertSee(__('especies.ficha.procesamiento'));
        }
    }

    public function test_las_especies_por_defecto_traen_su_ficha_de_referencia_y_su_foto_existe(): void
    {
        $this->usuario(Rol::PROPIETARIO);
        $campos = ['nombre', 'cientifico', 'clima', 'temperatura', 'oxigeno_min', 'ph', 'alimentacion', 'color', 'aspecto',
            'proteina', 'meses_cosecha', 'peso_comercial', 'policultivo'];

        $especies = Especie::query()->get();
        $this->assertCount(6, $especies);
        foreach ($especies as $especie) {
            $ficha = $especie->ficha();
            foreach ($campos as $campo) {
                $this->assertNotEmpty($ficha[$campo], "Falta {$campo} en {$especie->clave}.");
            }
            $this->assertContains($ficha['clima'], Especie::CLIMAS);
            // Cultivo y procesamiento completos y marcados como orientativos
            $this->assertTrue($ficha['orientativa']);
            foreach ([...array_values($ficha['cultivo']), ...array_values($ficha['procesamiento'])] as $texto) {
                $this->assertNotEmpty($texto, "Falta una parte de la ficha en {$especie->clave}.");
            }
            if ($ficha['foto'] !== null) {
                $this->assertFileExists(public_path(ltrim($ficha['foto'], '/')));
            }
        }
    }

    public function test_una_especie_sin_foto_se_muestra_sin_error_en_todas_sus_vistas(): void
    {
        // Una especie que agrega la finca no tiene foto de referencia: hasta que suba una, ninguna
        $administrador = $this->usuario(Rol::ADMINISTRADOR);
        $especie = Especie::create(['nombre' => 'Sábalo', 'clima' => Especie::CALIDO]);
        $this->assertNull($especie->foto());
        $this->assertNull($especie->creditoFoto());
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $especie->id, 'fecha' => now(), 'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 5, 'biomasa_kg' => 5]);

        // Guía y ficha: recuadro con el texto «Sin foto todavía» (la guía también muestra las fotos de referencia de las demás)
        foreach ([route('especies.index'), route('especies.show', $especie)] as $url) {
            $html = $this->actingAs($administrador)->get($url)->assertOk()->assertSee(__('especies.foto_pendiente'))->getContent();
            $this->assertSinImagenesRotas($html);
        }

        // Tarjeta del lago y detalle: el ícono del pez en lugar de la foto
        foreach ([route('admin.lagos.index'), route('admin.lagos.show', $lago)] as $url) {
            $html = $this->actingAs($administrador)->get($url)->assertOk()->assertSee('Sábalo')->getContent();
            $this->assertSinImagenesRotas($html);
        }
    }

    /** Ninguna <img> sin dirección ni apuntando a un archivo que no existe en public/. */
    private function assertSinImagenesRotas(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*\ssrc=""/', $html);
        preg_match_all('/<img[^>]*\ssrc="([^"]+)"/', $html, $imagenes);
        foreach ($imagenes[1] as $src) {
            if (str_starts_with($src, '/') && ! str_starts_with($src, '//')) {
                $this->assertFileExists(public_path(parse_url($src, PHP_URL_PATH)), "Imagen rota: {$src}");
            }
        }
    }
}
