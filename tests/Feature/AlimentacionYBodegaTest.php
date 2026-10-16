<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\TablaRacion;
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
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $this->propietario = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
    }

    private function sembrar(string $especie, int $peces, float $pesoG): Siembra
    {
        return Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie($especie), 'fecha' => now()->subDays(30),
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
        // La tabla de ración por defecto de la finca (TablaRacion): la de antes, con los datos de la finca piloto en el perfil cálido
        $fila = function (string $especie, float $peso): array {
            $this->lago = Lago::create(['nombre' => 'T'.Lago::count(), 'tipo' => 'tierra']);
            $this->sembrar($especie, 100, $peso);

            return CalculadoraRacion::paraLago($this->lago)['filas'][0];
        };

        $this->assertSame([8.0, 5, '45 %', 'Harina'], array_values(array_intersect_key($fila('mojarra roja', 5), array_flip(['porcentaje', 'tomas', 'proteina', 'pellet']))));
        $this->assertSame([4.1, 4, '36 %'], [$fila('mojarra roja', 5.1)['porcentaje'], $fila('mojarra roja', 5.1)['tomas'], $fila('mojarra roja', 5.1)['proteina']]);
        $this->assertSame([1.5, '24 %'], [$fila('cachama blanca', 280)['porcentaje'], $fila('cachama blanca', 280)['proteina']]);
        $this->assertSame([1.3, 2], [$fila('mojarra negra', 450)['porcentaje'], $fila('mojarra negra', 450)['tomas']]);
        $this->assertSame([1.2, 2], [$fila('mojarra roja', 900)['porcentaje'], $fila('mojarra roja', 900)['tomas']]);
        $this->assertSame([2.5, 4], [$fila('trucha arcoiris', 30)['porcentaje'], $fila('trucha arcoiris', 30)['tomas']]);
        // Sin proteína en el tramo: la de referencia de la especie para su etapa; sin pellet: vacío
        $this->assertSame(['32 % a 34 %', null], [$fila('mojarra roja', 50)['proteina'], $fila('mojarra roja', 50)['pellet']]);
        $this->assertSame('iniciacion', CalculadoraRacion::etapa(10));
        $this->assertSame('engorde', CalculadoraRacion::etapa(150));
    }

    public function test_una_especie_sin_tabla_de_racion_no_tiene_sugerencia(): void
    {
        // Ya no hay tablas fijas en el código: sin tramos en la tabla de la finca, la ración es manual
        $this->sembrar('mojarra roja', 1000, 50);
        TablaRacion::query()->where('especie_id', $this->especie('mojarra roja'))->delete();

        $racion = CalculadoraRacion::paraLago($this->lago->fresh());
        $this->assertSame([], $racion['filas']);
        $this->assertSame(['Mojarra roja'], $racion['sin_sugerencia']);
        $this->assertSame(0.0, $racion['total_kg_dia']);
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

    public function test_el_inicio_del_propietario_y_del_administrador_avisan_el_stock_agotado(): void
    {
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 0]);
        $administrador = tap(User::factory()->create(['tenant_id' => $this->propietario->tenant_id]))->asignarRoles([Rol::ADMINISTRADOR]);

        EvaluadorAlertas::paraFinca($this->propietario->tenant_id);
        $aviso = __('alertas.mensajes.stock_agotado', ['concentrado' => 'Engorde']);

        $this->actingAs($this->propietario)->get(route('inicio'))->assertSee($aviso);
        // El administrador recibe el concentrado: también ve la alerta de stock
        $this->actingAs($administrador)->get(route('inicio'))->assertSee($aviso);
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
