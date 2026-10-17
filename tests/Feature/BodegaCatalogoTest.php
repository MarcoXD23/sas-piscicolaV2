<?php

namespace Tests\Feature;

use App\Models\Concentrado;
use App\Models\DespachoAlimento;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Bodega: en «Registrar despacho» y en «Recibir sin despacho» el concentrado se elige de una lista desplegable con los
 * concentrados activos del catálogo de la finca (Ajustes › Concentrados). Nada de texto libre ni de datalist.
 */
class BodegaCatalogoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
    }

    private function despachar(array $datos)
    {
        return $this->actingAs($this->propietario)->post(route('admin.bodega.despachos.store'), $datos + [
            'fecha' => '2026-10-15', 'proveedor' => 'Proveedor', 'lote' => 'L-1', 'bultos' => 10, 'peso_bulto_kg' => 40,
        ]);
    }

    private function recibirSinDespacho(array $datos)
    {
        return $this->actingAs($this->administrador)->post(route('admin.bodega.recepciones.store'), $datos + [
            'fecha' => '2026-10-15', 'proveedor' => 'Proveedor', 'lote' => 'L-2', 'bultos' => 5, 'peso_bulto_kg' => 40,
        ]);
    }

    private function deOtraFinca(): Concentrado
    {
        $otra = Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'F02']);

        return TenantActual::sinFiltro(fn () => tap((new Concentrado(['nombre' => 'De la otra finca']))->forceFill(['tenant_id' => $otra->id]))->save());
    }

    public function test_la_lista_desplegable_trae_solo_los_activos_de_la_finca(): void
    {
        Concentrado::create(['nombre' => 'Engorde', 'proteina_porcentaje' => 32]);
        Concentrado::create(['nombre' => 'Levante']);
        Concentrado::create(['nombre' => 'Viejo', 'activo' => false]);
        $this->deOtraFinca();

        $html = $this->actingAs($this->propietario)->get(route('admin.bodega.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<select[^>]*name="concentrado_id"[^>]*id="despacho-concentrado"|<select[^>]*id="despacho-concentrado"[^>]*name="concentrado_id"/', $html);
        $this->assertStringContainsString(__('bodega.concentrado_elige'), $html);
        $this->assertStringContainsString('Engorde · 32 % de proteína', $html);
        $this->assertStringContainsString('>Levante</option>', $html);
        $this->assertStringNotContainsString('Viejo', $html);
        $this->assertStringNotContainsString('De la otra finca', $html);
        // Ni texto libre ni sugerencias
        $this->assertStringNotContainsString('<datalist', $html);
        $this->assertStringNotContainsString('name="tipo_concentrado"', $html);

        $this->actingAs($this->administrador)->get(route('admin.bodega.index'))->assertOk()
            ->assertSee('id="recepcion-concentrado"', false)->assertSee('Engorde · 32 % de proteína')->assertDontSee('<datalist', false);
    }

    public function test_despacho_y_recepcion_guardan_el_concentrado_del_catalogo(): void
    {
        $engorde = Concentrado::create(['nombre' => 'Engorde', 'proteina_porcentaje' => 32]);

        $this->despachar(['concentrado_id' => $engorde->id])->assertSessionHasNoErrors();
        $despacho = DespachoAlimento::query()->sole();
        $this->assertSame([$engorde->id, 'Engorde'], [$despacho->concentrado_id, $despacho->tipo_concentrado]);

        $this->recibirSinDespacho(['concentrado_id' => $engorde->id])->assertSessionHasNoErrors();
        $entrada = MovimientoAlimento::query()->sole();
        $this->assertSame([$engorde->id, 'Engorde', null], [$entrada->concentrado_id, $entrada->tipo_concentrado, $entrada->despacho_id]);
        $stock = StockAlimento::query()->sole();
        $this->assertSame([$engorde->id, 'Engorde', 200.0], [$stock->concentrado_id, $stock->tipo_concentrado, (float) $stock->cantidad_kg]);
    }

    public function test_rechaza_uno_de_otra_finca_uno_inactivo_uno_inventado_y_texto_libre(): void
    {
        Concentrado::create(['nombre' => 'Engorde']);
        $inactivo = Concentrado::create(['nombre' => 'Viejo', 'activo' => false]);
        $ajeno = $this->deOtraFinca();

        foreach ([$ajeno->id, $inactivo->id, 999999, 'Engorde'] as $malo) {
            $this->despachar(['concentrado_id' => $malo])->assertSessionHasErrors(['concentrado_id' => __('bodega.concentrado_no_valido')]);
            $this->recibirSinDespacho(['concentrado_id' => $malo])->assertSessionHasErrors(['concentrado_id' => __('bodega.concentrado_no_valido')]);
        }
        // Sin elegir, o con el nombre escrito como antes: no se guarda
        $this->despachar([])->assertSessionHasErrors(['concentrado_id' => __('bodega.concentrado_elige')]);
        $this->despachar(['tipo_concentrado' => 'Engorde'])->assertSessionHasErrors('concentrado_id');
        $this->recibirSinDespacho(['tipo_concentrado' => 'Engorde'])->assertSessionHasErrors(['concentrado_id' => __('bodega.concentrado_elige')]);

        $this->assertSame(0, DespachoAlimento::count());
        $this->assertSame(0, MovimientoAlimento::count());
    }

    public function test_sin_catalogo_avisa_y_no_deja_despachar_pero_se_recibe_un_despacho_pendiente(): void
    {
        // Un despacho viejo, de antes del catálogo (solo el texto)
        $viejo = DespachoAlimento::create(['user_id' => $this->propietario->id, 'fecha' => '2026-10-10', 'proveedor' => 'P', 'tipo_concentrado' => '32',
            'bultos' => 2, 'peso_bulto_kg' => 40, 'cantidad_kg' => 80, 'lote' => 'V']);

        // El propietario ve el aviso con el enlace a Concentrados y el botón deshabilitado
        $html = $this->actingAs($this->propietario)->get(route('admin.bodega.index'))->assertOk()->getContent();
        $this->assertStringContainsString(__('bodega.sin_catalogo_propietario'), $html);
        $this->assertStringContainsString(route('admin.concentrados.index'), $html);
        $this->assertMatchesRegularExpression('/<button[^>]*disabled[^>]*>\s*'.preg_quote(__('bodega.guardar_despacho'), '/').'/', $html);

        // El administrador ve el aviso sin el enlace (Concentrados es del propietario)
        $this->actingAs($this->administrador)->get(route('admin.bodega.index'))->assertOk()
            ->assertSee(__('bodega.sin_catalogo_administrador'))->assertDontSee(route('admin.concentrados.index'), false);

        // El servidor tampoco deja despachar ni recibir sin despacho
        $this->despachar(['concentrado_id' => ''])->assertSessionHasErrors('concentrado_id');
        $this->recibirSinDespacho([])->assertSessionHasErrors('concentrado_id');

        // Recibir el despacho pendiente sí funciona: el concentrado es el del despacho
        $this->actingAs($this->administrador)->post(route('admin.bodega.recepciones.store'), [
            'despacho_id' => $viejo->id, 'fecha' => '2026-10-15', 'bultos' => 2, 'peso_bulto_kg' => 40,
        ])->assertSessionHasNoErrors();
        $this->assertSame([80.0, '32'], [(float) StockAlimento::query()->sole()->cantidad_kg, StockAlimento::query()->sole()->tipo_concentrado]);
    }
}
