<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\Jornada;
use App\Models\PesajeLimpio;
use App\Models\Presentacion;
use App\Models\Proceso;
use App\Models\Rol;
use App\Models\Tamano;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6b: catálogo de presentaciones (tamaños por especie, procesos y la tabla tamaño × proceso). El administrador edita,
 * el propietario consulta; arranca vacío; lo que ya se pesó no se borra: se desactiva.
 */
class PresentacionesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $administrador;

    private User $propietario;

    private int $cachama;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->administrador = $this->persona(Rol::ADMINISTRADOR);
        $this->propietario = $this->persona(Rol::PROPIETARIO);
        $this->cachama = $this->especie('cachama blanca');
    }

    private function persona(string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([$rol]);
    }

    private function catalogoBasico(): array
    {
        $this->actingAs($this->administrador)->post(route('admin.procesos.store'), ['nombre' => 'Descamado'])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.procesos.store'), ['nombre' => 'Entero lavado'])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.tamanos.store'), ['especie_id' => $this->cachama, 'nombre' => 'Grande', 'peso_min_g' => 800])->assertSessionHasNoErrors();

        return [Tamano::query()->sole(), Proceso::query()->orderBy('id')->get()];
    }

    public function test_el_catalogo_arranca_vacio(): void
    {
        $this->assertSame([0, 0, 0], [Tamano::query()->count(), Proceso::query()->count(), Presentacion::query()->count()]);

        $this->actingAs($this->administrador)->get(route('admin.presentaciones.index'))->assertOk()
            ->assertSee(__('presentaciones.sin_procesos'))->assertSee(__('presentaciones.ejemplo'));
    }

    public function test_el_administrador_arma_tamanos_procesos_y_la_tabla_con_cabuya(): void
    {
        [$grande, $procesos] = $this->catalogoBasico();
        $this->assertSame(800.0, $grande->peso_min_g);
        $this->assertSame(['Descamado', 'Entero lavado'], $procesos->pluck('nombre')->all());

        $this->actingAs($this->administrador)->put(route('admin.presentaciones.update', $this->cachama), [
            'marcadas' => [$grande->id => [$procesos[0]->id => '1']],
            'cabuya' => [$grande->id => [$procesos[0]->id => 'Roja', $procesos[1]->id => 'Azul']],
        ])->assertSessionHasNoErrors();

        $presentacion = Presentacion::query()->sole();
        $this->assertSame([$this->cachama, $grande->id, $procesos[0]->id, 'Roja', true],
            [$presentacion->especie_id, $presentacion->tamano_id, $presentacion->proceso_id, $presentacion->color_cabuya, $presentacion->activo]);
        $this->assertSame('Cachama blanca Grande Descamado', $presentacion->load(['especie', 'tamano', 'proceso'])->nombre());

        // Desmarcar la desactiva; nunca se borra
        $this->actingAs($this->administrador)->put(route('admin.presentaciones.update', $this->cachama), ['marcadas' => []])->assertSessionHasNoErrors();
        $this->assertFalse($presentacion->fresh()->activo);
        $this->assertSame(1, Presentacion::query()->count());
        $this->assertGreaterThan(0, Bitacora::query()->where('accion', 'presentaciones')->count());
    }

    public function test_nombres_repetidos_y_rango_invertido_no_se_aceptan(): void
    {
        $this->catalogoBasico();

        $this->actingAs($this->administrador)->post(route('admin.procesos.store'), ['nombre' => ' descamado '])->assertSessionHasErrors('nombre');
        $this->actingAs($this->administrador)->post(route('admin.tamanos.store'), ['especie_id' => $this->cachama, 'nombre' => 'GRANDE'])->assertSessionHasErrors('nombre');
        $this->actingAs($this->administrador)->post(route('admin.tamanos.store'), ['especie_id' => $this->cachama, 'nombre' => 'Mediana', 'peso_min_g' => 500, 'peso_max_g' => 300])
            ->assertSessionHasErrors('peso_min_g');
        // El mismo nombre en otra especie sí vale
        $this->actingAs($this->administrador)->post(route('admin.tamanos.store'), ['especie_id' => $this->especie('mojarra roja'), 'nombre' => 'Grande'])->assertSessionHasNoErrors();
    }

    public function test_lo_que_ya_se_peso_no_se_borra_se_desactiva(): void
    {
        [$grande, $procesos] = $this->catalogoBasico();
        $presentacion = Presentacion::create(['especie_id' => $this->cachama, 'tamano_id' => $grande->id, 'proceso_id' => $procesos[0]->id]);
        $jornada = Jornada::create(['fecha' => '2026-10-15', 'estado' => Jornada::ABIERTA, 'user_id' => $this->administrador->id]);
        PesajeLimpio::create(['jornada_id' => $jornada->id, 'presentacion_id' => $presentacion->id, 'tipo' => PesajeLimpio::TOTAL, 'bruto_kg' => 10, 'limpio_kg' => 10, 'user_id' => $this->administrador->id]);

        $this->actingAs($this->administrador)->delete(route('admin.tamanos.destroy', $grande))->assertSessionHasErrors('catalogo');
        $this->actingAs($this->administrador)->delete(route('admin.procesos.destroy', $procesos[0]))->assertSessionHasErrors('catalogo');
        $this->assertNotNull($grande->fresh());

        $this->actingAs($this->administrador)->put(route('admin.tamanos.update', $grande), ['nombre' => 'Grande', 'activo' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($grande->fresh()->activo);

        // Un proceso sin pesajes se borra con sus presentaciones
        Presentacion::create(['especie_id' => $this->cachama, 'tamano_id' => $grande->id, 'proceso_id' => $procesos[1]->id]);
        $this->actingAs($this->administrador)->delete(route('admin.procesos.destroy', $procesos[1]))->assertSessionHasNoErrors();
        $this->assertNull($procesos[1]->fresh());
        $this->assertSame(1, Presentacion::query()->count());
    }

    public function test_el_propietario_consulta_y_no_edita(): void
    {
        [$grande, $procesos] = $this->catalogoBasico();

        $this->actingAs($this->propietario)->get(route('admin.presentaciones.index'))->assertOk()
            ->assertSee(__('presentaciones.solo_lectura'))->assertSee('Descamado')
            ->assertDontSee(route('admin.procesos.store'), false);
        $this->actingAs($this->propietario)->post(route('admin.procesos.store'), ['nombre' => 'Otro'])->assertForbidden();
        $this->actingAs($this->propietario)->put(route('admin.procesos.update', $procesos[0]), ['nombre' => 'X', 'activo' => '1'])->assertForbidden();
        $this->actingAs($this->propietario)->delete(route('admin.tamanos.destroy', $grande))->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.tamanos.store'), ['especie_id' => $this->cachama, 'nombre' => 'X'])->assertForbidden();
        $this->actingAs($this->propietario)->put(route('admin.presentaciones.update', $this->cachama), ['marcadas' => []])->assertForbidden();

        foreach ([Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $this->actingAs($this->persona($rol))->get(route('admin.presentaciones.index'))->assertForbidden();
        }
        $this->assertSame(2, Proceso::query()->count());
    }

    public function test_la_especie_guarda_su_rendimiento_de_referencia(): void
    {
        $especie = Especie::query()->findOrFail($this->cachama);
        $datos = ['nombre' => $especie->nombre, 'clima' => $especie->clima, 'rendimiento_referencia' => 86];

        $this->actingAs($this->administrador)->put(route('admin.especies.update', $especie), $datos)->assertSessionHasNoErrors();
        $this->assertSame(86.0, $especie->fresh()->rendimiento_referencia);

        $this->actingAs($this->administrador)->put(route('admin.especies.update', $especie), ['rendimiento_referencia' => 120] + $datos)->assertSessionHasErrors('rendimiento_referencia');
    }
}
