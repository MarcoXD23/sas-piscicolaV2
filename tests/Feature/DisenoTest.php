<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisenoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string ...$roles): User
    {
        $tenant = Tenant::firstOrCreate(['codigo' => 'F01'], ['nombre' => 'Finca']);
        $usuario = User::factory()->create(['tenant_id' => $tenant->id]);
        $usuario->asignarRoles($roles);

        return $usuario;
    }

    public function test_el_menu_muestra_solo_los_modulos_del_rol(): void
    {
        $this->actingAs($this->usuario(Rol::PROPIETARIO))->get(route('inicio'))
            ->assertSee(route('admin.lagos.index'))
            ->assertSee(route('admin.bodega.index'))
            ->assertSee(route('admin.personal.index'));

        $this->actingAs($this->usuario(Rol::TRABAJADOR))->get(route('inicio'))
            ->assertSee(route('admin.lagos.index'))
            ->assertDontSee(route('admin.bodega.index'))
            ->assertDontSee(route('admin.personal.index'));
    }

    public function test_el_celador_no_tiene_menu_inferior_porque_solo_tiene_inicio(): void
    {
        $this->actingAs($this->usuario(Rol::CELADOR))->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('Principal (móvil)')
            ->assertDontSee(route('admin.lagos.index'));
    }

    public function test_la_ventana_del_formulario_se_reabre_si_hay_errores(): void
    {
        $propietario = $this->usuario(Rol::PROPIETARIO);

        $this->actingAs($propietario)
            ->from(route('admin.lagos.index'))
            ->post(route('admin.lagos.store'), ['nombre' => 'L01', 'tipo' => 'inventado', '_modal' => 'modal-lago'])
            ->assertRedirect(route('admin.lagos.index'));

        // Sin lagos, la única ventana de la página es la de "Nuevo lago"
        $this->actingAs($propietario)->get(route('admin.lagos.index'))
            ->assertOk()
            ->assertSee('data-abrir-al-cargar', false);
    }

    public function test_sin_errores_ninguna_ventana_se_abre_sola(): void
    {
        $this->actingAs($this->usuario(Rol::PROPIETARIO))->get(route('admin.lagos.index'))
            ->assertOk()
            ->assertDontSee('data-abrir-al-cargar', false);
    }

    public function test_la_tarjeta_del_lago_muestra_la_foto_de_la_especie(): void
    {
        $propietario = $this->usuario(Rol::PROPIETARIO);
        app(TenantActual::class)->establecer($propietario->tenant_id);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create([
            'lago_id' => $lago->id,
            'especie' => 'cachama blanca',
            'fecha' => now(),
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
            'peces_vivos' => 1000,
            'peso_actual_g' => 5,
            'biomasa_kg' => 5,
        ]);

        $this->actingAs($propietario)->get(route('admin.lagos.index'))
            ->assertOk()
            ->assertSee('/images/especies/cachama-blanca.webp', false);
    }

    public function test_foto_de_especie_es_null_cuando_no_hay_imagen(): void
    {
        $this->assertSame('/images/especies/bagre-rayado.webp', Siembra::fotoEspecie('Bagre rayado'));
        $this->assertNull(Siembra::fotoEspecie('especie sin foto'));
    }
}
