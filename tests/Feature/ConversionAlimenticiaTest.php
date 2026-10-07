<?php

namespace Tests\Feature;

use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Traslado;
use App\Models\User;
use App\Support\ConversionAlimenticia;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ConversionAlimenticiaTest extends TestCase
{
    use RefreshDatabase;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        // Jueves 15 de octubre: la semana actual empieza el lunes 12
        $this->travelTo(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));

        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($finca->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([Rol::PROPIETARIO]);
    }

    private function lago(string $nombre = 'L01'): Lago
    {
        return Lago::create(['nombre' => $nombre, 'tipo' => 'tierra']);
    }

    private function sembrar(Lago $lago, int $peces, float $pesoG, string $fecha = '2026-09-01', string $especie = 'mojarra roja'): Siembra
    {
        return Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie($especie), 'fecha' => $fecha, 'cantidad_alevinos' => $peces,
            'peso_promedio_inicial_g' => $pesoG, 'peces_vivos' => $peces, 'peso_actual_g' => $pesoG, 'biomasa_kg' => $peces * $pesoG / 1000]);
    }

    private function alimentar(Lago $lago, float $kg, string $fecha): void
    {
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'lago_id' => $lago->id, 'fecha' => $fecha,
            'tipo_movimiento' => 'salida', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => $kg]);
    }

    private function muestrear(Siembra $siembra, float $pesoG, string $fecha): void
    {
        Muestreo::create(['siembra_id' => $siembra->id, 'user_id' => $this->propietario->id, 'fecha' => $fecha,
            'peces_pesados' => 10, 'peso_total_g' => $pesoG * 10, 'peso_promedio_g' => $pesoG]);
    }

    private function trasladar(Siembra $origen, Siembra $destino, int $peces, float $pesoG, string $fecha): void
    {
        Traslado::create(['siembra_origen_id' => $origen->id, 'siembra_destino_id' => $destino->id, 'lago_origen_id' => $origen->lago_id,
            'lago_destino_id' => $destino->lago_id, 'user_id' => $this->propietario->id, 'fecha' => $fecha,
            'cantidad' => $peces, 'peso_promedio_g' => $pesoG, 'biomasa_kg' => $peces * $pesoG / 1000]);
    }

    public function test_caso_basico_alimento_entre_biomasa_ganada(): void
    {
        $lago = $this->lago();
        $siembra = $this->sembrar($lago, 1000, 10);          // 10 kg al sembrar
        $this->alimentar($lago, 60, '2026-09-10');
        $this->alimentar($lago, 48, '2026-09-25');
        $this->muestrear($siembra, 100, '2026-10-01');       // 1000 × 100 g = 100 kg → ganó 90 kg
        $this->alimentar($lago, 30, '2026-10-05');           // después del último peso: no cuenta todavía

        $resultado = (new ConversionAlimenticia)->deSiembra($siembra);

        $this->assertSame(108.0, $resultado['alimento_kg']);
        $this->assertSame(90.0, $resultado['ganancia_kg']);
        $this->assertSame(1.2, $resultado['fca']);           // 108 / 90
        $this->assertSame('2026-10-01', $resultado['hasta']);
    }

    public function test_cosechas_y_traslados_cuentan_como_biomasa_ganada_y_la_mortalidad_no(): void
    {
        $lago = $this->lago();
        $otro = $this->lago('L02');
        $siembra = $this->sembrar($lago, 1000, 10);          // 10 kg
        $destino = $this->sembrar($otro, 50, 200, '2026-09-01', 'mojarra roja');
        Mortalidad::create(['siembra_id' => $siembra->id, 'user_id' => $this->propietario->id, 'fecha' => '2026-09-15', 'cantidad' => 100]);
        Cosecha::create(['siembra_id' => $siembra->id, 'lago_id' => $lago->id, 'user_id' => $this->propietario->id, 'fecha' => '2026-10-01',
            'peces' => 200, 'kg' => 40, 'peso_promedio_g' => 200]);
        $this->trasladar($siembra, $destino, 100, 200, '2026-10-01');   // 20 kg salen a otro lago
        $this->alimentar($lago, 204, '2026-09-20');

        // Quedan 1000 − 100 − 200 − 100 = 600 peces × 200 g = 120 kg
        // Ganancia = 120 − 10 + 40 (cosecha) + 20 (traslado) = 170 kg → FCA = 204 / 170 = 1,2
        $resultado = (new ConversionAlimenticia)->deSiembra($siembra);

        $this->assertSame(170.0, $resultado['ganancia_kg']);
        $this->assertSame(1.2, $resultado['fca']);
    }

    public function test_los_peces_recibidos_se_restan_y_el_traslado_que_crea_la_siembra_no(): void
    {
        $origen = $this->sembrar($this->lago('L00'), 2000, 50);

        // Siembra existente que recibe 100 peces de 50 g (5 kg)
        $lago = $this->lago();
        $recibe = $this->sembrar($lago, 500, 10);            // 5 kg
        $this->trasladar($origen, $recibe, 100, 50, '2026-09-20');
        $this->muestrear($recibe, 100, '2026-10-01');        // 600 × 100 g = 60 kg
        $this->alimentar($lago, 75, '2026-09-25');
        // Ganancia = 60 − 5 − 5 (recibidos) = 50 → FCA 1,5
        $this->assertSame(1.5, (new ConversionAlimenticia)->deSiembra($recibe)['fca']);

        // Siembra creada por un traslado: esos peces son sus alevinos iniciales (20 kg)
        $nuevoLago = $this->lago('L03');
        $creada = $this->sembrar($nuevoLago, 100, 200, '2026-10-01');
        $this->trasladar($origen, $creada, 100, 200, '2026-10-01');
        $this->muestrear($creada, 250, '2026-10-11');        // 25 kg → ganó 5 kg
        $this->alimentar($nuevoLago, 6, '2026-10-05');
        $this->assertSame(5.0, (new ConversionAlimenticia)->deSiembra($creada)['ganancia_kg']);
        $this->assertSame(1.2, (new ConversionAlimenticia)->deSiembra($creada)['fca']);
    }

    public function test_en_policultivo_el_alimento_se_reparte_segun_la_biomasa_de_cada_siembra(): void
    {
        $lago = $this->lago();
        $mojarra = $this->sembrar($lago, 1000, 10);
        $cachama = $this->sembrar($lago, 1000, 10, '2026-09-01', 'cachama blanca');
        $this->muestrear($mojarra, 100, '2026-10-01');       // 100 kg ese día
        $this->muestrear($cachama, 300, '2026-10-01');       // 300 kg ese día
        $this->alimentar($lago, 100, '2026-10-01');

        $conversion = new ConversionAlimenticia;
        $this->assertSame(25.0, $conversion->deSiembra($mojarra)['alimento_kg']);
        $this->assertSame(75.0, $conversion->deSiembra($cachama)['alimento_kg']);

        $total = $conversion->porLago()->sole()['total'];
        $this->assertSame(100.0, $total['alimento_kg']);
        $this->assertSame(380.0, $total['ganancia_kg']);     // 90 + 290
        $this->assertSame(0.26, $total['fca']);              // 100 / 380

        $especies = $conversion->porEspecie()->keyBy('especie');
        $this->assertSame(1, $especies['Mojarra roja']['siembras']);
        $this->assertSame(0.28, $especies['Mojarra roja']['fca']);   // 25 / 90
    }

    public function test_tendencia_semanal_con_peso_interpolado_entre_muestreos(): void
    {
        $lago = $this->lago();
        $siembra = $this->sembrar($lago, 1000, 10, '2026-09-14');   // lunes
        $this->muestrear($siembra, 66, '2026-10-12');               // 28 días después: +2 g por día
        $this->alimentar($lago, 21, '2026-10-07');

        $semanas = collect((new ConversionAlimenticia)->semanal($lago, 12))->keyBy(fn ($fila) => $fila['semana']->toDateString());

        $this->assertCount(12, $semanas);
        // Semana del 5 al 11: peso 50 g (domingo 4) → 64 g (domingo 11): 50 kg → 64 kg = 14 kg ganados
        $this->assertSame(21.0, $semanas['2026-10-05']['alimento_kg']);
        $this->assertSame(14.0, $semanas['2026-10-05']['ganancia_kg']);
        $this->assertSame(1.5, $semanas['2026-10-05']['fca']);
        // Primera semana: desde la siembra (10 kg) hasta el domingo 20 (22 g → 22 kg)
        $this->assertSame(12.0, $semanas['2026-09-14']['ganancia_kg']);
        $this->assertSame(0.0, $semanas['2026-09-14']['fca']);   // esa semana no se registró alimento: 0 kg / 12 kg
        // Semana actual: no hay peso hasta el domingo 18
        $this->assertSame(ConversionAlimenticia::SIN_PESO, $semanas['2026-10-12']['motivo']);
        $this->assertNull($semanas['2026-10-12']['fca']);
        // Antes de sembrar: nada que calcular
        $this->assertNull($semanas['2026-07-27']['fca']);
    }

    public function test_division_por_cero_y_casos_sin_datos(): void
    {
        $lago = $this->lago();
        $conversion = new ConversionAlimenticia;

        // Sin muestreos: no hay peso final
        $sinPeso = $this->sembrar($lago, 1000, 10);
        $this->alimentar($lago, 50, '2026-09-10');
        $this->assertNull($conversion->deSiembra($sinPeso)['fca']);
        $this->assertSame(ConversionAlimenticia::SIN_PESO, $conversion->deSiembra($sinPeso)['motivo']);

        // Ganancia cero: mismo peso que al sembrar
        $cero = $this->sembrar($this->lago('L02'), 1000, 10);
        $this->muestrear($cero, 10, '2026-10-01');
        $this->assertSame(0.0, (new ConversionAlimenticia)->deSiembra($cero)['ganancia_kg']);
        $this->assertNull((new ConversionAlimenticia)->deSiembra($cero)['fca']);
        $this->assertSame(ConversionAlimenticia::SIN_GANANCIA, (new ConversionAlimenticia)->deSiembra($cero)['motivo']);

        // Ganancia negativa por mucha mortalidad: 100 × 50 g = 5 kg < 10 kg iniciales
        $negativa = $this->sembrar($this->lago('L03'), 1000, 10);
        Mortalidad::create(['siembra_id' => $negativa->id, 'user_id' => $this->propietario->id, 'fecha' => '2026-09-20', 'cantidad' => 900]);
        $this->muestrear($negativa, 50, '2026-10-01');
        $this->assertSame(-5.0, (new ConversionAlimenticia)->deSiembra($negativa)['ganancia_kg']);
        $this->assertNull((new ConversionAlimenticia)->deSiembra($negativa)['fca']);

        // Sin alimento pero con ganancia: FCA 0
        $sinAlimento = $this->sembrar($this->lago('L04'), 1000, 10);
        $this->muestrear($sinAlimento, 20, '2026-10-01');
        $this->assertSame(0.0, (new ConversionAlimenticia)->deSiembra($sinAlimento)['fca']);
    }

    public function test_sin_siembras_la_pantalla_no_falla(): void
    {
        $this->assertCount(0, (new ConversionAlimenticia)->porLago());
        $this->assertCount(0, (new ConversionAlimenticia)->porEspecie());

        $this->actingAs($this->propietario)->get(route('admin.conversion.index'))->assertOk()->assertSee(__('conversion.sin_siembras'));
    }

    public function test_solo_propietario_y_administrador_ven_la_conversion(): void
    {
        $lago = $this->lago();
        $siembra = $this->sembrar($lago, 1000, 10);
        $this->alimentar($lago, 108, '2026-09-10');
        $this->muestrear($siembra, 100, '2026-10-01');
        $finca = $this->propietario->tenant_id;

        $this->actingAs($this->propietario)->get(route('admin.conversion.index'))->assertOk()
            ->assertSee(__('conversion.titulo'))->assertSee('1,20')->assertSee('108,0 kg');

        foreach ([Rol::ADMINISTRADOR => 200, Rol::TRABAJADOR => 403, Rol::CELADOR => 403] as $rol => $estado) {
            $persona = tap(User::factory()->create(['tenant_id' => $finca]))->asignarRoles([$rol]);
            $this->actingAs($persona)->get(route('admin.conversion.index'))->assertStatus($estado);
        }
    }
}
