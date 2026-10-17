<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tercero;
use App\Models\Traslado;
use App\Models\User;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * F5: al trasladar se pregunta «sumar al lote existente» o «crear lote aparte» (diseño, tema 4). Sin adivinar: sin lote
 * de la especie no se puede sumar, y con varios hay que elegir. El lote aparte conserva la trazabilidad del origen.
 * Datos ficticios.
 */
class TrasladoLoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private Lago $origen;

    private Lago $destino;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-10 08:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
        $this->origen = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->destino = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
    }

    private function lote(Lago $lago, string $fecha, int $peces = 1000, float $peso = 50, array $extra = []): Siembra
    {
        return Siembra::create($extra + ['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => $fecha, 'cantidad_alevinos' => $peces,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces, 'peso_actual_g' => $peso, 'biomasa_kg' => $peces * $peso / 1000]);
    }

    private function trasladar(Siembra $siembra, array $datos)
    {
        return $this->actingAs($this->administrador)->post(route('admin.traslados.store', $siembra), $datos + [
            'lago_destino_id' => $this->destino->id, 'fecha' => '2026-11-10', 'cantidad' => 100, 'peso_promedio_g' => 50,
        ]);
    }

    public function test_se_pregunta_siempre_que_hacer_en_el_destino(): void
    {
        $origen = $this->lote($this->origen, '2026-09-01');

        $this->trasladar($origen, [])->assertSessionHasErrors('modo');
        $this->trasladar($origen, ['modo' => 'mezclar'])->assertSessionHasErrors('modo');
        $this->assertSame(0, Traslado::query()->count());
        $this->assertSame(1000, $origen->fresh()->peces_vivos);

        // La pantalla ofrece las dos opciones, sin ninguna marcada
        $html = $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->origen))->assertOk()
            ->assertSee(__('lagos.traslado.sumar'))->assertSee(__('lagos.traslado.aparte'))->getContent();
        $this->assertStringNotContainsString('value="sumar" class="mt-0.5 h-5 w-5 shrink-0 accent-marca-700" required data-traslado-modo checked', $html);
    }

    public function test_crear_lote_aparte_aunque_el_destino_tenga_la_especie(): void
    {
        $proveedor = new Tercero(['nombres' => 'Alevinos Ficticios']);
        $proveedor->asignarProvee([Tercero::PROVEE_ALEVINOS]);
        $proveedor->save();
        $origen = $this->lote($this->origen, '2026-09-01', extra: ['proveedor_id' => $proveedor->id, 'lote_alevinos' => 'A-17', 'numero_lote' => '2026-004']);
        $existente = $this->lote($this->destino, '2026-10-01', 600, 120);

        $this->trasladar($origen, ['modo' => 'aparte'])->assertSessionHasNoErrors();

        $this->assertSame(600, $existente->fresh()->peces_vivos);   // no se tocó
        $aparte = $this->destino->siembrasActivas()->where('id', '!=', $existente->id)->sole();
        $this->assertSame(Siembra::ORIGEN_TRASLADO, $aparte->origen);
        $this->assertSame(100, $aparte->peces_vivos);
        $this->assertEquals(50.0, (float) $aparte->peso_actual_g);
        // Conserva la trazabilidad del origen
        $this->assertSame([$proveedor->id, 'A-17', '2026-004'], [$aparte->proveedor_id, $aparte->lote_alevinos, $aparte->numero_lote]);
        $this->assertSame(Traslado::MODO_APARTE, Traslado::query()->sole()->modo);
    }

    public function test_el_lote_aparte_recibe_numero_si_el_origen_no_tiene_y_la_numeracion_es_automatica(): void
    {
        AjustesFinca::guardar(User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]), [
            CatalogoAjustes::campo(CatalogoAjustes::LOTES_NUMERACION) => CatalogoAjustes::NUMERACION_AUTOMATICA,
        ]);
        $origen = $this->lote($this->origen, '2026-09-01');

        $this->trasladar($origen, ['modo' => 'aparte'])->assertSessionHasNoErrors();

        $this->assertSame('2026-001', $this->destino->siembrasActivas()->sole()->numero_lote);
    }

    public function test_sumar_al_unico_lote_de_la_especie(): void
    {
        $origen = $this->lote($this->origen, '2026-09-01');
        $existente = $this->lote($this->destino, '2026-10-01', 600, 100);

        $this->trasladar($origen, ['modo' => 'sumar'])->assertSessionHasNoErrors();

        $existente->refresh();
        $this->assertSame(700, $existente->peces_vivos);
        $this->assertEquals(round((600 * 100 + 100 * 50) / 700, 2), round((float) $existente->peso_actual_g, 2));
        $this->assertSame('2026-10-01', $existente->fecha->toDateString());   // conserva sus días
        $this->assertSame(1, $this->destino->siembrasActivas()->count());
        $this->assertSame(Traslado::MODO_SUMAR, Traslado::query()->sole()->modo);
    }

    public function test_sumar_sin_lote_de_la_especie_no_adivina(): void
    {
        $origen = $this->lote($this->origen, '2026-09-01');
        // Otra especie en el destino: no hay a qué sumar
        Siembra::create(['lago_id' => $this->destino->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 10,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 5, 'biomasa_kg' => 0.05]);

        $this->trasladar($origen, ['modo' => 'sumar'])->assertSessionHasErrors('modo');
        $this->assertSame(0, Traslado::query()->count());
        $this->assertSame(1000, $origen->fresh()->peces_vivos);
    }

    public function test_con_varios_lotes_hay_que_elegir_y_solo_uno_del_destino(): void
    {
        $origen = $this->lote($this->origen, '2026-09-01');
        $viejo = $this->lote($this->destino, '2026-08-01', 500, 100);
        $nuevo = $this->lote($this->destino, '2026-10-01', 300, 60);
        $otroLago = $this->lote(Lago::create(['nombre' => 'L03', 'tipo' => 'tierra']), '2026-08-01');
        $futuro = $this->lote($this->destino, '2026-11-10', 50, 10);

        $this->trasladar($origen, ['modo' => 'sumar'])->assertSessionHasErrors('siembra_destino_id');
        $this->trasladar($origen, ['modo' => 'sumar', 'siembra_destino_id' => $otroLago->id])->assertSessionHasErrors('siembra_destino_id');
        // Un lote sembrado después de la fecha del traslado no recibe peces
        $this->trasladar($origen, ['modo' => 'sumar', 'siembra_destino_id' => $futuro->id, 'fecha' => '2026-11-05'])->assertSessionHasErrors('siembra_destino_id');
        $this->assertSame(0, Traslado::query()->count());

        $this->trasladar($origen, ['modo' => 'sumar', 'siembra_destino_id' => $nuevo->id])->assertSessionHasNoErrors();
        $this->assertSame(400, $nuevo->fresh()->peces_vivos);
        $this->assertSame(500, $viejo->fresh()->peces_vivos);   // el más viejo ya no se elige solo
    }

    public function test_la_lista_de_lotes_del_formulario_dice_su_lago(): void
    {
        $origen = $this->lote($this->origen, '2026-09-01');
        $destino = $this->lote($this->destino, '2026-10-01', 600, 100, ['numero_lote' => 'L-9']);

        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->origen))->assertOk()
            ->assertSee('data-traslado-lote', false)
            ->assertSee('value="'.$destino->id.'" data-lago="'.$this->destino->id.'"', false)
            ->assertSee(__('lagos.traslado.opcion_lote_numero', ['lago' => 'L02', 'numero' => 'L-9', 'fecha' => '01/10/2026', 'peces' => '600']));
        $this->assertNotNull($origen);
    }
}
