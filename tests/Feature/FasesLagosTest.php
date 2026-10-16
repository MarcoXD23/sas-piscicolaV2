<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\Fase;
use App\Models\Lago;
use App\Models\ReglaFase;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\EstadoFase;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FasesLagosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 09:00', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca Las Aguas', 'codigo' => 'FLA']);
        app(TenantActual::class)->establecer($this->finca->id);

        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))
            ->asignarRoles([Rol::PROPIETARIO]);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))
            ->asignarRoles([Rol::ADMINISTRADOR]);
    }

    public function test_toda_finca_nueva_arranca_con_las_cuatro_fases_y_reglas_de_forma_idempotente(): void
    {
        $nombres = Fase::query()->orderBy('orden')->pluck('nombre')->all();
        $this->assertSame(['Alevinaje', 'Precría', 'Levante', 'Engorde'], $nombres);
        $this->assertSame(24, ReglaFase::query()->count());

        // Volver a llamar cargarPorDefecto no duplica fases ni reglas
        Fase::cargarPorDefecto($this->finca->id);
        $this->assertSame(4, Fase::query()->count());
        $this->assertSame(24, ReglaFase::query()->count());
    }

    public function test_solo_el_propietario_puede_gestionar_las_fases_y_sus_reglas(): void
    {
        $fase = Fase::query()->firstOrFail();
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $celador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::CELADOR]);

        foreach ([$this->administrador, $trabajador, $celador] as $usuario) {
            $this->actingAs($usuario)->get(route('admin.fases.index'))->assertForbidden();
            $this->actingAs($usuario)->post(route('admin.fases.store'), ['nombre' => 'Finalización', 'orden' => 5])->assertForbidden();
            $this->actingAs($usuario)->put(route('admin.fases.update', $fase), ['nombre' => 'Otra', 'orden' => 1])->assertForbidden();
            $this->actingAs($usuario)->delete(route('admin.fases.destroy', $fase))->assertForbidden();
        }

        $this->actingAs($this->propietario)->get(route('admin.fases.index'))
            ->assertOk()
            ->assertSee('Alevinaje')
            ->assertSee('Engorde');
    }

    public function test_el_propietario_crea_edita_reordena_y_elimina_fases_sin_lagos(): void
    {
        $this->actingAs($this->propietario)
            ->post(route('admin.fases.store'), ['nombre' => 'Finalización', 'orden' => 5])
            ->assertRedirect(route('admin.fases.index'));

        $fase = Fase::query()->where('nombre', 'Finalización')->firstOrFail();
        $this->assertSame(5, $fase->orden);

        // Nombre repetido en la misma finca se rechaza
        $this->actingAs($this->propietario)
            ->post(route('admin.fases.store'), ['nombre' => 'Finalización', 'orden' => 6])
            ->assertSessionHasErrors('nombre');

        $this->actingAs($this->propietario)
            ->put(route('admin.fases.update', $fase), ['nombre' => 'Terminación', 'orden' => 6])
            ->assertRedirect(route('admin.fases.index'));

        $this->assertSame('Terminación', $fase->fresh()->nombre);
        $this->assertSame(6, $fase->fresh()->orden);

        // Si un lago usa la fase, no se puede eliminar
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra', 'fase_id' => $fase->id]);
        $this->actingAs($this->propietario)
            ->delete(route('admin.fases.destroy', $fase))
            ->assertSessionHasErrors('fase');
        $this->assertNotNull($fase->fresh());

        // Al quitar la fase del lago, ya se puede eliminar y queda en la bitácora
        $lago->update(['fase_id' => null]);
        $this->actingAs($this->propietario)
            ->delete(route('admin.fases.destroy', $fase))
            ->assertRedirect(route('admin.fases.index'));

        $this->assertDatabaseMissing('fases', ['id' => $fase->id]);
        $this->assertTrue(Bitacora::query()->where('accion', 'fase_eliminada')->exists());
    }

    public function test_el_propietario_edita_reglas_por_especie_y_valida_rangos_y_finca(): void
    {
        $fase = Fase::query()->where('nombre', 'Engorde')->firstOrFail();
        $especieId = $this->especie('trucha arcoiris');

        // Rango invertido se rechaza
        $this->actingAs($this->propietario)
            ->post(route('admin.fases.reglas.guardar', $fase), [
                'especie_id' => $especieId,
                'peso_entrada_g' => 500,
                'peso_salida_g' => 200,
            ])
            ->assertSessionHasErrors('peso_entrada_g');

        $this->actingAs($this->propietario)
            ->post(route('admin.fases.reglas.guardar', $fase), [
                'especie_id' => $especieId,
                'peso_entrada_g' => 100,
                'peso_salida_g' => 450,
                'dias_esperados' => 110,
                'supervivencia_porcentaje' => 94,
                'densidad_min_m2' => 10,
                'densidad_max_m2' => 25,
            ])
            ->assertRedirect(route('admin.fases.index'));

        $regla = ReglaFase::query()->where('fase_id', $fase->id)->where('especie_id', $especieId)->firstOrFail();
        $this->assertSame(450.0, $regla->peso_salida_g);
        $this->assertSame(110, $regla->dias_esperados);
        $this->assertTrue(Bitacora::query()->where('accion', 'regla_fase')->exists());

        // Especie de otra finca es rechazada
        $otraFinca = Tenant::create(['nombre' => 'Otra Finca', 'codigo' => 'OTR']);
        $especieAjena = Especie::withoutGlobalScopes()->where('tenant_id', $otraFinca->id)->firstOrFail();

        $this->actingAs($this->propietario)
            ->post(route('admin.fases.reglas.guardar', $fase), [
                'especie_id' => $especieAjena->id,
                'dias_esperados' => 60,
            ])
            ->assertSessionHasErrors('especie_id');
    }

    public function test_el_administrador_asigna_fase_y_area_al_crear_o_editar_lago_y_rechaza_fase_ajena(): void
    {
        $engorde = Fase::query()->where('nombre', 'Engorde')->firstOrFail();
        $levante = Fase::query()->where('nombre', 'Levante')->firstOrFail();

        $otraFinca = Tenant::create(['nombre' => 'Otra Finca', 'codigo' => 'OTR']);
        $faseAjena = Fase::withoutGlobalScopes()->where('tenant_id', $otraFinca->id)->firstOrFail();

        // No permite usar fase de otra finca al crear
        $this->actingAs($this->administrador)
            ->post(route('admin.lagos.store'), [
                'nombre' => 'Lago Ajeno',
                'tipo' => 'tierra',
                'fase_id' => $faseAjena->id,
            ])
            ->assertSessionHasErrors('fase_id');

        $this->actingAs($this->administrador)
            ->post(route('admin.lagos.store'), [
                'nombre' => 'Lago 1',
                'tipo' => 'geomembrana',
                'fase_id' => $levante->id,
                'area_m2' => 250.5,
            ])
            ->assertRedirect(route('admin.lagos.index'));

        $lago = Lago::query()->where('nombre', 'Lago 1')->firstOrFail();
        $this->assertSame($levante->id, $lago->fase_id);
        $this->assertSame(250.5, $lago->area_m2);

        // No permite cambiar a una fase de otra finca al editar
        $this->actingAs($this->administrador)
            ->put(route('admin.lagos.update', $lago), [
                'nombre' => 'Lago 1',
                'tipo' => 'geomembrana',
                'fase_id' => $faseAjena->id,
                'area_m2' => 250.5,
            ])
            ->assertSessionHasErrors('fase_id');

        // Actualiza fase y área del lago
        $this->actingAs($this->administrador)
            ->put(route('admin.lagos.update', $lago), [
                'nombre' => 'Lago 1A',
                'tipo' => 'concreto',
                'fase_id' => $engorde->id,
                'area_m2' => 300,
            ])
            ->assertRedirect(route('admin.lagos.show', $lago));

        $lago->refresh();
        $this->assertSame('Lago 1A', $lago->nombre);
        $this->assertSame($engorde->id, $lago->fase_id);
        $this->assertSame(300.0, $lago->area_m2);
        $this->assertTrue(Bitacora::query()->where('accion', 'lago_editado')->where('lago_id', $lago->id)->exists());
    }

    public function test_el_detalle_del_lago_y_el_paquete_de_campo_muestran_la_fase_y_la_comparacion_real_vs_esperado(): void
    {
        $engorde = Fase::query()->where('nombre', 'Engorde')->firstOrFail();
        $truchaId = $this->especie('trucha arcoiris');
        $especieNueva = Especie::create(['nombre' => 'Carpa espejo', 'clima' => Especie::FRIO, 'activo' => true]);

        $lago = Lago::create([
            'nombre' => 'E01',
            'tipo' => 'concreto',
            'fase_id' => $engorde->id,
            'area_m2' => 100,
        ]);

        // Lote con regla configurada (Trucha arcoíris en Engorde: 80 a 400 g en 100 días, 95% supervivencia, 12 a 25 peces/m²)
        $siembraConRegla = Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $truchaId,
            'fecha' => '2026-09-17', // 50 días de cultivo al 2026-11-06
            'cantidad_alevinos' => 1500,
            'peso_promedio_inicial_g' => 80,
            'peces_vivos' => 1440,
            'peso_actual_g' => 250,
            'biomasa_kg' => 360,
        ]);

        // Segundo lote en policultivo sin regla en esa fase y sin muestreos (usa peso inicial y maneja ausencia de regla)
        $siembraSinRegla = Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $especieNueva->id,
            'fecha' => '2026-10-20',
            'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 50,
            'peces_vivos' => 100,
            'peso_actual_g' => 50,
            'biomasa_kg' => 5,
        ]);

        $estadoConRegla = EstadoFase::paraSiembra($lago, $siembraConRegla);
        $this->assertNotNull($estadoConRegla);
        $this->assertTrue($estadoConRegla['tiene_regla']);
        $this->assertSame(50, $estadoConRegla['dias']['real']);
        $this->assertSame(100, $estadoConRegla['dias']['esperado']);
        $this->assertSame(0, $estadoConRegla['dias']['atraso']);
        $this->assertSame(240.0, $estadoConRegla['peso']['esperado_hoy_g']);
        $this->assertFalse($estadoConRegla['peso']['atrasado']);
        $this->assertSame(96.0, $estadoConRegla['supervivencia']['real']);
        $this->assertFalse($estadoConRegla['supervivencia']['baja']);
        // Densidad total del lago: (1440 + 100) / 100 = 15,4 peces/m² (dentro de 12 a 25)
        $this->assertSame(15.4, $estadoConRegla['densidad']['real']);
        $this->assertSame('en_rango', $estadoConRegla['densidad']['estado']);

        $estadoSinRegla = EstadoFase::paraSiembra($lago, $siembraSinRegla);
        $this->assertNotNull($estadoSinRegla);
        $this->assertFalse($estadoSinRegla['tiene_regla']);

        // Si area_m2 es null o 0, densidadLago devuelve null sin división por cero
        $lagoSinArea = Lago::create(['nombre' => 'E02', 'tipo' => 'tierra', 'fase_id' => $engorde->id, 'area_m2' => null]);
        $this->assertNull(EstadoFase::densidadLago($lagoSinArea));

        // Vista de detalle del lago renderiza la comparación y la densidad
        $this->actingAs($this->administrador)
            ->get(route('admin.lagos.show', $lago))
            ->assertOk()
            ->assertSee('Engorde')
            ->assertSee('100 m²')
            ->assertSee('15,40 peces/m²')
            ->assertSee('Fase Engorde · real vs. esperado')
            ->assertSee('La fase Engorde aún no tiene reglas configuradas para Carpa espejo.');

        // Paquete de Registro rápido incluye la fase del lago
        $paquete = $this->actingAs($this->administrador)->getJson(route('campo.paquete'))->assertOk()->json();
        $lagoEnPaquete = collect($paquete['lagos'])->firstWhere('id', $lago->id);
        $this->assertSame('Engorde', $lagoEnPaquete['fase']);
    }
}

