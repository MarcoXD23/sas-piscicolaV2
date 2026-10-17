<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tercero;
use App\Models\Traslado;
use App\Models\User;
use App\Support\ComparacionProveedores;
use App\Support\ConversionAlimenticia;
use App\Support\ConversionVida;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * F5: conversión de toda la vida del pez a través de los traslados (días y alimento arrastrados) y comparación de
 * proveedores (crecimiento, supervivencia y conversión). Datos ficticios con números redondos.
 */
class ConversionVidaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-10 08:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
    }

    private function sembrar(Lago $lago, string $fecha, int $peces, float $peso, array $extra = []): Siembra
    {
        return Siembra::create($extra + ['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => $fecha, 'cantidad_alevinos' => $peces,
            'peso_promedio_inicial_g' => $peso, 'peces_vivos' => $peces, 'peso_actual_g' => $peso, 'biomasa_kg' => $peces * $peso / 1000]);
    }

    private function alimento(Lago $lago, string $fecha, float $kg): void
    {
        MovimientoAlimento::create(['user_id' => $this->administrador->id, 'lago_id' => $lago->id, 'fecha' => $fecha,
            'tipo_movimiento' => 'salida', 'tipo_concentrado' => 'Levante 32 %', 'cantidad_kg' => $kg]);
    }

    private function trasladar(Siembra $siembra, Lago $destino, string $fecha, int $peces, float $peso, array $modo = ['modo' => 'aparte']): void
    {
        $this->actingAs($this->administrador)->post(route('admin.traslados.store', $siembra->id), $modo + [
            'lago_destino_id' => $destino->id, 'fecha' => $fecha, 'cantidad' => $peces, 'peso_promedio_g' => $peso,
        ])->assertSessionHasNoErrors();
    }

    private function muestrear(Siembra $siembra, string $fecha, float $gramos): void
    {
        $this->actingAs($this->administrador)->post(route('admin.muestreos.store', $siembra->id), [
            'fecha' => $fecha, 'peces_pesados' => 10, 'peso_total_g' => $gramos * 10,
        ])->assertSessionHasNoErrors();
    }

    public function test_conversion_de_vida_a_traves_de_dos_traslados(): void
    {
        [$a, $b, $c] = [Lago::create(['nombre' => 'A', 'tipo' => 'tierra']), Lago::create(['nombre' => 'B', 'tipo' => 'tierra']), Lago::create(['nombre' => 'C', 'tipo' => 'tierra'])];

        // A: 1.000 alevinos de 10 g (10 kg) y 30 kg de alimento; el 01/07 pasan todos a B con 40 g (40 kg)
        $s1 = $this->sembrar($a, '2026-06-01', 1000, 10);
        $this->alimento($a, '2026-06-15', 30);
        $this->trasladar($s1, $b, '2026-07-01', 1000, 40);
        $s2 = Siembra::query()->where('lago_id', $b->id)->sole();

        // B: 60 kg de alimento; el 01/08 la mitad (500 de 100 g) pasa a C, lote aparte
        $this->alimento($b, '2026-07-15', 60);
        $this->trasladar($s2, $c, '2026-08-01', 500, 100);
        $s3 = Siembra::query()->where('lago_id', $c->id)->sole();

        // C: 45 kg de alimento y un muestreo de 160 g
        $this->alimento($c, '2026-08-15', 45);
        $this->muestrear($s3, '2026-09-01', 160);

        $conversion = new ConversionAlimenticia;
        $vida = new ConversionVida($conversion);

        // Lote A: ganó 40 − 10 = 30 kg con 30 kg de alimento. Todo se lo llevó el traslado a B
        $this->assertEqualsWithDelta(['alimento_kg' => 30.0, 'ganancia_kg' => 30.0], $vida->porcion(Traslado::query()->where('siembra_origen_id', $s1->id)->sole()), 0.001);

        // Lote B: propio 60 kg de alimento y 50 + 50 − 40 = 60 kg ganados; de vida 90 y 90. La mitad se fue a C
        $this->assertEqualsWithDelta(['alimento_kg' => 90.0, 'ganancia_kg' => 90.0], $vida->acumulado($s2->fresh(), '2026-08-01'), 0.001);
        $this->assertEqualsWithDelta(['alimento_kg' => 45.0, 'ganancia_kg' => 45.0], $vida->porcion(Traslado::query()->where('siembra_origen_id', $s2->id)->sole()), 0.001);

        // Lote C: propio 45 kg de alimento y 80 − 50 = 30 kg ganados (FCA 1,5); de vida 90 ÷ 75 = 1,2
        $propio = $conversion->deSiembra($s3->fresh());
        $this->assertSame(1.5, $propio['fca']);
        $deVida = $vida->deSiembra($s3->fresh());
        $this->assertEqualsWithDelta(90.0, $deVida['alimento_kg'], 0.001);
        $this->assertEqualsWithDelta(75.0, $deVida['ganancia_kg'], 0.001);
        $this->assertSame(1.2, $deVida['fca']);
        $this->assertTrue($deVida['arrastra']);
        // Días de vida desde la siembra original (01/06), no desde que llegó a C
        $this->assertSame('2026-06-01', $deVida['fecha_vida']);
        $this->assertSame(101, $deVida['dias_vida']);
        $this->assertSame(61, $vida->diasDeVidaAlTraslado(Traslado::query()->where('siembra_origen_id', $s2->id)->sole()));

        // El detalle del lago C lo muestra
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $c))->assertOk()
            ->assertSee('data-vida-lote', false)
            ->assertSee(__('lagos.vida.dias', ['dias' => '101', 'fecha' => '01/06/2026']))
            ->assertSee(__('lagos.vida.fca', ['fca' => '1,20']));

        // Los totales por especie siguen con el FCA propio (sin contar dos veces): 135 kg ÷ (30 + 60 + 30) kg
        $especie = $conversion->porEspecie()->sole();
        $this->assertEqualsWithDelta(135.0, $especie['alimento_kg'], 0.001);
    }

    public function test_al_sumar_el_lote_conserva_sus_dias_y_arrastra_el_alimento_de_los_que_llegan(): void
    {
        [$a, $b] = [Lago::create(['nombre' => 'A', 'tipo' => 'tierra']), Lago::create(['nombre' => 'B', 'tipo' => 'tierra'])];
        $origen = $this->sembrar($a, '2026-06-01', 1000, 10);
        $destino = $this->sembrar($b, '2026-07-01', 1000, 50);
        $this->alimento($a, '2026-06-15', 30);

        // 500 peces de 40 g (A ganó 30 kg; la mitad, 15 kg con 15 kg de alimento, se va con ellos) se suman a B
        $this->trasladar($origen, $b, '2026-07-01', 500, 40, ['modo' => 'sumar']);
        $this->alimento($b, '2026-07-20', 40);
        $this->muestrear($destino, '2026-08-01', 80);

        $vida = (new ConversionVida)->deSiembra($destino->fresh());
        // Propio: 1.500 × 80 g = 120 kg − 50 kg iniciales − 20 kg recibidos = 50 kg, con 40 kg de alimento; más lo arrastrado
        $this->assertEqualsWithDelta(55.0, $vida['alimento_kg'], 0.001);
        $this->assertEqualsWithDelta(65.0, $vida['ganancia_kg'], 0.001);
        // Los días son los del lote de destino
        $this->assertSame('2026-07-01', $vida['fecha_vida']);
    }

    public function test_un_lote_sin_traslados_no_cambia(): void
    {
        $a = Lago::create(['nombre' => 'A', 'tipo' => 'tierra']);
        $lote = $this->sembrar($a, '2026-06-01', 1000, 10);
        $this->alimento($a, '2026-06-15', 30);
        $this->muestrear($lote, '2026-07-01', 40);

        $conversion = new ConversionAlimenticia;
        $vida = (new ConversionVida($conversion))->deSiembra($lote->fresh());
        $this->assertFalse($vida['arrastra']);
        $this->assertSame($conversion->deSiembra($lote->fresh())['fca'], $vida['fca']);
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $a))->assertOk()->assertDontSee('data-vida-lote', false);
    }

    public function test_comparacion_de_proveedores_con_un_lote_que_recibe_peces(): void
    {
        $p = $this->proveedor('Alevinos P');
        $q = $this->proveedor('Alevinos Q');
        [$a, $b, $c] = [Lago::create(['nombre' => 'A', 'tipo' => 'tierra']), Lago::create(['nombre' => 'B', 'tipo' => 'tierra']), Lago::create(['nombre' => 'C', 'tipo' => 'tierra'])];

        $x = $this->sembrar($a, '2026-06-01', 1000, 5, ['proveedor_id' => $p->id]);
        $y = $this->sembrar($b, '2026-06-01', 500, 5, ['proveedor_id' => $q->id]);
        $this->actingAs($this->administrador)->post(route('admin.mortalidades.store', $x->id), ['fecha' => '2026-06-20', 'cantidad' => 100])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.mortalidades.store', $y->id), ['fecha' => '2026-06-20', 'cantidad' => 50])->assertSessionHasNoErrors();

        // De X salen 200 peces que se suman al lote Y (de otro proveedor) y 100 a un lote aparte en C (hereda P)
        $this->trasladar($x, $b, '2026-07-01', 200, 35, ['modo' => 'sumar']);
        $this->trasladar($x, $c, '2026-07-01', 100, 35);
        $this->assertSame($p->id, Siembra::query()->where('lago_id', $c->id)->sole()->proveedor_id);

        $filas = (new ComparacionProveedores)->filas()->keyBy('nombre');

        // P: (600 vivos en X + 100 en C + 200 trasladados a Y) ÷ 1.000 sembrados = 90 %; el lote de C no se cuenta dos veces
        $this->assertSame(1000, $filas['Alevinos P']['alevinos']);
        $this->assertSame(2, $filas['Alevinos P']['lotes']);
        $this->assertSame(90.0, $filas['Alevinos P']['supervivencia']);
        $this->assertFalse($filas['Alevinos P']['mezcla']);
        // Crecimiento: de 5 a 35 g en 30 días = 1 g/día
        $this->assertSame(1.0, $filas['Alevinos P']['crecimiento_g_dia']);

        // Q recibió 200 peces: (500 − 50 + 200) ÷ (500 + 200) = 92,9 %, nunca más de 100 %
        $this->assertSame(500, $filas['Alevinos Q']['alevinos']);
        $this->assertSame(92.9, $filas['Alevinos Q']['supervivencia']);
        $this->assertTrue($filas['Alevinos Q']['mezcla']);

        $this->actingAs($this->administrador)->get(route('admin.conversion.index'))->assertOk()
            ->assertSee('data-por-proveedor', false)->assertSee('Alevinos P')->assertSee('90,0 %')->assertSee(__('conversion.mezcla'));
    }

    public function test_comparacion_agrupa_las_siembras_sin_ligar_y_sin_proveedor(): void
    {
        $a = Lago::create(['nombre' => 'A', 'tipo' => 'tierra']);
        $b = Lago::create(['nombre' => 'B', 'tipo' => 'tierra']);
        $this->sembrar($a, '2026-06-01', 100, 5, ['proveedor_alevinos' => 'Escrito a mano']);
        $this->sembrar($b, '2026-06-01', 100, 5);

        $claves = (new ComparacionProveedores)->filas()->pluck('clave')->all();
        $this->assertSame([ComparacionProveedores::SIN_LIGAR, ComparacionProveedores::SIN_PROVEEDOR], $claves);
        $this->actingAs($this->administrador)->get(route('admin.conversion.index'))->assertOk()
            ->assertSee(__('conversion.proveedor_sin_ligar'))->assertSee(route('admin.proveedores.revision'));
    }

    public function test_la_comparacion_no_mezcla_fincas(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $lagoAjeno = Lago::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'nombre' => 'X', 'tipo' => 'tierra']);
        Siembra::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'lago_id' => $lagoAjeno->id, 'especie_id' => $this->especie('mojarra roja'),
            'fecha' => '2026-06-01', 'proveedor_alevinos' => 'Ajeno', 'cantidad_alevinos' => 1, 'peso_promedio_inicial_g' => 1, 'peces_vivos' => 1, 'peso_actual_g' => 1, 'biomasa_kg' => 0]);

        $this->assertTrue((new ComparacionProveedores)->filas()->isEmpty());
    }

    private function proveedor(string $nombre): Tercero
    {
        $tercero = new Tercero(['nombres' => $nombre]);
        $tercero->asignarProvee([Tercero::PROVEE_ALEVINOS]);
        $tercero->save();

        return $tercero;
    }
}
