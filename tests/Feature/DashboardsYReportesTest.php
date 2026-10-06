<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tratamiento;
use App\Models\User;
use App\Support\Indicadores;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardsYReportesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 09:00'));

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->propietario = $this->persona(Rol::PROPIETARIO);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie' => 'cachama blanca', 'fecha' => '2026-08-01',
            'proveedor_alevinos' => 'Estación piscícola X', 'lote_alevinos' => 'A-17',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 900, 'peso_actual_g' => 200, 'biomasa_kg' => 180]);
    }

    private function persona(string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([$rol]);
    }

    private function mortalidad(string $fecha, int $cantidad): void
    {
        Mortalidad::create(['siembra_id' => $this->siembra->id, 'user_id' => $this->propietario->id, 'fecha' => $fecha, 'cantidad' => $cantidad]);
    }

    private function salida(string $fecha, float $kg): void
    {
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'lago_id' => $this->lago->id, 'fecha' => $fecha,
            'tipo_movimiento' => 'salida', 'tipo_concentrado' => 'Engorde 24 %', 'cantidad_kg' => $kg]);
    }

    // --- Indicadores ---

    public function test_mortalidad_reciente_y_su_porcentaje_sobre_la_poblacion(): void
    {
        $this->mortalidad('2026-10-15', 60);
        $this->mortalidad('2026-09-25', 40);
        $this->mortalidad('2026-09-01', 500); // fuera de los 30 días

        // 100 muertos / (900 vivos + 100 muertos) = 10 %
        $this->assertSame(['muertos' => 100, 'porcentaje' => 10.0], Indicadores::mortalidadReciente());
    }

    public function test_las_series_diarias_tienen_30_dias_con_ceros_en_los_dias_sin_registros(): void
    {
        $this->mortalidad('2026-10-20', 5);
        $this->mortalidad('2026-10-20', 3);
        $this->salida('2026-10-19', 12.5);

        $mortalidad = Indicadores::mortalidadDiaria();
        $this->assertCount(30, $mortalidad);
        $this->assertSame('2026-09-21', $mortalidad->first()['fecha']->toDateString());
        $this->assertSame(8.0, $mortalidad->last()['valor']);
        $this->assertSame(0.0, $mortalidad[0]['valor']);

        $this->assertSame(12.5, Indicadores::alimentoDiario()->firstWhere(fn ($d) => $d['fecha']->toDateString() === '2026-10-19')['valor']);
    }

    public function test_biomasa_por_lago_de_mayor_a_menor_sin_lagos_vacios(): void
    {
        $grande = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $grande->id, 'especie' => 'mojarra roja', 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 2000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 2000, 'peso_actual_g' => 150, 'biomasa_kg' => 300]);
        Lago::create(['nombre' => 'L03', 'tipo' => 'tierra']);

        $this->assertSame([['etiqueta' => 'L02', 'valor' => 300.0], ['etiqueta' => 'L01', 'valor' => 180.0]], Indicadores::biomasaPorLago()->all());
    }

    // --- Dashboards por rol ---

    public function test_cada_rol_ve_su_dashboard(): void
    {
        $this->actingAs($this->propietario)->get(route('inicio'))
            ->assertSee('Panel ejecutivo')->assertSee('Biomasa por lago')->assertSee('Mortalidad diaria')->assertSee('Alimento suministrado');

        $this->actingAs($this->persona(Rol::ADMINISTRADOR))->get(route('inicio'))
            ->assertSee('Panel técnico')->assertSee('Mortalidad diaria')->assertDontSee('Alimento suministrado');

        $this->actingAs($this->persona(Rol::TRABAJADOR))->get(route('inicio'))
            ->assertSee('Ración sugerida para hoy')->assertDontSee('Panel ejecutivo')->assertDontSee('Panel técnico');

        $this->actingAs($this->persona(Rol::CELADOR))->get(route('inicio'))
            ->assertDontSee('Panel')->assertDontSee('Ración sugerida para hoy');
    }

    public function test_el_celador_y_el_administrador_ven_los_lagos_con_agua_fuera_de_rango(): void
    {
        MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id, 'medido_at' => now()->subHour(), 'oxigeno_mg_l' => 2]);

        $this->actingAs($this->persona(Rol::CELADOR))->get(route('inicio'))->assertSee('Calidad del agua fuera de rango')->assertSee('Oxígeno de 2 mg/L');
        $this->actingAs($this->persona(Rol::ADMINISTRADOR))->get(route('inicio'))->assertSee('Calidad del agua fuera de rango');
    }

    // --- Reportes ---

    public function test_los_reportes_son_para_propietario_y_administrador(): void
    {
        $this->actingAs($this->persona(Rol::ADMINISTRADOR))->get(route('admin.reportes.index'))->assertOk();
        $this->actingAs($this->persona(Rol::TRABAJADOR))->get(route('admin.reportes.mensual'))->assertForbidden();
        $this->actingAs($this->persona(Rol::CELADOR))->get(route('admin.reportes.libro-campo', ['lago_id' => $this->lago->id]))->assertForbidden();
    }

    public function test_el_informe_mensual_suma_solo_el_mes_y_las_entradas_son_del_propietario(): void
    {
        $this->mortalidad('2026-10-03', 7);
        $this->mortalidad('2026-09-30', 50);
        $this->salida('2026-10-10', 20);
        $this->salida('2026-09-29', 99);
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'fecha' => '2026-10-02', 'tipo_movimiento' => 'entrada',
            'tipo_concentrado' => 'Engorde 24 %', 'cantidad_kg' => 400, 'proveedor' => 'Proveedor A', 'lote' => 'Z-9', 'bultos' => 10, 'peso_bulto_kg' => 40]);

        $respuesta = $this->actingAs($this->propietario)->get(route('admin.reportes.mensual', ['mes' => '2026-10']))->assertOk();
        $fila = $respuesta->viewData('lagos')->first();
        $this->assertSame(7, $fila['mortalidad']);
        $this->assertSame(20.0, $fila['alimento_kg']);
        $respuesta->assertSee('Octubre de 2026')->assertSee('Proveedor A, lote Z-9');

        $this->actingAs($this->persona(Rol::ADMINISTRADOR))->get(route('admin.reportes.mensual', ['mes' => '2026-10']))
            ->assertOk()->assertDontSee('Entradas a bodega')->assertDontSee('Proveedor A');
    }

    public function test_el_libro_de_campo_es_cronologico_con_trazabilidad_y_solo_del_periodo(): void
    {
        $this->mortalidad('2026-10-05', 4);
        $this->salida('2026-10-01', 15);
        $this->mortalidad('2026-06-01', 9); // fuera del período
        Tratamiento::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id, 'fecha_inicio' => '2026-10-08',
            'fecha_fin' => '2026-10-10', 'motivo' => 'Parásitos', 'producto' => 'Producto X', 'registro_ica' => 'ICA-123',
            'dosis' => '1 g/m³', 'via' => 'baño', 'dias_retiro' => 5]);

        $respuesta = $this->actingAs($this->propietario)
            ->get(route('admin.reportes.libro-campo', ['lago_id' => $this->lago->id, 'desde' => '2026-07-01', 'hasta' => '2026-10-20']))
            ->assertOk()
            ->assertSee('Estación piscícola X')
            ->assertSee('A-17')
            ->assertSee('registro ICA ICA-123')
            ->assertDontSee('9 peces');

        $fechas = $respuesta->viewData('registros')->map(fn ($r) => $r['fecha']->toDateString())->all();
        $this->assertSame(['2026-08-01', '2026-10-01', '2026-10-05', '2026-10-08'], $fechas);
    }

    public function test_el_libro_de_campo_no_muestra_lagos_de_otra_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajeno = Lago::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'nombre' => 'X1', 'tipo' => 'tierra']);

        $this->actingAs($this->propietario)->get(route('admin.reportes.libro-campo', ['lago_id' => $ajeno->id]))->assertNotFound();
    }

    public function test_el_traslado_conserva_proveedor_y_lote_de_los_alevinos(): void
    {
        $destino = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);

        $this->actingAs($this->propietario)->post(route('admin.traslados.store', $this->siembra), [
            'lago_destino_id' => $destino->id, 'fecha' => '2026-10-20', 'cantidad' => 100, 'peso_promedio_g' => 200,
        ])->assertSessionHasNoErrors();

        $nueva = $destino->siembrasActivas()->sole();
        $this->assertSame('Estación piscícola X', $nueva->proveedor_alevinos);
        $this->assertSame('A-17', $nueva->lote_alevinos);
    }
}
