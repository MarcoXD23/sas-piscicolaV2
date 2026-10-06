<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CatalogoEspecies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspeciesTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        $tenant = Tenant::firstOrCreate(['codigo' => 'F01'], ['nombre' => 'Finca']);

        return tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([$rol]);
    }

    public function test_la_guia_lista_todas_las_especies_del_catalogo(): void
    {
        $respuesta = $this->actingAs($this->usuario(Rol::TRABAJADOR))->get(route('especies.index'))->assertOk();

        foreach (CatalogoEspecies::todas() as $especie) {
            $respuesta->assertSee($especie['nombre'])->assertSee(route('especies.show', $especie['slug']));
        }
    }

    public function test_muestra_la_ficha_de_una_especie(): void
    {
        $this->actingAs($this->usuario(Rol::ADMINISTRADOR))->get(route('especies.show', 'bagre-rayado'))
            ->assertOk()
            ->assertSee('Pseudoplatystoma magdaleniatum')
            ->assertSee('/images/especies/bagre-rayado.webp', false);
    }

    public function test_especie_inexistente_da_404(): void
    {
        $this->actingAs($this->usuario(Rol::PROPIETARIO))->get(route('especies.show', 'no-existe'))->assertNotFound();
    }

    public function test_el_celador_no_accede_a_la_guia(): void
    {
        $this->actingAs($this->usuario(Rol::CELADOR))->get(route('especies.index'))->assertForbidden();
    }

    public function test_cada_ficha_tiene_los_datos_obligatorios_y_su_foto_existe(): void
    {
        $campos = ['nombre', 'cientifico', 'clima', 'temperatura', 'oxigeno_min', 'ph', 'densidad_tierra',
            'densidad_tanque', 'proteina', 'meses_cosecha', 'peso_comercial', 'policultivo', 'manejo'];

        foreach (CatalogoEspecies::todas() as $clave => $especie) {
            foreach ($campos as $campo) {
                $this->assertNotEmpty($especie[$campo], "Falta {$campo} en {$clave}.");
            }

            $this->assertContains($especie['clima'], [CatalogoEspecies::CLIMA_CALIDO, CatalogoEspecies::CLIMA_FRIO]);

            if ($especie['foto'] !== null) {
                $this->assertFileExists(public_path(ltrim($especie['foto'], '/')));
            }
        }
    }
}
