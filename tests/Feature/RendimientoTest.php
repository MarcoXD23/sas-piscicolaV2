<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Especie;
use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Models\JornadaRendimiento;
use App\Models\Lago;
use App\Models\Muestreo;
use App\Models\PesajeLimpio;
use App\Models\Presentacion;
use App\Models\Proceso;
use App\Models\ReproduccionJornada;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tamano;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Proceso\Rendimiento;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6b: limpio por presentación, rendimiento contra la referencia, reproducción, muestra al pescar y esperado contra
 * real. Hoy es 2026-10-15 en la finca. L01 tiene dos siembras revueltas de cachama: S1 con 1.000 peces de 400 g y S2 con
 * 500 de 200 g. Con 250 kg enteros, el reparto según la biomasa es 200 kg y 50 kg.
 */
class RendimientoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $administrador;

    private Lago $lago;

    private Siembra $s1;

    private Siembra $s2;

    private int $cachama;

    private Presentacion $presentacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->ajuste(CatalogoAjustes::PROCESO_JORNADAS, CatalogoAjustes::ENCENDIDA);
        $this->cachama = $this->especie('cachama blanca');

        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->s1 = $this->sembrar(1000, 400, '2026-04-01');
        $this->s2 = $this->sembrar(500, 200, '2026-07-01');

        $tamano = Tamano::create(['especie_id' => $this->cachama, 'nombre' => 'Grande']);
        $proceso = Proceso::create(['nombre' => 'Descamado']);
        $this->presentacion = Presentacion::create(['especie_id' => $this->cachama, 'tamano_id' => $tamano->id, 'proceso_id' => $proceso->id]);
    }

    private function ajuste(string $clave, string|int $valor): void
    {
        AjusteFinca::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    private function sembrar(int $peces, float $gramos, string $fecha): Siembra
    {
        return Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->cachama, 'fecha' => $fecha,
            'cantidad_alevinos' => $peces, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces, 'peso_actual_g' => $gramos,
            'biomasa_kg' => $peces * $gramos / 1000]);
    }

    private function jornada(string $fecha = '2026-10-15', float $entero = 250): Jornada
    {
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => $fecha, 'lagos' => [$this->lago->id]])->assertSessionHasNoErrors();
        $jornada = Jornada::query()->latest('id')->firstOrFail();
        $this->actingAs($this->administrador)->post(route('admin.jornadas.pesajes.store', $jornada), [
            'jornada_lago_id' => $jornada->lagos()->first()->id, 'especie_id' => $this->cachama, 'kg' => $entero,
        ])->assertSessionHasNoErrors();

        return $jornada;
    }

    private function limpio(Jornada $jornada, float $kg, ?int $presentacion = null)
    {
        return $this->actingAs($this->administrador)->post(route('admin.jornadas.limpio.store', $jornada), [
            'presentacion_id' => $presentacion ?? $this->presentacion->id, 'kg' => $kg,
        ]);
    }

    private function cerrar(Jornada $jornada, array $lago = [], array $rendimientos = [])
    {
        return $this->actingAs($this->administrador)->post(route('admin.jornadas.cerrar.guardar', $jornada), [
            'lagos' => [$jornada->lagos()->first()->id => $lago + ['resultado' => JornadaLago::QUEDO_PESCADO]],
            'rendimientos' => $rendimientos,
        ]);
    }

    // --- Limpio ---

    public function test_el_limpio_se_pesa_por_presentacion_con_pesadas_o_total(): void
    {
        $jornada = $this->jornada();

        $this->actingAs($this->administrador)->post(route('admin.jornadas.limpio.store', $jornada), [
            'presentacion_id' => $this->presentacion->id, 'peso_canasta_kg' => 2.5, 'peso_canastilla_kg' => 1.5,
            'pesadas' => [['tipo' => 'canasta', 'cantidad' => 4, 'peso_pesado' => 110]],
        ])->assertSessionHasNoErrors();
        $this->limpio($jornada, 50.5)->assertSessionHasNoErrors();

        // 110 − 4 × 2,5 = 100; más 50,5 escritos
        $this->assertEqualsWithDelta(150.5, PesajeLimpio::query()->sum('limpio_kg'), 0.001);
        $this->actingAs($this->administrador)->get(route('admin.jornadas.show', $jornada))->assertOk()
            ->assertSee(__('jornadas.limpio_total', ['kg' => '150,5']));
    }

    public function test_el_limpio_tiene_el_mismo_chequeo_de_jornada_abierta_que_el_entero(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-16', 'lagos' => [$this->lago->id]])->assertSessionHasNoErrors();
        $this->limpio(Jornada::query()->latest('id')->firstOrFail(), 10)->assertSessionHasErrors('jornada');

        $hoy = $this->jornada();
        $this->actingAs($this->administrador)->post(route('admin.jornadas.cancelar', $hoy))->assertSessionHasNoErrors();
        $this->limpio($hoy, 10)->assertSessionHasErrors('jornada');

        // Una presentación inactiva o de una especie que no está en la jornada no se pesa
        $otra = $this->jornada('2026-10-14');
        $this->presentacion->update(['activo' => false]);
        $this->limpio($otra, 10)->assertSessionHasErrors('presentacion_id');
        $mojarra = Presentacion::create(['especie_id' => $this->especie('mojarra roja'),
            'tamano_id' => Tamano::create(['especie_id' => $this->especie('mojarra roja'), 'nombre' => 'Grande'])->id, 'proceso_id' => Proceso::query()->first()->id]);
        $this->limpio($otra, 10, $mojarra->id)->assertSessionHasErrors('presentacion_id');

        $this->assertSame(0, PesajeLimpio::query()->count());
    }

    // --- Rendimiento y limpio por cosecha ---

    public function test_al_cerrar_el_limpio_se_reparte_en_las_cosechas_y_queda_el_rendimiento(): void
    {
        Especie::query()->whereKey($this->cachama)->update(['rendimiento_referencia' => 85]);
        $jornada = $this->jornada();
        $this->limpio($jornada, 215)->assertSessionHasNoErrors();

        $this->cerrar($jornada)->assertSessionHasNoErrors();

        $this->assertSame(172.0, Cosecha::query()->where('siembra_id', $this->s1->id)->sole()->limpio_kg);
        $this->assertSame(43.0, Cosecha::query()->where('siembra_id', $this->s2->id)->sole()->limpio_kg);
        $fila = JornadaRendimiento::query()->sole();
        $this->assertSame([250.0, 215.0, 86.0, 85.0, JornadaRendimiento::CATALOGO, false],
            [$fila->entero_kg, $fila->limpio_kg, $fila->rendimiento, $fila->referencia, $fila->fuente_referencia, $fila->bajo]);
        $this->actingAs($this->administrador)->get(route('admin.jornadas.show', $jornada))->assertOk()->assertSee('86,0 %');
    }

    public function test_rendimiento_bajo_avisa_guarda_el_motivo_y_queda_en_la_bitacora(): void
    {
        // 86 % contra 92 %: 6 puntos por debajo, más que el umbral de 5
        Especie::query()->whereKey($this->cachama)->update(['rendimiento_referencia' => 92]);
        $jornada = $this->jornada();
        $this->limpio($jornada, 215)->assertSessionHasNoErrors();

        $this->actingAs($this->administrador)->get(route('admin.jornadas.cerrar', $jornada))->assertOk()->assertSee(__('jornadas.rendimiento_bajo'));
        $this->cerrar($jornada, [], [$this->cachama => ['motivo' => 'Lote en reproducción']])->assertSessionHasNoErrors();

        $fila = JornadaRendimiento::query()->sole();
        $this->assertTrue($fila->bajo);
        $this->assertSame('Lote en reproducción', $fila->motivo);
        $this->assertSame(1, Bitacora::query()->where('accion', 'rendimiento_bajo')->count());
        $this->actingAs($this->administrador)->get(route('admin.jornadas.show', $jornada))->assertOk()->assertSee('Lote en reproducción');
    }

    public function test_el_umbral_es_configurable(): void
    {
        Especie::query()->whereKey($this->cachama)->update(['rendimiento_referencia' => 92]);
        $this->ajuste(CatalogoAjustes::PROCESO_UMBRAL_RENDIMIENTO, 10);
        $jornada = $this->jornada();
        $this->limpio($jornada, 215)->assertSessionHasNoErrors();

        $this->cerrar($jornada)->assertSessionHasNoErrors();
        $this->assertFalse(JornadaRendimiento::query()->sole()->bajo);
    }

    public function test_el_promedio_real_reemplaza_la_referencia_cuando_hay_n_jornadas(): void
    {
        Especie::query()->whereKey($this->cachama)->update(['rendimiento_referencia' => 86]);
        $this->ajuste(CatalogoAjustes::PROCESO_JORNADAS_PROMEDIO, 2);
        $rendimiento = function (string $fecha, float $entero, float $limpio) {
            $jornada = Jornada::create(['fecha' => $fecha, 'estado' => Jornada::CERRADA, 'user_id' => $this->administrador->id]);
            JornadaRendimiento::create(['jornada_id' => $jornada->id, 'especie_id' => $this->cachama, 'entero_kg' => $entero, 'limpio_kg' => $limpio]);
        };

        $rendimiento('2026-10-01', 100, 80);
        $this->assertSame(['valor' => 86.0, 'fuente' => JornadaRendimiento::CATALOGO], Rendimiento::referencia($this->cachama));

        // Ponderado por el entero: (80 + 246) ÷ (100 + 300) = 81,5 %
        $rendimiento('2026-10-08', 300, 246);
        $this->assertSame(['valor' => 81.5, 'fuente' => JornadaRendimiento::PROMEDIO], Rendimiento::referencia($this->cachama));

        // Solo las últimas N: la del 01/10 sale
        $rendimiento('2026-10-10', 100, 90);
        $this->assertSame(['valor' => 84.0, 'fuente' => JornadaRendimiento::PROMEDIO], Rendimiento::referencia($this->cachama));
    }

    public function test_sin_limpio_avisa_antes_de_cerrar_y_el_rendimiento_queda_sin_dato(): void
    {
        $jornada = $this->jornada();

        $this->actingAs($this->administrador)->get(route('admin.jornadas.cerrar', $jornada))->assertOk()
            ->assertSee(__('jornadas.aviso_sin_limpio'))->assertSee(__('jornadas.confirmar_cerrar_sin_limpio'));
        $this->cerrar($jornada)->assertSessionHasNoErrors();

        $fila = JornadaRendimiento::query()->sole();
        $this->assertNull($fila->limpio_kg);
        $this->assertNull($fila->rendimiento);
        $this->assertNull(Cosecha::query()->first()->limpio_kg);
    }

    public function test_el_limpio_no_puede_superar_el_entero(): void
    {
        $jornada = $this->jornada();
        $this->limpio($jornada, 260)->assertSessionHasNoErrors();

        $this->cerrar($jornada)->assertSessionHasErrors('jornada');
        $this->assertTrue($jornada->fresh()->estaAbierta());
    }

    // --- Reproducción ---

    public function test_con_el_ajuste_apagado_no_hay_reproduccion(): void
    {
        $jornada = $this->jornada();

        $this->actingAs($this->administrador)->post(route('admin.jornadas.reproduccion.store', $jornada), [
            'jornada_lago_id' => $jornada->lagos()->first()->id, 'especie_id' => $this->cachama, 'kg' => 10,
        ])->assertNotFound();
        $this->actingAs($this->administrador)->get(route('admin.jornadas.show', $jornada))->assertOk()
            ->assertDontSee(route('admin.jornadas.reproduccion.store', $jornada), false);
        $this->assertSame(0, ReproduccionJornada::query()->count());
    }

    public function test_la_reproduccion_se_resta_del_entero_y_vuelve_al_lago(): void
    {
        $this->ajuste(CatalogoAjustes::PROCESO_REPRODUCCION, CatalogoAjustes::ENCENDIDA);
        $jornada = $this->jornada();
        $this->actingAs($this->administrador)->post(route('admin.jornadas.reproduccion.store', $jornada), [
            'jornada_lago_id' => $jornada->lagos()->first()->id, 'especie_id' => $this->cachama, 'kg' => 50,
        ])->assertSessionHasNoErrors();
        $this->limpio($jornada, 172)->assertSessionHasNoErrors();

        $this->cerrar($jornada)->assertSessionHasNoErrors();

        // Se reparten 200 kg (250 − 50): 160 y 40; las siembras solo pierden lo cosechado
        $this->assertSame(160.0, Cosecha::query()->where('siembra_id', $this->s1->id)->sole()->kg);
        $this->assertSame(40.0, Cosecha::query()->where('siembra_id', $this->s2->id)->sole()->kg);
        $this->assertSame(600, $this->s1->fresh()->peces_vivos);
        $fila = JornadaRendimiento::query()->sole();
        $this->assertSame([250.0, 50.0, 86.0], [$fila->entero_kg, $fila->reproduccion_kg, $fila->rendimiento]);
    }

    // --- Muestra al pescar y esperado contra real ---

    public function test_la_muestra_de_un_lote_que_queda_es_un_muestreo_normal(): void
    {
        $jornada = $this->jornada();

        // Muestra de S1: 10 peces, 5.000 g → 500 g; 200 kg ÷ 500 g = 400 peces
        $this->cerrar($jornada, ['muestra_peces' => [$this->s1->id => 10], 'muestra_g' => [$this->s1->id => 5000]])->assertSessionHasNoErrors();

        $muestreo = Muestreo::query()->sole();
        $this->assertSame([$this->s1->id, '2026-10-15', 10, 500.0, $this->administrador->id],
            [$muestreo->siembra_id, $muestreo->fecha->toDateString(), (int) $muestreo->peces_pesados, (float) $muestreo->peso_promedio_g, $muestreo->user_id]);
        $s1 = $this->s1->fresh();
        $this->assertSame([600, 500.0], [$s1->peces_vivos, (float) $s1->peso_actual_g]);
        $this->assertEquals(300, (float) $s1->biomasa_kg);
        $this->assertSame(1, Bitacora::query()->where('accion', 'muestreo')->count());

        $cosecha = Cosecha::query()->where('siembra_id', $this->s1->id)->sole();
        $this->assertSame([400, 1000, 400.0, 500.0, false], [$cosecha->peces, $cosecha->peces_esperados, $cosecha->peso_esperado_g, $cosecha->peso_medido_g, $cosecha->cerro_lote]);
        $this->assertSame(500.0, $cosecha->esperadoContraReal()['peso_real']);
        $this->assertNull($cosecha->esperadoContraReal()['mortalidad_no_registrada']);
    }

    public function test_una_muestra_incompleta_no_se_acepta(): void
    {
        $jornada = $this->jornada();

        $this->cerrar($jornada, ['muestra_peces' => [$this->s1->id => 10]])->assertSessionHasErrors();
        $this->assertTrue($jornada->fresh()->estaAbierta());
        $this->assertSame(0, Muestreo::query()->count());
    }

    public function test_lote_cerrado_muestra_la_mortalidad_no_registrada(): void
    {
        $jornada = $this->jornada('2026-10-15', 450);

        // Vacío. S1 recibe 360 kg; con la muestra de 450 g salen 800 peces de los 1.000 registrados
        $this->cerrar($jornada, ['resultado' => JornadaLago::VACIO, 'muestra_peces' => [$this->s1->id => 4], 'muestra_g' => [$this->s1->id => 1800]])->assertSessionHasNoErrors();

        $cosecha = Cosecha::query()->where('siembra_id', $this->s1->id)->sole();
        $comparacion = $cosecha->esperadoContraReal();
        $this->assertTrue($cosecha->cerro_lote);
        $this->assertSame([1000, 800, 200, 400.0, 360.0], [$comparacion['peces_esperados'], $comparacion['peces_reales'], $comparacion['mortalidad_no_registrada'],
            $comparacion['kg_esperados'], $comparacion['kg_reales']]);
        // Vacío: no hay muestreo (el lote se cierra)
        $this->assertSame(0, Muestreo::query()->count());
        $this->actingAs($this->administrador)->get(route('admin.cosechas.show', $cosecha))->assertOk()
            ->assertSee(__('cosechas.esperado_real.mortalidad', ['peces' => '200']));
    }

    public function test_la_cosecha_manual_guarda_lo_esperado(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.cosechas.store'), [
            'siembra_id' => $this->s2->id, 'destino' => 'Restaurante', 'fecha' => '2026-10-15', 'peces' => 500, 'kg' => 110,
        ])->assertSessionHasNoErrors();

        $cosecha = Cosecha::query()->sole();
        $comparacion = $cosecha->esperadoContraReal();
        $this->assertSame([500, 200.0, true], [$cosecha->peces_esperados, $cosecha->peso_esperado_g, $cosecha->cerro_lote]);
        $this->assertSame([220.0, 500, 0, 100.0, 110.0], [$comparacion['peso_real'], $comparacion['peces_reales'], $comparacion['mortalidad_no_registrada'],
            $comparacion['kg_esperados'], $comparacion['kg_reales']]);
    }
}
