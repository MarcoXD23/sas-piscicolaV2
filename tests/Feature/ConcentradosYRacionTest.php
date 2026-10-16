<?php

namespace Tests\Feature;

use App\Models\Alerta;
use App\Models\Concentrado;
use App\Models\DespachoAlimento;
use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\TablaRacion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Alertas\EvaluadorAlertas;
use App\Support\CalculadoraRacion;
use App\Support\Campo\PaqueteTrabajo;
use App\Support\CoberturaBodega;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConcentradosYRacionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private User $admin;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca Las Aguas', 'codigo' => 'FLA']);
        app(TenantActual::class)->establecer($this->finca->id);

        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->admin = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
    }

    public function test_al_crear_finca_se_carga_la_tabla_de_racion_inicial_por_especie(): void
    {
        $especieId = $this->especie('mojarra roja');
        $tramos = TablaRacion::query()->where('especie_id', $especieId)->orderBy('peso_hasta_g')->get();

        $this->assertCount(8, $tramos);
        $this->assertEquals(0.1, (float) $tramos->first()->peso_desde_g);
        $this->assertEquals(5.0, (float) $tramos->first()->peso_hasta_g);
        $this->assertEquals(45.0, (float) $tramos->first()->proteina_porcentaje);
    }

    public function test_catalogo_de_concentrados_crear_editar_y_sincronizar_bodega(): void
    {
        // Lo que ya estaba en bodega con ese nombre queda ligado al crearlo en el catálogo, sin cambiar su texto
        $movimiento = MovimientoAlimento::create(['user_id' => $this->admin->id, 'fecha' => '2026-10-10', 'tipo_movimiento' => 'entrada',
            'tipo_concentrado' => 'mojarras 24', 'cantidad_kg' => 80]);
        $stock = StockAlimento::create(['tipo_concentrado' => 'mojarras 24', 'cantidad_kg' => 80]);

        $this->actingAs($this->propietario)->post(route('admin.concentrados.store'), [
            'nombre' => 'Mojarras 24',
            'nombre_corto' => 'Azul',
            'proteina_porcentaje' => 24,
            'pellet_mm' => '4.5 mm',
            'peso_bulto_kg' => 40,
        ])->assertRedirect(route('admin.concentrados.index'));

        $concentrado = Concentrado::query()->where('nombre_corto', 'Azul')->firstOrFail();
        $this->assertSame('Mojarras 24', $concentrado->nombre);
        $this->assertEquals(24.0, (float) $concentrado->proteina_porcentaje);
        $this->assertSame('Azul (Mojarras 24)', $concentrado->etiqueta());
        $this->assertSame([$concentrado->id, 'mojarras 24'], [$stock->fresh()->concentrado_id, $stock->fresh()->tipo_concentrado]);
        $this->assertSame([$concentrado->id, 'mojarras 24'], [$movimiento->fresh()->concentrado_id, $movimiento->fresh()->tipo_concentrado]);

        // Renombrar no cambia la historia: la bodega y los movimientos guardados conservan su texto
        $this->actingAs($this->propietario)->put(route('admin.concentrados.update', $concentrado), [
            'nombre' => 'Mojarras 25',
            'nombre_corto' => 'Blanca',
            'proteina_porcentaje' => 25,
            'pellet_mm' => '5 mm',
            'peso_bulto_kg' => 50,
            'activo' => 1,
        ])->assertRedirect(route('admin.concentrados.index'));

        $this->assertSame('Mojarras 25', $concentrado->fresh()->nombre);
        $this->assertSame('Blanca', $concentrado->fresh()->nombre_corto);
        $this->assertSame('mojarras 24', $stock->fresh()->tipo_concentrado);
        $this->assertSame('mojarras 24', $movimiento->fresh()->tipo_concentrado);
        $this->assertSame($concentrado->id, $movimiento->fresh()->concentrado_id);
        // El nombre viejo (el que puede traer la cola de un teléfono) sigue llevando al mismo concentrado
        $this->assertSame($concentrado->id, Concentrado::resolver('Mojarras 24')?->id);
        $this->assertSame($concentrado->id, Concentrado::resolver('blanca')?->id);

        // Administrador y trabajador no pueden entrar a Ajustes › Concentrados (exclusivo del propietario)
        $this->actingAs($this->admin)
            ->get(route('admin.concentrados.index'))
            ->assertForbidden();
        $this->actingAs($this->trabajador)
            ->get(route('admin.concentrados.index'))
            ->assertForbidden();
    }

    public function test_tabla_de_racion_configurable_y_concentrado_sugerido(): void
    {
        $concentrado = Concentrado::create([
            'nombre' => 'Iniciador 38%',
            'nombre_corto' => 'P38',
            'proteina_porcentaje' => 38,
            'pellet_mm' => '2 mm',
            'peso_bulto_kg' => 40,
        ]);

        $especieId = $this->especie('mojarra roja');

        $this->actingAs($this->propietario)->post(route('admin.concentrados.racion.guardar'), [
            'especie_id' => $especieId,
            'peso_desde_g' => 5.01,
            'peso_hasta_g' => 20,
            'porcentaje_biomasa' => 6.0,
            'tomas_dia' => 4,
            'proteina_porcentaje' => 38,
            'pellet' => '2 mm',
            'concentrado_id' => $concentrado->id,
        ])->assertRedirect(route('admin.concentrados.index'));

        $lago = Lago::create(['nombre' => 'Lago 1', 'tipo' => 'tierra']);
        Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $especieId,
            'fecha' => '2026-09-15',
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 5,
            'peces_vivos' => 1000,
            'peso_actual_g' => 20,
            'biomasa_kg' => 20,
        ]);

        $plan = CalculadoraRacion::paraLago($lago->load('siembrasActivas.especie'));

        $this->assertSame(1.2, $plan['total_kg_dia']);
        $this->assertSame(0.3, $plan['kg_por_toma']);
        $this->assertSame('Iniciador 38%', $plan['concentrado_sugerido']);
        $this->assertSame('P38 (Iniciador 38%)', $plan['concentrado_etiqueta']);
    }

    public function test_cambio_gradual_de_concentrado_en_tres_dias_cuarto_mitad_y_tres_cuartos(): void
    {
        $p38 = Concentrado::create(['nombre' => 'Levante 38', 'nombre_corto' => 'P38', 'proteina_porcentaje' => 38, 'pellet_mm' => '2 mm', 'peso_bulto_kg' => 40]);
        $p32 = Concentrado::create(['nombre' => 'Levante 32', 'nombre_corto' => 'P32', 'proteina_porcentaje' => 32, 'pellet_mm' => '3 mm', 'peso_bulto_kg' => 40]);

        $especieId = $this->especie('mojarra roja');
        TablaRacion::query()
            ->where('especie_id', $especieId)
            ->where('peso_hasta_g', 20)
            ->update(['concentrado_id' => $p38->id]);
        TablaRacion::query()
            ->where('especie_id', $especieId)
            ->where('peso_hasta_g', 50)
            ->update(['concentrado_id' => $p32->id]);

        $lago = Lago::create(['nombre' => 'Lago Gradual', 'tipo' => 'tierra']);
        Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $especieId,
            'fecha' => '2026-09-01',
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 20,
            'peces_vivos' => 1000,
            'peso_actual_g' => 40,
            'biomasa_kg' => 40,
        ]);

        // El lago venía recibiendo Levante 38 ayer y ya recibió un día del nuevo Levante 32 => hoy va en el día 2 de 3 (1/2)
        MovimientoAlimento::create([
            'user_id' => $this->admin->id,
            'lago_id' => $lago->id,
            'concentrado_id' => $p38->id,
            'fecha' => '2026-10-13',
            'tipo_movimiento' => 'salida',
            'tipo_concentrado' => 'Levante 38',
            'cantidad_kg' => 2.0,
        ]);
        MovimientoAlimento::create([
            'user_id' => $this->admin->id,
            'lago_id' => $lago->id,
            'concentrado_id' => $p32->id,
            'fecha' => '2026-10-14',
            'tipo_movimiento' => 'salida',
            'tipo_concentrado' => 'Levante 32',
            'cantidad_kg' => 0.5,
        ]);

        $planDia2 = CalculadoraRacion::paraLago($lago->fresh()->load('siembrasActivas.especie'));
        $gradual = $planDia2['cambio_gradual'];

        $this->assertNotNull($gradual);
        $this->assertSame(2, $gradual['dia']);
        $this->assertSame('1/2', $gradual['fraccion']);
        $this->assertSame('P38 (Levante 38)', $gradual['anterior']);
        $this->assertSame('P32 (Levante 32)', $gradual['nuevo']);

        // Día 1 (1/4 nuevo + 3/4 anterior)
        $planDia1 = CalculadoraRacion::planCambioGradual(4.0, 'P38', 'P32', 1);
        $this->assertSame('1/4', $planDia1['fraccion']);
        $this->assertSame(1.0, $planDia1['kg_nuevo']);
        $this->assertSame(3.0, $planDia1['kg_anterior']);

        // Día 3 (3/4 nuevo + 1/4 anterior)
        $planDia3 = CalculadoraRacion::planCambioGradual(4.0, 'P38', 'P32', 3);
        $this->assertSame('3/4', $planDia3['fraccion']);
        $this->assertSame(3.0, $planDia3['kg_nuevo']);
        $this->assertSame(1.0, $planDia3['kg_anterior']);
    }

    public function test_registro_de_racion_en_bultos_o_kg_y_enlace_por_nombre_corto(): void
    {
        $concentrado = Concentrado::create([
            'nombre' => 'Engorde 25%',
            'nombre_corto' => 'P25',
            'proteina_porcentaje' => 25,
            'pellet_mm' => '4.5 mm',
            'peso_bulto_kg' => 40,
        ]);

        StockAlimento::create([
            'tipo_concentrado' => 'Engorde 25%',
            'concentrado_id' => $concentrado->id,
            'cantidad_kg' => 200,
        ]);

        $lago = Lago::create(['nombre' => 'Lago Engorde', 'tipo' => 'tierra']);
        Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => '2026-09-01',
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10,
            'peces_vivos' => 1000,
            'peso_actual_g' => 150,
            'biomasa_kg' => 150,
        ]);

        // Registrar ración en bultos (1.5 bultos × 40 kg = 60 kg) enviando el nombre corto P25
        $this->actingAs($this->admin)
            ->from(route('admin.lagos.show', $lago))
            ->post(route('admin.raciones.store', $lago), [
                'fecha' => '2026-10-15',
                'tipo_concentrado' => 'P25',
                'unidad_racion' => 'bultos',
                'bultos' => 1.5,
            ])->assertRedirect(route('admin.lagos.show', $lago));

        $movimiento = MovimientoAlimento::query()->where('lago_id', $lago->id)->firstOrFail();
        $this->assertEquals(60.0, (float) $movimiento->cantidad_kg);
        $this->assertSame($concentrado->id, $movimiento->concentrado_id);
        $this->assertSame('Engorde 25%', $movimiento->tipo_concentrado);
        $this->assertEquals(140.0, (float) StockAlimento::query()->where('concentrado_id', $concentrado->id)->value('cantidad_kg'));

        // Cobertura de bodega carga la relación con el concentrado del catálogo
        $cobertura = CoberturaBodega::calcular();
        $filaStock = $cobertura->first(fn (array $item) => $item['stock']->tipo_concentrado === 'Engorde 25%');
        $this->assertNotNull($filaStock);
        $this->assertEquals(40.0, (float) $filaStock['stock']->concentrado->peso_bulto_kg);

        // Paquete de trabajo para Registro rápido incluye concentrado_sugerido, nombre_corto y peso_bulto_kg
        $paquete = PaqueteTrabajo::para($this->admin);
        $this->assertSame('P25', $paquete['concentrados'][0]['nombre_corto']);
        $this->assertSame(40.0, $paquete['concentrados'][0]['peso_bulto_kg']);
    }

    public function test_despacho_y_recepcion_en_bodega_enlazan_el_concentrado_del_catalogo(): void
    {
        $catalogo = Concentrado::create(['nombre' => 'Finalizador 24%', 'nombre_corto' => 'Azul', 'proteina_porcentaje' => 24, 'peso_bulto_kg' => 40]);
        $despachar = fn (string $tipo) => $this->actingAs($this->propietario)->post(route('admin.bodega.despachos.store'), [
            'fecha' => '2026-10-15', 'proveedor' => 'Proveedor', 'tipo_concentrado' => $tipo, 'bultos' => 5, 'peso_bulto_kg' => 40, 'lote' => 'LOT-24',
        ])->assertRedirect(route('admin.bodega.index'));

        // Por su nombre corto: queda con el nombre y el id del catálogo
        $despachar('azul');
        $despacho = DespachoAlimento::query()->latest('id')->firstOrFail();
        $this->assertSame([$catalogo->id, 'Finalizador 24%'], [$despacho->concentrado_id, $despacho->tipo_concentrado]);

        $this->actingAs($this->admin)->post(route('admin.bodega.recepciones.store'), [
            'despacho_id' => $despacho->id,
            'fecha' => '2026-10-15',
            'bultos' => 5,
            'peso_bulto_kg' => 40,
        ])->assertRedirect(route('admin.bodega.index'));

        $stock = StockAlimento::query()->where('tipo_concentrado', 'Finalizador 24%')->firstOrFail();
        $this->assertSame($catalogo->id, $stock->concentrado_id);
        $this->assertEquals(200.0, (float) $stock->cantidad_kg);

        // Un nombre que no está en el catálogo se guarda como texto, sin concentrado: la bodega no agrega al catálogo
        $despachar('Concentrado nuevo');
        $this->assertNull(DespachoAlimento::query()->latest('id')->first()->concentrado_id);
        $this->actingAs($this->admin)->post(route('admin.bodega.recepciones.store'), [
            'fecha' => '2026-10-15', 'tipo_concentrado' => 'Otro sin catálogo', 'proveedor' => 'Proveedor', 'lote' => 'L2', 'bultos' => 2, 'peso_bulto_kg' => 40,
        ])->assertSessionHasNoErrors();
        $this->assertNull(StockAlimento::query()->where('tipo_concentrado', 'Otro sin catálogo')->sole()->concentrado_id);
        $this->assertSame(1, Concentrado::count());
    }

    public function test_el_concentrado_de_la_fase_manda_sobre_el_del_tramo_de_peso(): void
    {
        $delTramo = Concentrado::create(['nombre' => 'Treinta', 'nombre_corto' => 'Blanca', 'proteina_porcentaje' => 30]);
        $deFase = Concentrado::create(['nombre' => 'Veinticuatro', 'nombre_corto' => 'Azul', 'proteina_porcentaje' => 24]);
        $especieId = $this->especie('cachama blanca');
        TablaRacion::query()->where('especie_id', $especieId)->where('peso_hasta_g', 200)->update(['concentrado_id' => $delTramo->id]);
        StockAlimento::create(['tipo_concentrado' => 'veinticuatro en bodega', 'concentrado_id' => $deFase->id, 'cantidad_kg' => 100]);

        $engorde = \App\Models\Fase::query()->where('clave', 'engorde')->firstOrFail();
        $lago = Lago::create(['nombre' => 'E1', 'tipo' => 'tierra', 'fase_id' => $engorde->id]);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $especieId, 'fecha' => '2026-09-01', 'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 150, 'peces_vivos' => 100, 'peso_actual_g' => 180, 'biomasa_kg' => 18]);

        // Sin concentrado en la regla de la fase: el del tramo de peso
        $this->assertSame('Treinta', CalculadoraRacion::paraLago($lago->fresh())['concentrado_sugerido']);

        // Con concentrado en la regla de la fase (Ajustes › Fases): ese, con el texto con que está en bodega
        $this->actingAs($this->propietario)->post(route('admin.fases.reglas.guardar', $engorde), [
            'especie_id' => $especieId, 'peso_entrada_g' => 150, 'dias_esperados' => 90, 'concentrado_id' => $deFase->id,
        ])->assertSessionHasNoErrors();
        $racion = CalculadoraRacion::paraLago($lago->fresh());
        $this->assertSame('veinticuatro en bodega', $racion['concentrado_sugerido']);
        $this->assertSame('Azul (Veinticuatro)', $racion['concentrado_etiqueta']);

        // Un concentrado de otra finca no se puede poner en la regla
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'OTRA']);
        $idAjeno = TenantActual::sinFiltro(fn () => tap((new Concentrado(['nombre' => 'Ajeno']))->forceFill(['tenant_id' => $otra->id]))->save()->id);
        $this->actingAs($this->propietario)->post(route('admin.fases.reglas.guardar', $engorde), [
            'especie_id' => $especieId, 'concentrado_id' => $idAjeno,
        ])->assertSessionHasErrors('concentrado_id');
    }

    public function test_alerta_de_muestreo_vencido_a_los_30_dias_y_cierre_al_muestrear(): void
    {
        $lago = Lago::create(['nombre' => 'Lago Muestreo', 'tipo' => 'tierra']);
        $siembra = Siembra::create([
            'lago_id' => $lago->id,
            'especie_id' => $this->especie('mojarra roja'),
            'fecha' => '2026-09-01',
            'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10,
            'peces_vivos' => 1000,
            'peso_actual_g' => 50,
            'biomasa_kg' => 50,
        ]);

        // Muestreo hace 35 días (2026-09-10)
        Muestreo::create([
            'siembra_id' => $siembra->id,
            'user_id' => $this->admin->id,
            'fecha' => '2026-09-10',
            'peces_pesados' => 30,
            'peso_total_g' => 1500,
            'peso_promedio_g' => 50,
        ]);

        EvaluadorAlertas::paraFinca($this->finca->id);

        $alerta = Alerta::abiertas()->where('tipo', Alerta::MUESTREO)->where('referencia', (string) $lago->id)->first();
        $this->assertNotNull($alerta);
        $this->assertSame(35, $alerta->datos['dias']);
        $this->assertSame(30, $alerta->datos['plazo']);

        // Al registrar un muestreo reciente hoy, la alerta se cierra automáticamente
        Muestreo::create([
            'siembra_id' => $siembra->id,
            'user_id' => $this->admin->id,
            'fecha' => '2026-10-15',
            'peces_pesados' => 30,
            'peso_total_g' => 3000,
            'peso_promedio_g' => 100,
        ]);

        $this->assertNull(Alerta::abiertas()->where('tipo', Alerta::MUESTREO)->where('referencia', (string) $lago->id)->first());
        $this->assertNotNull($alerta->fresh()->cerrada_at);

        // El plazo es el ajuste «muestreo.plazo_dias» (Ajustes › Operación de la finca). Un lote sin ningún muestreo
        // cuenta desde la siembra: 25 días no pasan de 30, pero sí de 20
        $otroLago = Lago::create(['nombre' => 'Lago Nuevo', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $otroLago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-20', 'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 100, 'peso_actual_g' => 10, 'biomasa_kg' => 1]);
        EvaluadorAlertas::paraFinca($this->finca->id);
        $this->assertNull(Alerta::abiertas()->where('tipo', Alerta::MUESTREO)->where('referencia', (string) $otroLago->id)->first());

        $campo = CatalogoAjustes::campo(CatalogoAjustes::MUESTREO_PLAZO_DIAS);
        $this->actingAs($this->propietario)->put(route('admin.ajustes.operacion'), [
            CatalogoAjustes::campo(CatalogoAjustes::AGUA_QUIEN_MIDE) => 'ambos', $campo => 20,
        ])->assertSessionHasNoErrors();
        $this->assertSame(20, AjustesFinca::valor(CatalogoAjustes::MUESTREO_PLAZO_DIAS));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('tenants', 'alerta_muestreo_dias'));

        EvaluadorAlertas::paraFinca($this->finca->id);
        $alertaNueva = Alerta::abiertas()->where('tipo', Alerta::MUESTREO)->where('referencia', (string) $otroLago->id)->sole();
        $this->assertSame([25, 20], [$alertaNueva->datos['dias'], $alertaNueva->datos['plazo']]);
    }
}
