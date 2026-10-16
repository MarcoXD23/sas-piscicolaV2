<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\PesajeCosecha;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\CalculoPesaje;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

/**
 * Pesaje de la cosecha como en el cuaderno: cada PESADA es un montón de pescado en varias canastas o canastillas.
 * descuento = cantidad × peso de UNA vacía (se multiplica); peso entero = peso pesado − descuento.
 */
class PesajesCosechaTest extends TestCase
{
    use RefreshDatabase;

    /** El ejemplo del cuaderno: canasta de 2 kg y canastilla de 1,5 kg. */
    private const EJEMPLO = [
        ['tipo' => 'canasta', 'cantidad' => 6, 'peso_pesado' => 220],
        ['tipo' => 'canasta', 'cantidad' => 5, 'peso_pesado' => 185],
        ['tipo' => 'canastilla', 'cantidad' => 9, 'peso_pesado' => 250],
    ];

    private Tenant $finca;

    private User $administrador;

    private User $propietario;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 15:30', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'name' => 'Ana Admin']))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-05-01',
            'cantidad_alevinos' => 5000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 5000, 'peso_actual_g' => 400, 'biomasa_kg' => 2000]);
    }

    private function cosechar(array $datos = [], ?User $quien = null): TestResponse
    {
        return $this->actingAs($quien ?? $this->administrador)->post(route('admin.cosechas.store'), $datos + [
            'siembra_id' => $this->siembra->id, 'destino' => 'Restaurante El Río', 'fecha' => '2026-10-20', 'peces' => 1200,
            'peso_canasta_kg' => 2, 'peso_canastilla_kg' => 1.5, 'pesadas' => self::EJEMPLO,
        ]);
    }

    // --- Cuentas ---

    public function test_el_ejemplo_del_cuaderno_da_los_descuentos_y_el_total_entero(): void
    {
        $this->cosechar()->assertSessionHasNoErrors()->assertRedirect(route('admin.cosechas.show', Cosecha::sole()));

        $cosecha = Cosecha::sole();
        $this->assertSame(619.5, $cosecha->kg);   // el total entero es el kg de la cosecha

        $pesajes = $cosecha->pesajes;
        $this->assertSame([1, 2, 3], $pesajes->pluck('numero')->all());
        $this->assertSame(['canasta', 'canasta', 'canastilla'], $pesajes->pluck('tipo')->all());
        $this->assertSame([6, 5, 9], $pesajes->pluck('canastas')->all());
        $this->assertSame([12.0, 10.0, 13.5], $pesajes->pluck('tara_kg')->all());      // descuentos
        $this->assertSame([208.0, 175.0, 236.5], $pesajes->pluck('neto_kg')->all());   // pesos enteros

        $totales = CalculoPesaje::deCosecha($cosecha)['totales'];
        $this->assertSame(3, $totales['pesadas']);
        $this->assertSame(20, $totales['cantidad']);
        $this->assertSame(['canasta' => 11, 'canastilla' => 9], $totales['por_tipo']);
        $this->assertEquals(655, $totales['pesado']);
        $this->assertEquals(35.5, $totales['descuento']);
        $this->assertEquals(619.5, $totales['entero']);
        $this->assertSame('11 canastas × 2 kg + 9 canastillas × 1,5 kg = 35,5 kg de descuento; 655 kg − 35,5 kg = 619,5 kg enteros',
            CalculoPesaje::lineaCuenta($totales));
    }

    public function test_el_descuento_se_multiplica_nunca_se_divide(): void
    {
        // 20 canastas de 2 kg = 40 kg de descuento; 655 − 40 = 615 kg enteros
        $calculo = CalculoPesaje::calcular([['tipo' => 'canasta', 'cantidad' => 20, 'peso_pesado' => 655]], 2, 1.5);

        $this->assertEquals(40, $calculo['filas'][0]['descuento']);
        $this->assertEquals(615, $calculo['filas'][0]['entero']);
        $this->assertNotEquals(655 - 2 / 20, $calculo['filas'][0]['entero']);
    }

    public function test_el_servidor_rehace_las_cuentas_y_no_confia_en_totales_del_navegador(): void
    {
        $this->cosechar(['kg' => 9999, 'total_entero' => 9999, 'descuento' => 0, 'meta' => 1000])->assertSessionHasNoErrors();

        $cosecha = Cosecha::sole();
        $this->assertSame(619.5, $cosecha->kg);
        $this->assertSame(35.5, round($cosecha->pesajes->sum('tara_kg'), 2));
    }

    // --- Rechazos ---

    public function test_rechaza_cantidades_pesos_y_tipos_invalidos_con_mensajes_claros(): void
    {
        $con = fn (array $fila) => ['pesadas' => [self::EJEMPLO[0], $fila]];

        $this->cosechar($con(['tipo' => 'canasta', 'cantidad' => 0, 'peso_pesado' => 50]))
            ->assertInvalid(['pesadas.1.cantidad' => 'Pesada 2: la cantidad debe ser un número entero de 1 a 50.']);
        $this->cosechar($con(['tipo' => 'canasta', 'cantidad' => 2.5, 'peso_pesado' => 50]))->assertInvalid(['pesadas.1.cantidad']);
        $this->cosechar($con(['tipo' => 'canasta', 'cantidad' => 51, 'peso_pesado' => 50]))->assertInvalid(['pesadas.1.cantidad']);
        $this->cosechar($con(['tipo' => 'balde', 'cantidad' => 2, 'peso_pesado' => 50]))
            ->assertInvalid(['pesadas.1.tipo' => 'Pesada 2: elige si fueron canastas o canastillas.']);
        $this->cosechar($con(['tipo' => 'canasta', 'cantidad' => 2, 'peso_pesado' => 2001]))->assertInvalid(['pesadas.1.peso_pesado']);
        $this->cosechar($con(['tipo' => 'canasta', 'cantidad' => 2, 'peso_pesado' => '']))->assertInvalid(['pesadas.1.peso_pesado']);
        // Peso pesado igual o menor que el descuento (5 canastas × 2 kg = 10 kg)
        $this->cosechar($con(['tipo' => 'canasta', 'cantidad' => 5, 'peso_pesado' => 10]))
            ->assertInvalid(['pesadas.1.peso_pesado' => 'Pesada 2: el peso pesado (10 kg) debe ser mayor que el descuento (10 kg).']);
        // Peso de una vacía cero o negativo
        $this->cosechar(['peso_canasta_kg' => 0])->assertInvalid(['peso_canasta_kg' => 'El peso de una canasta vacía debe ser mayor que 0']);
        $this->cosechar(['peso_canastilla_kg' => -1])->assertInvalid(['peso_canastilla_kg']);
        $this->cosechar(['peso_canasta_kg' => 25])->assertInvalid(['peso_canasta_kg']);
        // Más de 200 pesadas
        $this->cosechar(['pesadas' => array_fill(0, 201, self::EJEMPLO[0])])->assertInvalid(['pesadas' => 'Máximo 200 pesadas']);

        $this->assertSame(0, Cosecha::count() + PesajeCosecha::count());
        $this->assertSame(5000, $this->siembra->fresh()->peces_vivos);
    }

    public function test_rechaza_destino_vacio_lago_ajeno_o_sin_siembra_y_despacho_que_no_es_personal(): void
    {
        $this->cosechar(['destino' => '  '])->assertInvalid(['destino' => 'Escribe el destino del pedido']);
        $this->cosechar(['destino' => str_repeat('x', 121)])->assertInvalid(['destino']);

        // Siembra finalizada (lago sin siembra activa)
        $finalizada = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => '2026-04-01', 'cantidad_alevinos' => 10,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 0, 'peso_actual_g' => 400, 'biomasa_kg' => 0, 'finalizada_at' => now()]);
        $this->cosechar(['siembra_id' => $finalizada->id])->assertInvalid(['siembra_id' => 'no tiene una siembra activa']);

        // Otra finca: su siembra no existe aquí y su personal no puede despachar
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $lagoAjeno = Lago::create(['nombre' => 'X1', 'tipo' => 'tierra']);
        $siembraAjena = Siembra::create(['lago_id' => $lagoAjeno->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-05-01', 'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 100, 'peso_actual_g' => 400, 'biomasa_kg' => 40]);
        $ajeno = User::factory()->create(['tenant_id' => $otra->id]);
        app(TenantActual::class)->establecer($this->finca->id);

        $this->cosechar(['siembra_id' => $siembraAjena->id])->assertInvalid(['siembra_id']);
        $this->cosechar(['despachado_por_id' => $ajeno->id])->assertInvalid(['despachado_por_id' => 'Quien despachó debe ser una persona aprobada']);
        // Personal de la misma finca pero sin aprobar
        $pendiente = User::factory()->pendiente()->create(['tenant_id' => $this->finca->id]);
        $this->cosechar(['despachado_por_id' => $pendiente->id])->assertInvalid(['despachado_por_id']);

        $this->assertSame(0, Cosecha::count());
        $this->assertSame(100, TenantActual::sinFiltro(fn () => $siembraAjena->fresh()->peces_vivos));
    }

    public function test_si_la_cosecha_falla_no_quedan_pesadas_sueltas(): void
    {
        $this->cosechar(['peces' => 9000])->assertInvalid(['peces']);

        $this->assertSame(0, PesajeCosecha::count());
    }

    public function test_la_base_exige_que_el_descuento_sea_cantidad_por_peso_de_una_vacia(): void
    {
        $this->cosechar(['pesadas' => null, 'kg' => 10])->assertSessionHasNoErrors();
        $cosecha = Cosecha::sole();

        $this->expectException(QueryException::class);
        // 3 canastas de 2 kg deberían descontar 6 kg, no 2
        PesajeCosecha::create(['cosecha_id' => $cosecha->id, 'numero' => 1, 'tipo' => 'canasta', 'canastas' => 3, 'tara_unitaria_kg' => 2,
            'bruto_kg' => 50, 'tara_kg' => 2, 'neto_kg' => 48]);
    }

    // --- Encabezado ---

    public function test_encabezado_despacho_por_defecto_fecha_de_la_finca_y_hora_en_utc(): void
    {
        $this->cosechar()->assertSessionHasNoErrors();
        $cosecha = Cosecha::sole();

        $this->assertSame($this->administrador->id, $cosecha->user_id);
        $this->assertSame($this->administrador->id, $cosecha->despachado_por_id);   // por defecto, quien registra
        $this->assertSame('Restaurante El Río', $cosecha->destino);
        // La hora exacta del registro queda sola, en UTC (15:30 en Bogotá = 20:30 UTC)
        $this->assertSame('2026-10-20 20:30', $cosecha->created_at->utc()->format('Y-m-d H:i'));

        // Otra persona aprobada puede figurar como quien despachó; user_id sigue siendo quien registró
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'name' => 'Luis Campo']))->asignarRoles([Rol::TRABAJADOR]);
        $this->cosechar(['despachado_por_id' => $trabajador->id, 'destino' => 'Plaza de mercado', 'fecha' => '2026-10-18'])->assertSessionHasNoErrors();
        $atrasada = Cosecha::latest('id')->first();
        $this->assertSame($trabajador->id, $atrasada->despachado_por_id);
        $this->assertSame($this->administrador->id, $atrasada->user_id);
        $this->assertSame('2026-10-18', $atrasada->fecha->format('Y-m-d'));   // cosecha atrasada

        $this->cosechar(['fecha' => '2026-10-21'])->assertInvalid(['fecha' => 'La fecha no puede ser futura']);
        $this->cosechar(['fecha' => '2026-04-30'])->assertInvalid(['fecha' => 'anterior a la siembra']);
    }

    public function test_el_formulario_trae_la_fecha_la_hora_fija_los_destinos_usados_y_los_pesos_de_la_finca(): void
    {
        $this->cosechar(['destino' => 'Plaza de mercado'])->assertSessionHasNoErrors();
        // Un destino de otra finca no se sugiere
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        DB::table('cosechas')->insert(['tenant_id' => $otra->id, 'siembra_id' => $this->siembra->id, 'lago_id' => $this->lago->id, 'user_id' => $this->administrador->id,
            'fecha' => '2026-10-19', 'peces' => 1, 'kg' => 1, 'peso_promedio_g' => 1000, 'destino' => 'Destino ajeno', 'created_at' => now(), 'updated_at' => now()]);

        $pagina = $this->actingAs($this->administrador)->get(route('admin.cosechas.create', ['lago' => $this->lago->id]))->assertOk();

        $pagina->assertSee('<option value="Plaza de mercado">', false)
            ->assertDontSee('Destino ajeno')
            ->assertSee('value="2026-10-20"', false)            // fecha de hoy en la zona de la finca
            ->assertSee('15:30')                                // hora del registro: se muestra, no se edita
            ->assertDontSee('name="hora"', false)
            // Pesos por defecto de la finca en los campos de las vacías
            ->assertSeeInOrder(['name="peso_canasta_kg"', 'value="2"'], false)
            ->assertSeeInOrder(['name="peso_canastilla_kg"', 'value="1.5"'], false)
            ->assertSee('<option value="'.$this->siembra->id.'" selected', false)   // lago ya elegido desde su tarjeta
            ->assertSee('<option value="'.$this->administrador->id.'" selected', false);   // despachó: quien registra
    }

    public function test_la_meta_es_solo_ayuda_en_pantalla_y_no_se_guarda(): void
    {
        $pagina = $this->actingAs($this->administrador)->get(route('admin.cosechas.create'))->assertOk();
        // El campo de la meta no tiene nombre: no viaja al servidor
        $this->assertMatchesRegularExpression('/<input[^>]*data-meta(?![^>]*name=)[^>]*>/', $pagina->getContent());
        $pagina->assertSee(__('cosechas.meta'));

        $this->cosechar(['meta' => 700])->assertSessionHasNoErrors();
        $this->assertArrayNotHasKey('meta', Cosecha::sole()->getAttributes());
    }

    // --- Modo total entero ---

    public function test_el_modo_total_entero_sigue_funcionando_y_pide_el_encabezado(): void
    {
        $this->cosechar(['pesadas' => null, 'kg' => 48.5])->assertSessionHasNoErrors();
        $this->assertSame(48.5, Cosecha::sole()->kg);
        $this->assertSame(0, PesajeCosecha::count());

        $this->cosechar(['pesadas' => null, 'kg' => null])->assertInvalid(['kg']);
        $this->cosechar(['pesadas' => null, 'kg' => 10, 'destino' => ''])->assertInvalid(['destino']);
    }

    // --- Pesos por defecto y permisos ---

    public function test_pesos_por_defecto_de_la_finca_y_quien_los_cambia(): void
    {
        $this->assertSame(2.0, $this->finca->fresh()->peso_canasta_kg);
        $this->assertSame(1.5, $this->finca->fresh()->peso_canastilla_kg);

        $this->actingAs($this->administrador)->put(route('admin.ajustes-pesaje.update'), ['peso_canasta_kg' => 2.3, 'peso_canastilla_kg' => 1.25])
            ->assertSessionHasNoErrors();
        $this->assertSame(2.3, $this->finca->fresh()->peso_canasta_kg);
        $this->assertSame(1.25, $this->finca->fresh()->peso_canastilla_kg);

        $this->actingAs($this->administrador)->put(route('admin.ajustes-pesaje.update'), ['peso_canasta_kg' => 0, 'peso_canastilla_kg' => 30])
            ->assertInvalid(['peso_canasta_kg', 'peso_canastilla_kg']);

        // El propietario sin rol de administrador los ve en Ajustes, pero no los cambia
        $this->actingAs($this->propietario)->put(route('admin.ajustes-pesaje.update'), ['peso_canasta_kg' => 5, 'peso_canastilla_kg' => 5])->assertForbidden();
        $this->actingAs($this->propietario)->get(route('admin.ajustes.edit'))->assertOk()
            ->assertSee(__('cosechas.peso_canasta_vacia'))->assertSee('2,3')->assertSee(__('cosechas.ajustes.solo_lectura'));
        // El propietario que también es administrador sí
        $dueno = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $this->actingAs($dueno)->put(route('admin.ajustes-pesaje.update'), ['peso_canasta_kg' => 2, 'peso_canastilla_kg' => 1.5])->assertSessionHasNoErrors();
    }

    public function test_solo_el_administrador_registra_cosechas_y_el_propietario_las_ve(): void
    {
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);

        $this->cosechar([], $this->propietario)->assertForbidden();
        $this->cosechar([], $trabajador)->assertForbidden();
        $this->actingAs($this->propietario)->get(route('admin.cosechas.create'))->assertForbidden();
        $this->assertSame(0, Cosecha::count());

        $this->cosechar()->assertSessionHasNoErrors();
        $cosecha = Cosecha::sole();
        $this->actingAs($this->propietario)->get(route('admin.cosechas.show', $cosecha))->assertOk()
            ->assertSee('Restaurante El Río')->assertSee('619,5')
            ->assertSee('11 canastas × 2 kg + 9 canastillas × 1,5 kg = 35,5 kg de descuento; 655 kg − 35,5 kg = 619,5 kg enteros')
            ->assertDontSee(__('cosechas.registrar'));
        $this->actingAs($trabajador)->get(route('admin.cosechas.show', $cosecha))->assertForbidden();
    }

    // --- Datos existentes ---

    public function test_la_migracion_deja_intactos_los_pesajes_ya_registrados(): void
    {
        $migracion = require database_path('migrations/2026_10_23_000001_pesadas_por_canastas_en_cosechas.php');
        $migracion->down();

        // Una cosecha y un pesaje con el formato anterior (una canasta, tara común)
        $cosechaId = DB::table('cosechas')->insertGetId(['tenant_id' => $this->finca->id, 'siembra_id' => $this->siembra->id, 'lago_id' => $this->lago->id,
            'user_id' => $this->administrador->id, 'fecha' => '2026-10-10', 'peces' => 50, 'kg' => 23.3, 'peso_promedio_g' => 466, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pesajes_cosecha')->insert(['tenant_id' => $this->finca->id, 'cosecha_id' => $cosechaId, 'numero' => 1,
            'bruto_kg' => 25.4, 'tara_kg' => 2.1, 'neto_kg' => 23.3, 'created_at' => now(), 'updated_at' => now()]);

        $migracion->up();

        $pesaje = PesajeCosecha::sole();
        $this->assertSame(['canasta', 1, 2.1, 25.4, 2.1, 23.3], [$pesaje->tipo, $pesaje->canastas, $pesaje->tara_unitaria_kg, $pesaje->bruto_kg, $pesaje->tara_kg, $pesaje->neto_kg]);
        $cosecha = Cosecha::sole();
        $this->assertNull($cosecha->destino);
        $this->assertSame(23.3, $cosecha->kg);
        // Se muestra con "—" donde falta el destino
        $this->actingAs($this->propietario)->get(route('admin.cosechas.show', $cosecha))->assertOk()->assertSee('—');
    }

    public function test_los_kg_disponibles_para_vender_usan_el_total_entero(): void
    {
        $this->cosechar()->assertSessionHasNoErrors();

        $this->assertSame(619.5, Cosecha::sole()->kgDisponibles());
    }

    public function test_la_bitacora_registra_la_cosecha_con_el_destino(): void
    {
        $this->cosechar()->assertSessionHasNoErrors();

        $registro = Bitacora::where('accion', 'cosecha')->sole();
        $this->assertSame('Restaurante El Río', $registro->detalle['destino']);
        $this->assertStringContainsString('619,5 kg enteros, destino: Restaurante El Río', $registro->textoDetalle());
    }

    // --- Historial y exportaciones ---

    public function test_el_historial_y_los_reportes_muestran_destino_pesadas_y_totales(): void
    {
        $this->cosechar()->assertSessionHasNoErrors();
        $cosecha = Cosecha::sole();

        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertOk()
            ->assertSee('1.200 peces, 619,50 kg enteros')->assertSee('en 3 pesadas · destino: Restaurante El Río')
            ->assertSee(route('admin.cosechas.show', $cosecha));

        $this->actingAs($this->administrador)->get(route('admin.reportes.mensual', ['mes' => '2026-10']))->assertOk()
            ->assertSee('destino: Restaurante El Río')->assertSee('655 kg − 35,5 kg = 619,5 kg enteros');

        $libro = ['lago_id' => $this->lago->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-20'];
        $this->actingAs($this->administrador)->get(route('admin.reportes.libro-campo', $libro))->assertOk()
            ->assertSee(__('cosechas.peso_entero'))->assertSee('236,5')->assertSee('Restaurante El Río');

        // Excel: hoja de cosechas con encabezado y totales, y hoja de pesadas con su fila de totales
        $hojas = $this->leerExcel($this->actingAs($this->administrador)->get(route('admin.reportes.mensual.excel', ['mes' => '2026-10']))->assertOk());
        $this->assertContains(['Fecha', 'Lago', 'Especie', 'Destino del pedido', 'Despachó', 'Peces', 'Pesadas', 'Total pesado (kg)', 'Total de descuento (kg)', 'Kg enteros'], $hojas['Cosechas']);
        $this->assertContains(['20/10/2026', 'L01', 'Mojarra roja', 'Restaurante El Río', 'Ana Admin', 1200, 3, 655, 35.5, 619.5], $hojas['Cosechas']);
        $this->assertContains(['20/10/2026', 'L01', 'Restaurante El Río', '3', 'Canastilla', 9, 1.5, 250, 13.5, 236.5, ''], $hojas['Pesadas']);
        $total = collect($hojas['Pesadas'])->firstWhere(3, 'Total');
        $this->assertSame([20, 655, 35.5, 619.5], [$total[5], $total[7], $total[8], $total[9]]);
        $this->assertStringContainsString('= 619,5 kg enteros', $total[10]);

        $libroExcel = $this->leerExcel($this->actingAs($this->administrador)->get(route('admin.reportes.libro-campo.excel', $libro))->assertOk());
        $this->assertCount(5, $libroExcel['Pesadas']);   // encabezado, 3 pesadas y el total

        $pdf = $this->actingAs($this->administrador)->get(route('admin.reportes.mensual.pdf', ['mes' => '2026-10']))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
    }

    public function test_ninguna_pantalla_de_pesaje_usa_arrume_tara_bruto_ni_neto(): void
    {
        $this->cosechar()->assertSessionHasNoErrors();
        $cosecha = Cosecha::sole();
        $libro = ['lago_id' => $this->lago->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-20'];

        foreach ([
            route('admin.cosechas.create'), route('admin.cosechas.show', $cosecha), route('admin.lagos.show', $this->lago),
            route('admin.ajustes-pesaje.edit'), route('admin.reportes.mensual', ['mes' => '2026-10']), route('admin.reportes.libro-campo', $libro),
        ] as $url) {
            $html = $this->actingAs($this->administrador)->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression('/\b(arrume|arrumes|tara|taras|bruto|brutos|neto|netos|netas)\b/iu', strip_tags($html), $url);
        }

        $html = $this->actingAs($this->propietario)->get(route('admin.ajustes.edit'))->getContent();
        $this->assertDoesNotMatchRegularExpression('/\b(arrume|tara|bruto|neto)\b/iu', strip_tags($html));
        // Los textos de la cosecha tampoco las usan
        $this->assertDoesNotMatchRegularExpression('/\b(arrume|tara|bruto|neto)\b/iu', json_encode([__('cosechas'), __('js'), __('mensajes.cosecha')], JSON_UNESCAPED_UNICODE));
    }

    public function test_aislamiento_la_cosecha_de_otra_finca_no_se_ve_ni_se_lista(): void
    {
        $this->cosechar()->assertSessionHasNoErrors();
        $cosecha = Cosecha::sole();

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $adminOtra = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::ADMINISTRADOR]);

        $this->actingAs($adminOtra)->get(route('admin.cosechas.show', $cosecha))->assertNotFound();
        $this->actingAs($adminOtra)->get(route('admin.cosechas.create'))->assertOk()
            ->assertDontSee('Restaurante El Río')->assertSee(__('cosechas.sin_lagos'));
    }

    private function leerExcel(TestResponse $respuesta): array
    {
        $lector = new Reader;
        $lector->open($respuesta->baseResponse->getFile()->getPathname());
        $hojas = [];
        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $hojas[$hoja->getName()][] = $fila->toArray();
            }
        }
        $lector->close();

        return $hojas;
    }
}
