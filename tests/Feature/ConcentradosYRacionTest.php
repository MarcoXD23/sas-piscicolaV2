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
        $this->actingAs($this->propietario)->post(route('admin.concentrados.store'), [
            'nombre' => 'Italcol Mojarras 24',
            'nombre_corto' => 'P24',
            'proteina_porcentaje' => 24,
            'pellet_mm' => '4.5 mm',
            'peso_bulto_kg' => 40,
        ])->assertRedirect(route('admin.concentrados.index'));

        $concentrado = Concentrado::query()->where('nombre_corto', 'P24')->firstOrFail();
        $this->assertSame('Italcol Mojarras 24', $concentrado->nombre);
        $this->assertEquals(24.0, (float) $concentrado->proteina_porcentaje);
        $this->assertSame('P24 (Italcol Mojarras 24)', $concentrado->etiqueta());

        // Editar concentrado y sincronizar nombre en stock de bodega
        $stock = StockAlimento::create([
            'tipo_concentrado' => 'Italcol Mojarras 24',
            'concentrado_id' => $concentrado->id,
            'cantidad_kg' => 80,
        ]);

        $this->actingAs($this->propietario)->put(route('admin.concentrados.update', $concentrado), [
            'nombre' => 'Solla Mojarras 25',
            'nombre_corto' => 'P25',
            'proteina_porcentaje' => 25,
            'pellet_mm' => '5 mm',
            'peso_bulto_kg' => 50,
            'activo' => 1,
        ])->assertRedirect(route('admin.concentrados.index'));

        $this->assertSame('Solla Mojarras 25', $stock->fresh()->tipo_concentrado);
        $this->assertSame('P25', $concentrado->fresh()->nombre_corto);

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
        $this->actingAs($this->propietario)->post(route('admin.bodega.despachos.store'), [
            'fecha' => '2026-10-15',
            'proveedor' => 'Italcol',
            'tipo_concentrado' => 'Finalizador 24%',
            'bultos' => 5,
            'peso_bulto_kg' => 40,
            'lote' => 'LOT-24',
        ])->assertRedirect(route('admin.bodega.index'));

        $despacho = DespachoAlimento::query()->firstOrFail();
        $this->assertNotNull($despacho->concentrado_id);

        $this->actingAs($this->admin)->post(route('admin.bodega.recepciones.store'), [
            'despacho_id' => $despacho->id,
            'fecha' => '2026-10-15',
            'bultos' => 5,
            'peso_bulto_kg' => 40,
        ])->assertRedirect(route('admin.bodega.index'));

        $stock = StockAlimento::query()->where('tipo_concentrado', 'Finalizador 24%')->firstOrFail();
        $this->assertSame($despacho->concentrado_id, $stock->concentrado_id);
        $this->assertEquals(200.0, (float) $stock->cantidad_kg);
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

        // El propietario puede configurar los días de alerta de muestreo en Ajustes
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), [
            'nombre' => $this->finca->nombre,
            'pais' => 'CO',
            'zona_horaria' => 'America/Bogota',
            'idioma' => 'es',
            'alerta_muestreo_dias' => 20,
        ])->assertRedirect(route('admin.ajustes.edit'));

        $this->assertSame(20, $this->finca->fresh()->alerta_muestreo_dias);
    }
}
