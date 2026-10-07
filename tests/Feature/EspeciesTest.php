<?php

namespace Tests\Feature;

use App\Models\Especie;
use App\Models\Rol;
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
            ->assertSee('/images/especies/bagre-rayado.webp', false);
    }

    public function test_especie_inexistente_da_404(): void
    {
        $this->actingAs($this->usuario(Rol::PROPIETARIO))->get(route('especies.show', 999999))->assertNotFound();
    }

    public function test_el_celador_no_accede_a_la_guia(): void
    {
        $this->actingAs($this->usuario(Rol::CELADOR))->get(route('especies.index'))->assertForbidden();
    }

    public function test_las_especies_por_defecto_traen_su_ficha_de_referencia_y_su_foto_existe(): void
    {
        $this->usuario(Rol::PROPIETARIO);
        $campos = ['nombre', 'cientifico', 'clima', 'temperatura', 'oxigeno_min', 'ph', 'alimentacion', 'densidad_tierra',
            'densidad_tanque', 'proteina', 'meses_cosecha', 'peso_comercial', 'policultivo', 'manejo'];

        $especies = Especie::query()->get();
        $this->assertCount(6, $especies);
        foreach ($especies as $especie) {
            $ficha = $especie->ficha();
            foreach ($campos as $campo) {
                $this->assertNotEmpty($ficha[$campo], "Falta {$campo} en {$especie->clave}.");
            }
            $this->assertContains($ficha['clima'], Especie::CLIMAS);
            if ($ficha['foto'] !== null) {
                $this->assertFileExists(public_path(ltrim($ficha['foto'], '/')));
            }
        }
    }
}
