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
        // Solo los datos de la finca piloto (tema 15.1): precría, levante y engorde para las 5 especies de agua cálida
        $this->assertSame(15, ReglaFase::query()->count());

        $regla = fn (string $fase, string $especie) => ReglaFase::query()->where('fase_id', Fase::query()->where('clave', $fase)->value('id'))
            ->where('especie_id', $this->especie($especie))->first();
        $campos = ['peso_entrada_g', 'peso_salida_g', 'dias_esperados', 'supervivencia_porcentaje', 'densidad_min_m2', 'densidad_max_m2'];
        $this->assertSame([null, 30.0, 60, null, null, null], array_values($regla('precria', 'mojarra roja')->only($campos)));
        $this->assertSame([30.0, 150.0, 90, null, null, null], array_values($regla('levante', 'bocachico')->only($campos)));
        $this->assertSame([150.0, 850.0, 90, null, 15.0, 24.0], array_values($regla('engorde', 'cachama blanca')->only($campos)));
        $this->assertSame([150.0, 600.0, 90, null, 15.0, 24.0], array_values($regla('engorde', 'mojarra negra')->only($campos)));
        // Sin peso de salida para el bocachico en engorde; la trucha y el alevinaje, sin reglas
        $this->assertNull($regla('engorde', 'bocachico')->peso_salida_g);
        $this->assertNull($regla('engorde', 'trucha arcoiris'));
        $this->assertSame(0, ReglaFase::query()->where('fase_id', Fase::query()->where('clave', 'alevinaje')->value('id'))->count());

        // Volver a llamar cargarPorDefecto no duplica fases ni reglas
        Fase::cargarPorDefecto($this->finca->id);
        $this->assertSame(4, Fase::query()->count());
        $this->assertSame(15, ReglaFase::query()->count());
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

    public function test_el_detalle_del_lago_compara_peso_supervivencia_y_densidad_sin_dias_y_sin_datos_inventados(): void
    {
        $engorde = Fase::query()->where('clave', 'engorde')->firstOrFail();
        $especieNueva = Especie::create(['nombre' => 'Carpa espejo', 'clima' => Especie::FRIO, 'activo' => true]);
        $lago = Lago::create(['nombre' => 'E01', 'tipo' => 'concreto', 'fase_id' => $engorde->id, 'area_m2' => 100]);

        // Cachama en engorde: regla de la finca piloto (150 a 850 g, 15 a 24 peces/m², sin supervivencia esperada)
        $cachama = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => '2026-09-17',
            'cantidad_alevinos' => 1500, 'peso_promedio_inicial_g' => 150, 'peces_vivos' => 1440, 'peso_actual_g' => 250, 'biomasa_kg' => 360]);
        // Policultivo con una especie sin regla en esa fase
        $carpa = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $especieNueva->id, 'fecha' => '2026-10-20',
            'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 50, 'peces_vivos' => 100, 'peso_actual_g' => 50, 'biomasa_kg' => 5]);

        $estado = EstadoFase::paraSiembra($lago, $cachama);
        $this->assertTrue($estado['tiene_regla']);
        $this->assertArrayNotHasKey('dias', $estado);   // los días en la fase llegan con F5
        $this->assertSame(['real_g' => 250.0, 'entrada_g' => 150.0, 'salida_g' => 850.0, 'estado' => EstadoFase::PESO_EN_FASE], $estado['peso']);
        $this->assertSame(['real' => 96.0, 'esperada' => null, 'baja' => null], $estado['supervivencia']);
        // Densidad total del lago: (1440 + 100) / 100 = 15,4 peces/m² (dentro de 15 a 24)
        $this->assertSame(['real' => 15.4, 'min' => 15.0, 'max' => 24.0, 'estado' => 'en_rango'], $estado['densidad']);
        $this->assertFalse(EstadoFase::paraSiembra($lago, $carpa)['tiene_regla']);

        // Peso por debajo de la entrada y en el peso de salida
        $cachama->update(['peso_actual_g' => 120]);
        $this->assertSame(EstadoFase::PESO_BAJO_ENTRADA, EstadoFase::paraSiembra($lago, $cachama)['peso']['estado']);
        $cachama->update(['peso_actual_g' => 850]);
        $this->assertSame(EstadoFase::PESO_SALIDA, EstadoFase::paraSiembra($lago, $cachama)['peso']['estado']);
        $cachama->update(['peso_actual_g' => 250]);

        // Sin área: la densidad no se calcula (sin dividir por cero) ni se juzga
        $lagoSinArea = Lago::create(['nombre' => 'E02', 'tipo' => 'tierra', 'fase_id' => $engorde->id, 'area_m2' => null]);
        $this->assertNull(EstadoFase::densidadLago($lagoSinArea));

        // La pantalla: lo que no tiene dato dice «sin dato», sin días ni valores esperados inventados
        $this->actingAs($this->administrador)
            ->get(route('admin.lagos.show', $lago))
            ->assertOk()
            ->assertSee('Engorde')
            ->assertSee('100 m²')
            ->assertSee('15,40 peces/m²')
            ->assertSee('Fase Engorde · real contra la regla')
            ->assertSee('Entrada: 150 g · salida: 850 g')
            ->assertSee('Dentro de la fase')
            ->assertSee('Esperada: sin dato')
            ->assertSee('Referencia: 15 a 24 peces/m²')
            ->assertDontSee('días de atraso')
            ->assertSee('La fase Engorde aún no tiene reglas configuradas para Carpa espejo.');

        // Un lago sin fase no muestra comparación y sigue funcionando
        $sinFase = Lago::create(['nombre' => 'S01', 'tipo' => 'tierra']);
        $loteSinFase = Siembra::create(['lago_id' => $sinFase->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => '2026-10-01',
            'cantidad_alevinos' => 10, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 5, 'biomasa_kg' => 0.05]);
        $this->assertNull(EstadoFase::paraSiembra($sinFase, $loteSinFase));
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $sinFase))->assertOk()->assertDontSee('data-comparacion-fase', false);

        // Paquete de Registro rápido incluye la fase del lago (campo agregado)
        $paquete = $this->actingAs($this->administrador)->getJson(route('campo.paquete'))->assertOk()->json();
        $this->assertSame('Engorde', collect($paquete['lagos'])->firstWhere('id', $lago->id)['fase']);
        $this->assertNull(collect($paquete['lagos'])->firstWhere('id', $sinFase->id)['fase']);
    }
}
