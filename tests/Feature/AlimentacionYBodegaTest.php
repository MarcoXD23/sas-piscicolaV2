<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Alertas\EvaluadorAlertas;
use App\Support\CalculadoraRacion;
use App\Support\CoberturaBodega;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlimentacionYBodegaTest extends TestCase
{
    use RefreshDatabase;

    private User $propietario;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 09:00', 'America/Bogota'));

        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
    }

    private function sembrar(string $especie, int $peces, float $pesoG): Siembra
    {
        return Siembra::create(['lago_id' => $this->lago->id, 'especie' => $especie, 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => $peces, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces,
            'peso_actual_g' => $pesoG, 'biomasa_kg' => $peces * $pesoG / 1000]);
    }

    private function salida(string $concentrado, float $kg, string $fecha): void
    {
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'lago_id' => $this->lago->id, 'fecha' => $fecha,
            'tipo_movimiento' => 'salida', 'tipo_concentrado' => $concentrado, 'cantidad_kg' => $kg]);
    }

    public function test_la_racion_se_calcula_con_la_biomasa_y_el_peso_promedio(): void
    {
        // 1.000 mojarras de 50 g = 50 kg de biomasa; a 50 g corresponde 4 % en 4 tomas
        $this->sembrar('mojarra roja', 1000, 50);

        $racion = CalculadoraRacion::paraLago($this->lago);

        $this->assertSame(2.0, $racion['total_kg_dia']);
        $this->assertSame(4, $racion['tomas']);
        $this->assertSame(0.5, $racion['kg_por_toma']);
        $this->assertSame(1.0, $racion['factor']);
        $this->assertSame('levante', $racion['filas'][0]['etapa']);
        $this->assertSame('32 % a 34 %', $racion['filas'][0]['proteina']);
    }

    public function test_en_policultivo_suma_cada_especie_con_su_perfil(): void
    {
        $this->sembrar('mojarra roja', 1000, 50);   // 50 kg × 4 % = 2,0 kg
        $this->sembrar('bocachico', 400, 30);       // 12 kg × 2 % (detritívoro) = 0,24 kg

        $racion = CalculadoraRacion::paraLago($this->lago);

        $this->assertCount(2, $racion['filas']);
        $this->assertSame(2.2, $racion['total_kg_dia']);
        $this->assertSame(4, $racion['tomas']);
    }

    public function test_tablas_de_alimentacion_por_peso_y_perfil(): void
    {
        $this->assertSame([8.0, 5], CalculadoraRacion::porcentajeYTomas('mojarra negra', 5));
        $this->assertSame([6.0, 4], CalculadoraRacion::porcentajeYTomas('mojarra negra', 5.1));
        $this->assertSame([1.2, 2], CalculadoraRacion::porcentajeYTomas('cachama blanca', 900));
        $this->assertSame([2.5, 4], CalculadoraRacion::porcentajeYTomas('trucha arcoiris', 30));
        $this->assertSame('iniciacion', CalculadoraRacion::etapa(10));
        $this->assertSame('engorde', CalculadoraRacion::etapa(150));
    }

    public function test_reduce_la_racion_si_la_medicion_reciente_tiene_alertas(): void
    {
        $this->sembrar('mojarra roja', 1000, 50);
        MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id,
            'medido_at' => now()->subHours(2), 'oxigeno_mg_l' => 2.5]);

        $racion = CalculadoraRacion::paraLago($this->lago->fresh());

        $this->assertSame(0.5, $racion['factor']);
        $this->assertSame(1.0, $racion['total_kg_dia']);
        $this->assertNotEmpty($racion['motivos']);
    }

    public function test_una_medicion_vieja_no_reduce_la_racion(): void
    {
        $this->sembrar('mojarra roja', 1000, 50);
        MedicionAgua::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id,
            'medido_at' => now()->subHours(30), 'oxigeno_mg_l' => 2.5]);

        $this->assertSame(1.0, CalculadoraRacion::paraLago($this->lago->fresh())['factor']);
    }

    public function test_cobertura_de_bodega_con_el_consumo_de_los_ultimos_7_dias(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 30]);
        StockAlimento::create(['tipo_concentrado' => 'Levante', 'cantidad_kg' => 500]);
        StockAlimento::create(['tipo_concentrado' => 'Iniciación', 'cantidad_kg' => 0]);

        $this->salida('Engorde', 40, '2026-10-07');
        $this->salida('Engorde', 30, '2026-10-01'); // dentro de la ventana (7 días contando hoy)
        $this->salida('Engorde', 500, '2026-09-30'); // fuera de la ventana
        $this->salida('Levante', 70, '2026-10-05');

        $cobertura = CoberturaBodega::calcular()->keyBy(fn ($item) => $item['stock']->tipo_concentrado);

        // Engorde: 70 kg / 7 días = 10 kg/día → 30 kg alcanzan 3 días
        $this->assertSame(10.0, $cobertura['Engorde']['consumo_diario_kg']);
        $this->assertSame(3.0, $cobertura['Engorde']['dias']);
        $this->assertSame(CoberturaBodega::BAJO, $cobertura['Engorde']['estado']);
        // Levante: 10 kg/día → 50 días
        $this->assertSame(CoberturaBodega::SUFICIENTE, $cobertura['Levante']['estado']);
        $this->assertSame(CoberturaBodega::AGOTADO, $cobertura['Iniciación']['estado']);

        $this->assertSame(['Engorde', 'Iniciación'], CoberturaBodega::alertas()->map(fn ($a) => $a['stock']->tipo_concentrado)->sort()->values()->all());
    }

    public function test_sin_consumo_reciente_no_hay_alerta_de_stock_bajo(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 5]);

        $item = CoberturaBodega::calcular()->sole();
        $this->assertNull($item['dias']);
        $this->assertSame(CoberturaBodega::SUFICIENTE, $item['estado']);
    }

    public function test_el_inicio_del_propietario_avisa_el_stock_bajo_y_el_del_administrador_no(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 0]);
        $administrador = tap(User::factory()->create(['tenant_id' => $this->propietario->tenant_id]))->asignarRoles([Rol::ADMINISTRADOR]);

        EvaluadorAlertas::paraFinca($this->propietario->tenant_id);
        $aviso = __('alertas.mensajes.stock_agotado', ['concentrado' => 'Engorde']);

        $this->actingAs($this->propietario)->get(route('inicio'))->assertSee($aviso);
        // La bodega es del propietario: el administrador no ve esa alerta
        $this->actingAs($administrador)->get(route('inicio'))->assertDontSee($aviso);
    }

    public function test_la_bodega_muestra_y_filtra_los_movimientos(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 100]);
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'fecha' => '2026-10-06', 'tipo_movimiento' => 'entrada',
            'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 120, 'proveedor' => 'Proveedor A', 'lote' => 'L-77', 'bultos' => 3, 'peso_bulto_kg' => 40]);
        $this->salida('Engorde', 20, '2026-10-07');

        $this->actingAs($this->propietario)->get(route('admin.bodega.index'))
            ->assertOk()->assertSee('Proveedor A · lote L-77')->assertSee('Lago L01');

        $this->actingAs($this->propietario)->get(route('admin.bodega.index', ['tipo' => 'salida']))
            ->assertOk()->assertDontSee('Proveedor A')->assertSee('Lago L01');
    }

    public function test_la_ventana_de_alimentar_sugiere_la_cantidad(): void
    {
        $this->sembrar('mojarra roja', 1000, 50);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 100]);

        $this->actingAs($this->propietario)->get(route('admin.lagos.index'))
            ->assertSee('Sugerido: 0,5 kg por toma, 4 tomas al día (2,0 kg/día).');
        $this->actingAs($this->propietario)->get(route('admin.lagos.show', $this->lago))
            ->assertSee('Ración sugerida');
    }
}
