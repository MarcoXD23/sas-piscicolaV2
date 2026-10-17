<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Concentrado;
use App\Models\DespachoAlimento;
use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Alertas\EvaluadorAlertas;
use App\Support\CoberturaBodega;
use App\Support\ConversionAlimenticia;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DespachoRecepcionTest extends TestCase
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
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
    }

    private function despachar(array $datos = []): DespachoAlimento
    {
        $this->actingAs($this->propietario)->post(route('admin.bodega.despachos.store'), $datos + [
            'fecha' => '2026-10-15', 'proveedor' => 'Proveedor Uno', 'concentrado_id' => $this->concentrado('Engorde'), 'lote' => 'L-1', 'bultos' => 10, 'peso_bulto_kg' => 40,
        ])->assertSessionHasNoErrors();

        return DespachoAlimento::query()->latest('id')->first();
    }

    /** Id de un concentrado del catálogo de la finca (la bodega lo elige de una lista). */
    private function concentrado(string $nombre): int
    {
        return Concentrado::query()->firstOrCreate(['nombre' => $nombre])->id;
    }

    private function recibir(array $datos): TestResponse
    {
        return $this->actingAs($this->administrador)->post(route('admin.bodega.recepciones.store'), $datos + ['fecha' => '2026-10-15', 'peso_bulto_kg' => 40]);
    }

    private function stock(string $tipo = 'Engorde'): float
    {
        return (float) StockAlimento::where('tipo_concentrado', $tipo)->value('cantidad_kg');
    }

    public function test_el_despacho_no_cambia_el_stock_hasta_que_se_recibe(): void
    {
        $despacho = $this->despachar();

        $this->assertSame(400.0, $despacho->cantidad_kg);
        $this->assertSame(DespachoAlimento::SIN_RECEPCION, $despacho->estado());
        $this->assertSame(0.0, $this->stock());

        $this->recibir(['despacho_id' => $despacho->id, 'bultos' => 10])->assertSessionHasNoErrors();

        $this->assertSame(400.0, $this->stock());
        $this->assertSame(DespachoAlimento::EXACTO, $despacho->fresh()->estado());
        // Con despacho: el concentrado, proveedor y lote son los del despacho
        $this->assertDatabaseHas('movimientos_alimento', ['despacho_id' => $despacho->id, 'tipo_concentrado' => 'Engorde', 'proveedor' => 'Proveedor Uno', 'lote' => 'L-1', 'cantidad_kg' => 400]);
        $this->assertSame(0, Alerta::count());
    }

    public function test_faltante_y_sobrante_alertan_al_propietario_sin_bloquear_y_se_cierran_al_revisarlos(): void
    {
        $faltante = $this->despachar(['concentrado_id' => $this->concentrado('Engorde')]);
        $sobrante = $this->despachar(['concentrado_id' => $this->concentrado('Levante')]);

        $this->recibir(['despacho_id' => $faltante->id, 'bultos' => 9])->assertSessionHasNoErrors();   // 360 de 400
        $this->recibir(['despacho_id' => $sobrante->id, 'bultos' => 11])->assertSessionHasNoErrors();  // 440 de 400

        $this->assertSame([DespachoAlimento::FALTANTE, -40.0], [$faltante->fresh()->estado(), $faltante->fresh()->diferenciaKg()]);
        $this->assertSame([DespachoAlimento::SOBRANTE, 40.0], [$sobrante->fresh()->estado(), $sobrante->fresh()->diferenciaKg()]);
        // No bloquea: el stock es lo recibido
        $this->assertSame(360.0, $this->stock('Engorde'));
        $this->assertSame(440.0, $this->stock('Levante'));

        $alertas = Alerta::query()->abiertas()->where('tipo', Alerta::DESPACHO_DIFERENCIA)->get();
        $this->assertCount(2, $alertas);
        $this->actingAs($this->propietario)->get(route('inicio'))->assertSee('faltan 40 kg')->assertSee('sobran 40 kg');
        $this->actingAs($this->administrador)->get(route('inicio'))->assertDontSee('faltan 40 kg');
        $this->actingAs($this->propietario)->get(route('admin.bodega.index'))->assertSee('Faltan 40 kg')->assertSee('Sobran 40 kg')->assertSee(__('bodega.marcar_revisado'));

        $this->actingAs($this->administrador)->post(route('admin.bodega.despachos.revisado', $faltante))->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.bodega.despachos.revisado', $faltante))->assertSessionHasNoErrors();
        $this->assertNotNull($faltante->fresh()->revisado_at);
        $this->assertSame(['sobrante'], Alerta::query()->abiertas()->where('tipo', Alerta::DESPACHO_DIFERENCIA)->get()->map(fn ($a) => $a->datos['estado'])->all());
    }

    public function test_recepcion_sin_despacho(): void
    {
        $this->recibir(['proveedor' => 'Vecino', 'concentrado_id' => $this->concentrado('Iniciación'), 'lote' => 'X', 'bultos' => 2])->assertSessionHasNoErrors();

        $this->assertSame(80.0, $this->stock('Iniciación'));
        $this->assertDatabaseHas('movimientos_alimento', ['tipo_movimiento' => 'entrada', 'despacho_id' => null, 'proveedor' => 'Vecino']);
        $this->actingAs($this->administrador)->get(route('admin.bodega.index'))->assertSee(__('bodega.sin_despacho'));

        // Sin despacho, el concentrado, el proveedor y el lote son obligatorios
        $this->recibir(['bultos' => 2])->assertSessionHasErrors(['concentrado_id', 'proveedor', 'lote']);
    }

    public function test_despacho_sin_recepcion_alerta_despues_de_n_dias_configurables(): void
    {
        $despacho = $this->despachar(['fecha' => '2026-10-13']);   // hace 2 días: todavía no (más de 2)
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertSame(0, Alerta::query()->where('tipo', Alerta::DESPACHO_PENDIENTE)->count());

        $this->travelTo(Carbon::parse('2026-10-16 09:00', 'America/Bogota'));   // 3 días
        EvaluadorAlertas::paraFinca($this->finca->id);
        $alerta = Alerta::query()->abiertas()->where('tipo', Alerta::DESPACHO_PENDIENTE)->sole();
        $this->assertStringContainsString('lleva 3 días sin recepción', $alerta->mensaje());
        $this->actingAs($this->propietario)->get(route('inicio'))->assertSee('lleva 3 días sin recepción');
        $this->actingAs($this->administrador)->get(route('inicio'))->assertSee('lleva 3 días sin recepción');

        // Al recibirlo se cierra
        $this->recibir(['despacho_id' => $despacho->id, 'bultos' => 10, 'fecha' => '2026-10-16'])->assertSessionHasNoErrors();
        $this->assertSame(0, Alerta::query()->abiertas()->where('tipo', Alerta::DESPACHO_PENDIENTE)->count());

        // N es editable por finca: con 2 días un despacho de hace 2 días no alerta; con 1 día, sí
        $otro = $this->despachar(['fecha' => '2026-10-14']);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertSame(0, Alerta::query()->abiertas()->where('tipo', Alerta::DESPACHO_PENDIENTE)->count());
        $this->finca->update(['alerta_despacho_dias' => 1]);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertSame((string) $otro->id, Alerta::query()->abiertas()->where('tipo', Alerta::DESPACHO_PENDIENTE)->sole()->referencia);
    }

    public function test_la_racion_explica_por_que_se_bloquea_y_el_stock_nunca_queda_negativo(): void
    {
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 100, 'peso_actual_g' => 100, 'biomasa_kg' => 10]);
        $racion = fn (float $kg) => $this->actingAs($this->administrador)->post(route('admin.raciones.store', $lago), ['fecha' => '2026-10-15', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => $kg]);

        // Nada recibido todavía (aunque haya un despacho)
        $despacho = $this->despachar();
        $racion(5)->assertSessionHasErrors(['cantidad_kg' => __('mensajes.racion.sin_concentrado_recibido')]);

        $this->recibir(['despacho_id' => $despacho->id, 'bultos' => 1]);   // 40 kg
        $racion(50)->assertSessionHasErrors(['cantidad_kg' => 'Solo hay 40,00 kg de Engorde recibidos. El administrador debe registrar la recepción.']);
        $racion(40)->assertSessionHasNoErrors();
        $this->assertSame(0.0, $this->stock());
        $racion(1)->assertSessionHasErrors(['cantidad_kg' => __('mensajes.racion.sin_concentrado_recibido')]);

        // Segunda barrera: la base rechaza un saldo negativo aunque dos registros se crucen
        $this->expectException(QueryException::class);
        DB::table('stocks_alimento')->where('tipo_concentrado', 'Engorde')->update(['cantidad_kg' => -1]);
    }

    public function test_las_entradas_historicas_siguen_contando_como_recepciones_sin_despacho(): void
    {
        // Entrada creada como antes de esta etapa (sin despacho)
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'fecha' => '2026-10-01', 'tipo_movimiento' => 'entrada',
            'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 200, 'proveedor' => 'Antiguo', 'lote' => 'H', 'bultos' => 5, 'peso_bulto_kg' => 40]);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 200]);

        $this->assertNull(MovimientoAlimento::first()->despacho_id);
        $this->assertSame(200.0, (float) CoberturaBodega::calcular()->sole()['stock']->cantidad_kg);
        $this->actingAs($this->propietario)->get(route('admin.bodega.index'))->assertSee('Antiguo')->assertSee(__('bodega.sin_despacho'));
    }

    public function test_bitacora_conversion_y_alertas_de_stock_siguen_funcionando(): void
    {
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 1000, 'peso_actual_g' => 10, 'biomasa_kg' => 10]);

        $despacho = $this->despachar(['bultos' => 3]);   // 120 kg
        $this->recibir(['despacho_id' => $despacho->id, 'bultos' => 3]);
        $this->actingAs($this->administrador)->post(route('admin.raciones.store', $lago), ['fecha' => '2026-10-15', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 120])->assertSessionHasNoErrors();

        // Bitácora: despacho (propietario), recepción y ración (administrador)
        $this->assertSame(['despacho', 'recepcion', 'racion'], Bitacora::query()->orderBy('id')->pluck('accion')->all());
        $this->assertSame([Rol::PROPIETARIO, Rol::ADMINISTRADOR, Rol::ADMINISTRADOR], Bitacora::query()->orderBy('id')->pluck('rol')->all());

        // Alerta de stock agotado (crítica) tras gastar lo recibido
        $this->assertSame(Alerta::CRITICA, Alerta::query()->abiertas()->where('tipo', Alerta::STOCK)->sole()->severidad);

        // La conversión sigue usando las salidas de bodega
        $siembra->muestreos()->create(['user_id' => $this->administrador->id, 'fecha' => '2026-10-15', 'peces_pesados' => 10, 'peso_total_g' => 1100, 'peso_promedio_g' => 110]);
        $this->assertSame(120.0, (new ConversionAlimenticia)->deSiembra($siembra->fresh())['alimento_kg']);
        $this->assertSame(1.2, (new ConversionAlimenticia)->deSiembra($siembra->fresh())['fca']);   // 120 / (110 − 10)
    }

    public function test_cada_finca_solo_ve_y_recibe_sus_despachos(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $ajeno = DespachoAlimento::create(['user_id' => $this->propietario->id, 'fecha' => '2026-10-15', 'proveedor' => 'ProveedorAjeno',
            'tipo_concentrado' => 'Secreto', 'bultos' => 1, 'peso_bulto_kg' => 40, 'cantidad_kg' => 40, 'lote' => 'Z']);
        app(TenantActual::class)->establecer($this->finca->id);

        $this->recibir(['despacho_id' => $ajeno->id, 'bultos' => 1])->assertSessionHasErrors(['despacho_id' => __('mensajes.bodega.despacho_no_existe')]);
        $this->actingAs($this->propietario)->get(route('admin.bodega.index'))->assertDontSee('ProveedorAjeno');
        $this->actingAs($this->propietario)->post(route('admin.bodega.despachos.revisado', $ajeno))->assertNotFound();
    }
}
