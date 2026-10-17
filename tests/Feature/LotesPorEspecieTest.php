<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Traslado;
use App\Models\User;
use App\Support\Asistente\ContextoFinca;
use App\Support\CalculadoraRacion;
use App\Support\ConversionAlimenticia;
use App\Support\Formato;
use App\Support\LotesPorEspecie;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lotes de la misma especie en un lago, agrupados solo para mostrar: total, peso ponderado con rango, número de lotes y
 * el origen de cada uno (siembra o traslado). Las operaciones y los cálculos siguen por lote.
 * Escenario: L01 con dos lotes de bagre rayado (1.000 peces de 400 g y 300 de 50 g) y uno de trucha; L02 con bagre.
 */
class LotesPorEspecieTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private Lago $l01;

    private Lago $l02;

    private Siembra $bagre1;

    private Siembra $bagre2;

    private Siembra $trucha;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-04 08:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
        $this->l01 = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->l02 = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $this->bagre1 = $this->sembrar($this->l01, 'bagre rayado', '2026-09-01', 1000, 400);
        $this->bagre2 = $this->sembrar($this->l01, 'bagre rayado', '2026-10-15', 300, 50);
        $this->trucha = $this->sembrar($this->l01, 'trucha arcoiris', '2026-10-01', 400, 120);
    }

    private function sembrar(Lago $lago, string $especie, string $fecha, int $peces, float $peso): Siembra
    {
        return Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie($especie), 'fecha' => $fecha, 'cantidad_alevinos' => $peces,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces, 'peso_actual_g' => $peso, 'biomasa_kg' => $peces * $peso / 1000]);
    }

    private function trasladar(Siembra $siembra, Lago $destino, int $cantidad, float $peso = 200, array $modo = ['modo' => 'aparte'])
    {
        return $this->actingAs($this->administrador)->post(route('admin.traslados.store', $siembra->id), $modo + [
            'lago_destino_id' => $destino->id, 'fecha' => '2026-11-04', 'cantidad' => $cantidad, 'peso_promedio_g' => $peso,
        ]);
    }

    private function siembrasDe(Lago $lago)
    {
        return $lago->fresh()->load('siembrasActivas.lagoOrigen', 'siembrasActivas.trasladosRecibidos.lagoOrigen')->siembrasActivas;
    }

    public function test_agrupa_por_especie_con_totales_peso_ponderado_y_rango(): void
    {
        $grupos = LotesPorEspecie::agrupar($this->siembrasDe($this->l01));

        $this->assertCount(2, $grupos);
        $bagre = $grupos->firstWhere('especie_id', $this->especie('bagre rayado'));
        $this->assertSame(2, $bagre['lotes']);
        $this->assertSame(1300, $bagre['peces']);
        $this->assertEquals(415.0, $bagre['biomasa_kg']);
        $this->assertEquals(319.23, $bagre['peso_promedio_g']);   // (1000 × 400 + 300 × 50) ÷ 1300
        $this->assertSame([$this->bagre1->id, $this->bagre2->id], $bagre['siembras']->pluck('id')->all());   // por fecha
        $this->assertSame(Formato::numero(319.23, 2).' g · de 50 a 400 g', LotesPorEspecie::textoPeso($bagre));

        $trucha = $grupos->firstWhere('especie_id', $this->especie('trucha arcoiris'));
        $this->assertSame(1, $trucha['lotes']);
        $this->assertSame(Formato::numero(120, 2).' g', LotesPorEspecie::textoPeso($trucha));   // un lote: sin rango

        $bagreNombre = $bagre['especie'];
        $this->assertSame("{$bagreNombre} (2 lotes), {$trucha['especie']}", LotesPorEspecie::nombres($this->siembrasDe($this->l01)));
    }

    public function test_el_traslado_que_crea_un_lote_guarda_su_origen_y_el_que_suma_no(): void
    {
        $l03 = Lago::create(['nombre' => 'L03', 'tipo' => 'tierra']);

        // L03 vacío: el traslado crea un lote nuevo, con origen «traslado desde L01»
        $this->trasladar($this->bagre1, $l03, 100)->assertSessionHasNoErrors();
        $creado = Siembra::where('lago_id', $l03->id)->sole();
        $this->assertSame(Siembra::ORIGEN_TRASLADO, $creado->origen);
        $this->assertSame($this->l01->id, $creado->lago_origen_id);
        $this->assertSame(__('lagos.lotes.origen_traslado', ['lago' => 'L01', 'fecha' => '04/11/2026']), $creado->textoOrigen());
        $this->assertCount(0, $creado->trasladosSumados());

        // L01 tiene dos lotes de bagre: el traslado desde L03 se suma al elegido, que sigue siendo «siembra» y dice que recibió
        $this->trasladar($creado, $this->l01, 40, modo: ['modo' => 'sumar', 'siembra_destino_id' => $this->bagre1->id])->assertSessionHasNoErrors();
        $this->assertSame(Siembra::ORIGEN_SIEMBRA, $this->bagre1->fresh()->origen);
        $sumados = $this->bagre1->fresh()->trasladosSumados();
        $this->assertCount(1, $sumados);
        $this->assertSame([40, $l03->id], [$sumados[0]->cantidad, $sumados[0]->lago_origen_id]);
        $this->assertSame(__('lagos.lotes.origen_siembra', ['fecha' => '01/09/2026']), $this->bagre1->fresh()->textoOrigen());
    }

    public function test_el_relleno_de_la_migracion_reconoce_los_lotes_creados_por_traslado(): void
    {
        // Datos como los dejaba la app antes de esta columna: un lote creado por un traslado (misma transacción)…
        $l03 = Lago::create(['nombre' => 'L03', 'tipo' => 'tierra']);
        $creado = $this->sembrar($l03, 'bagre rayado', '2026-11-04', 100, 200);
        $creado->forceFill(['peso_promedio_inicial_g' => 200])->save();
        DB::table('traslados')->insert($this->filaTraslado($this->bagre1, $creado, 100, 200, $creado->created_at->copy()->addSecond()));
        // …y un lote sembrado que más tarde recibió un traslado (no cambia de origen)
        DB::table('traslados')->insert($this->filaTraslado($creado, $this->bagre2, 300, 5, now()->addDay()));

        $migracion = require database_path('migrations/2026_11_03_000001_add_origen_to_siembras_table.php');
        $migracion->down();
        $migracion->up();

        $this->assertSame(['traslado', $this->l01->id], [$creado->fresh()->origen, $creado->fresh()->lago_origen_id]);
        $this->assertSame(['siembra', null], [$this->bagre2->fresh()->origen, $this->bagre2->fresh()->lago_origen_id]);
        $this->assertSame('siembra', $this->bagre1->fresh()->origen);
        $this->assertSame('siembra', $this->trucha->fresh()->origen);
    }

    private function filaTraslado(Siembra $origen, Siembra $destino, int $cantidad, float $peso, Carbon $creado): array
    {
        return ['tenant_id' => $this->finca->id, 'siembra_origen_id' => $origen->id, 'siembra_destino_id' => $destino->id, 'lago_origen_id' => $origen->lago_id,
            'lago_destino_id' => $destino->lago_id, 'user_id' => $this->administrador->id, 'fecha' => $destino->fecha->toDateString(), 'cantidad' => $cantidad,
            'peso_promedio_g' => $peso, 'biomasa_kg' => $cantidad * $peso / 1000, 'created_at' => $creado, 'updated_at' => $creado];
    }

    public function test_el_detalle_del_lago_agrupa_y_cada_lote_conserva_sus_botones(): void
    {
        $html = $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->l01))->assertOk()->getContent();

        $this->assertStringContainsString('data-especie-lotes="2"', $html);
        $this->assertStringContainsString(e(__('lagos.lotes.n_lotes', ['lotes' => 2])), $html);
        $this->assertStringContainsString(e(__('lagos.lotes.resumen', ['numero' => 2, 'origen' => __('lagos.lotes.origen_siembra', ['fecha' => '15/10/2026']), 'peces' => '300'])), $html);
        $this->assertStringContainsString(e(Formato::numero(319.23, 2).' g · de 50 a 400 g'), $html);
        // Cada lote, con sus modales de siempre (los ids no cambian)
        foreach ([$this->bagre1, $this->bagre2, $this->trucha] as $siembra) {
            $this->assertStringContainsString('id="modal-traslado-'.$siembra->id.'"', $html);
            $this->assertStringContainsString(route('admin.cosechas.create', ['siembra' => $siembra->id]), $html);
        }
        // La trucha, sola en su especie, como siempre (sin «lotes»)
        $this->assertSame(1, substr_count($html, 'data-especie-lotes='));

        // En L02, un lote creado por traslado es el único de su especie: se ve como siempre
        $this->trasladar($this->bagre1, $this->l02, 10)->assertSessionHasNoErrors();
        $this->flushSession();
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->l02))->assertOk()->assertDontSee('data-especie-lotes', false);
    }

    public function test_la_lista_de_lagos_agrupa_y_conserva_los_modales_por_lote(): void
    {
        $html = $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-especie-lotes="2"'));
        foreach ([$this->bagre1, $this->bagre2, $this->trucha] as $siembra) {
            foreach (['modal-mortalidad-', 'modal-muestreo-', 'modal-finalizar-'] as $modal) {
                $this->assertStringContainsString('id="'.$modal.$siembra->id.'"', $html);
            }
            // El formulario de mortalidad sigue guardándose sin conexión
            $this->assertStringContainsString(route('admin.mortalidades.store', $siembra->id), $html);
        }
        $this->assertStringContainsString(e(__('lagos.lotes.origen_siembra', ['fecha' => '01/09/2026'])), $html);
        // El título del modal dice qué lote es
        $this->assertStringContainsString(e(__('lagos.lotes.etiqueta', ['especie' => $this->bagre2->nombre_especie, 'numero' => 2, 'origen' => mb_lcfirst(__('lagos.lotes.origen_siembra', ['fecha' => '15/10/2026']))])), $html);
    }

    public function test_inicio_noche_y_reporte_no_repiten_la_especie(): void
    {
        $nombre = $this->bagre1->nombre_especie;
        $repetido = "{$nombre}, {$nombre}";
        $agrupado = e(__('lagos.lotes.especie_con_lotes', ['especie' => $nombre, 'lotes' => 2]));

        $propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]);
        $celador = User::factory()->conRoles(Rol::CELADOR)->create(['tenant_id' => $this->finca->id]);
        $this->ponerEnTurno($celador, Rol::CELADOR);
        foreach ([
            [$this->administrador, route('inicio')],
            [$celador, route('noche.index')],
            [$propietario, route('admin.reportes.mensual')],
        ] as [$quien, $url]) {
            $html = $this->actingAs($quien)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString($repetido, $html, $url);
            $this->assertStringContainsString($agrupado, $html, $url);
        }
    }

    public function test_los_calculos_siguen_por_lote(): void
    {
        $lago = $this->l01->fresh();
        // Ración: una fila por lote (cada peso con su porcentaje) y el total del lago
        $racion = CalculadoraRacion::paraLago($lago);
        $this->assertCount(3, $racion['filas']);
        // Conversión: una fila por siembra
        $conversion = (new ConversionAlimenticia)->porLago()->firstWhere('lago.id', $this->l01->id);
        $this->assertCount(3, $conversion['siembras']);
    }

    public function test_registro_rapido_lleva_el_numero_y_el_origen_de_cada_lote(): void
    {
        $siembras = collect($this->actingAs($this->administrador)->getJson(route('campo.paquete'))->assertOk()->json('lagos'))
            ->firstWhere('id', $this->l01->id)['siembras'];
        $porId = collect($siembras)->keyBy('id');

        $this->assertSame([1, __('lagos.lotes.origen_siembra', ['fecha' => '01/09/2026'])], [$porId[$this->bagre1->id]['lote'], $porId[$this->bagre1->id]['origen']]);
        $this->assertSame(2, $porId[$this->bagre2->id]['lote']);
        // La trucha, sola en su especie: sin número ni origen (se muestra como siempre)
        $this->assertArrayNotHasKey('lote', $porId[$this->trucha->id]);
        $this->assertArrayNotHasKey('origen', $porId[$this->trucha->id]);
    }

    public function test_el_asistente_ve_la_especie_con_su_total_y_sus_lotes(): void
    {
        $contexto = ContextoFinca::para($this->administrador);
        $nombre = $this->bagre1->nombre_especie;

        $this->assertStringContainsString("{$nombre}: 2 lotes, en total ".Formato::numero(1300).' peces', $contexto);
        $this->assertStringContainsString('lote 2 ('.mb_lcfirst(__('lagos.lotes.origen_siembra', ['fecha' => '15/10/2026'])).')', $contexto);
        $this->assertStringNotContainsString("{$nombre}: ".Formato::numero(1000).' peces', $contexto);
    }

    public function test_aislamiento_por_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $adminOtra = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $otra->id]);
        $lagoOtra = Lago::create(['nombre' => 'X1', 'tipo' => 'tierra']);

        $this->actingAs($adminOtra)->get(route('admin.lagos.show', $this->l01))->assertNotFound();
        $this->actingAs($adminOtra)->get(route('admin.lagos.index'))->assertOk()->assertDontSee('data-especie-lotes', false);
        // No se puede trasladar a un lago de otra finca (el origen siempre es de la misma finca)
        app(TenantActual::class)->establecer($this->finca->id);
        $this->trasladar($this->bagre1, $lagoOtra, 10)->assertSessionHasErrors('lago_destino_id');
        $this->assertSame(0, Traslado::count());
    }
}
