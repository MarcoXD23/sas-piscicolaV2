<?php

namespace Tests\Feature;

use App\Models\Abono;
use App\Models\Cosecha;
use App\Models\HoraExtra;
use App\Models\Lago;
use App\Models\Liquidacion;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Support\CalculadoraNomina;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NominaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();
        // Miércoles 14 de octubre de 2026, 10:00. La semana va del lunes 12 al domingo 18.
        $this->travelTo(Carbon::parse('2026-10-14 10:00', 'America/Bogota'));

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->trabajador = tap(User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Trabajador Uno']))->asignarRoles([Rol::TRABAJADOR]);
        $this->trabajador->forceFill(['valor_hora' => 7000, 'valor_hora_extra' => 9000])->save();

        foreach (['2026-10-11', '2026-10-12', '2026-10-13', '2026-10-14'] as $dia) {
            $this->turno("{$dia} 06:00", "{$dia} 14:00");
        }
        $this->horas('2026-10-13', 2.5, 'aprobado');
        $this->horas('2026-10-13', 1, 'pendiente');
        $this->horas('2026-10-12', 3, 'rechazado');
        $this->horas('2026-10-09', 4, 'aprobado'); // semana anterior
    }

    private function turno(string $inicio, string $fin): Turno
    {
        return Turno::create(['user_id' => $this->trabajador->id, 'rol' => Rol::TRABAJADOR, 'inicia_at' => $inicio, 'termina_at' => $fin]);
    }

    private function horas(string $fecha, float $horas, string $estado): void
    {
        HoraExtra::create(['user_id' => $this->trabajador->id, 'fecha' => $fecha, 'horas' => $horas, 'motivo' => 'X', 'estado' => $estado]);
    }

    private function credito(int $saldo, string $fecha): Venta
    {
        $lago = Lago::firstOrCreate(['nombre' => 'L01'], ['tipo' => 'tierra']);
        $siembra = Siembra::firstOrCreate(['lago_id' => $lago->id], ['especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-05-01',
            'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 100, 'peso_actual_g' => 400, 'biomasa_kg' => 40]);
        $cosecha = Cosecha::create(['siembra_id' => $siembra->id, 'lago_id' => $lago->id, 'user_id' => $this->propietario->id,
            'fecha' => '2026-10-01', 'peces' => 50, 'kg' => 20, 'peso_promedio_g' => 400]);

        return Venta::create(['cosecha_id' => $cosecha->id, 'user_id' => $this->propietario->id, 'comprador_id' => $this->trabajador->id,
            'cliente' => $this->trabajador->name, 'fecha' => $fecha, 'kg' => 5, 'precio_kg' => $saldo / 5, 'total' => $saldo,
            'forma_pago' => 'credito', 'saldo' => $saldo]);
    }

    private function liquidar(array $datos = [])
    {
        return $this->actingAs($this->propietario)->post(route('admin.nomina.store'), $datos + [
            'user_id' => $this->trabajador->id, 'semana' => '2026-10-12',
        ]);
    }

    public function test_paga_turnos_terminados_y_horas_extra_aprobadas_de_la_semana(): void
    {
        $calculo = CalculadoraNomina::calcular($this->trabajador, CalculadoraNomina::lunes('2026-10-14'));

        // Lunes y martes (16 h). El del miércoles aún no termina; el del domingo 11 es de la semana anterior.
        $this->assertSame(16.0, $calculo['horas_turnos']);
        $this->assertSame(1, $calculo['turnos_sin_terminar']);
        $this->assertSame(2.5, $calculo['horas_extra']);
        $this->assertSame(112000, $calculo['valor_turnos']);   // 16 × 7.000
        $this->assertSame(22500, $calculo['valor_horas_extra']); // 2,5 × 9.000
        $this->assertSame(134500, $calculo['bruto']);

        $this->liquidar()->assertSessionHasNoErrors();

        $liquidacion = Liquidacion::sole();
        $this->assertSame('2026-10-12', $liquidacion->semana_inicio->toDateString());
        $this->assertSame('2026-10-18', $liquidacion->semana_fin->toDateString());
        $this->assertSame(134500, $liquidacion->total_pagar);
        $this->assertSame(7000, $liquidacion->valor_hora);
    }

    public function test_los_turnos_en_dias_de_permiso_aprobado_no_se_pagan(): void
    {
        Permiso::create(['user_id' => $this->trabajador->id, 'fecha_inicio' => '2026-10-13', 'fecha_fin' => '2026-10-13',
            'motivo' => 'Cita', 'estado' => 'aprobado']);

        $calculo = CalculadoraNomina::calcular($this->trabajador, CalculadoraNomina::lunes('2026-10-12'));

        $this->assertSame(8.0, $calculo['horas_turnos']);
        $this->assertCount(1, $calculo['turnos_en_permiso']);
    }

    public function test_el_descuento_de_pescado_salda_creditos_del_mas_antiguo_sin_pasar_del_bruto(): void
    {
        $antiguo = $this->credito(100000, '2026-10-02');
        $reciente = $this->credito(50000, '2026-10-05');

        $this->liquidar(['descontar_pescado' => '1'])->assertSessionHasNoErrors();

        $liquidacion = Liquidacion::sole();
        $this->assertSame(134500, $liquidacion->descuento_pescado);
        $this->assertSame(0, $liquidacion->total_pagar);
        $this->assertSame(0, $antiguo->fresh()->saldo);
        $this->assertSame(15500, $reciente->fresh()->saldo);   // 50.000 − 34.500

        $abonos = Abono::orderBy('id')->get();
        $this->assertSame([100000, 34500], $abonos->pluck('valor')->all());
        $this->assertSame(['nomina', 'nomina'], $abonos->pluck('medio')->all());
        $this->assertTrue($abonos->every(fn ($abono) => $abono->liquidacion_id === $liquidacion->id));
    }

    public function test_sin_marcar_el_descuento_no_se_toca_el_pescado(): void
    {
        $credito = $this->credito(30000, '2026-10-02');

        $this->liquidar()->assertSessionHasNoErrors();

        $this->assertSame(30000, $credito->fresh()->saldo);
        $this->assertSame(134500, Liquidacion::sole()->total_pagar);
    }

    public function test_anular_devuelve_los_saldos_y_no_aplica_a_nominas_pagadas(): void
    {
        $credito = $this->credito(100000, '2026-10-02');
        $this->liquidar(['descontar_pescado' => '1']);
        $liquidacion = Liquidacion::sole();

        $this->actingAs($this->propietario)->delete(route('admin.nomina.destroy', $liquidacion))->assertSessionHasNoErrors();

        $this->assertSame(100000, $credito->fresh()->saldo);
        $this->assertSame(0, Abono::count());
        $this->assertModelMissing($liquidacion);

        // Una pagada no se anula
        $this->liquidar();
        $pagada = Liquidacion::sole();
        $this->actingAs($this->propietario)->post(route('admin.nomina.pagar', $pagada))->assertSessionHasNoErrors();
        $this->actingAs($this->propietario)->delete(route('admin.nomina.destroy', $pagada))->assertSessionHasErrors('liquidacion');
        $this->assertModelExists($pagada);
    }

    public function test_reglas_al_liquidar(): void
    {
        $this->liquidar()->assertSessionHasNoErrors();
        $this->liquidar()->assertInvalid(['user_id' => 'ya está liquidada']);

        $this->liquidar(['semana' => '2026-10-19'])->assertInvalid(['semana']);

        $sinTarifas = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::CELADOR]);
        $this->liquidar(['user_id' => $sinTarifas->id])->assertInvalid(['user_id' => 'configura las tarifas']);

        $administrador = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->liquidar(['user_id' => $administrador->id])->assertInvalid(['user_id']);

        $this->assertSame(1, Liquidacion::count());
    }

    public function test_la_nomina_es_exclusiva_del_propietario_y_el_trabajador_ve_la_suya(): void
    {
        $administrador = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->actingAs($administrador)->get(route('admin.nomina.index'))->assertForbidden();
        $this->actingAs($this->trabajador)->post(route('admin.nomina.store'), [])->assertForbidden();

        $this->actingAs($this->propietario)->get(route('admin.nomina.index', ['semana' => '2026-10-12']))
            ->assertOk()->assertSee('Trabajador Uno')->assertSee('$ 134.500');

        $this->liquidar();
        $this->actingAs($this->trabajador)->get(route('mi-trabajo.index'))
            ->assertSee('Mis pagos de nómina')->assertSee('$ 134.500')->assertSee('Por pagar');
    }

    public function test_tarifas_solo_para_el_personal_de_campo(): void
    {
        $celador = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::CELADOR]);

        $this->actingAs($this->propietario)->put(route('admin.nomina.tarifas', $celador), ['valor_hora' => 8000, 'valor_hora_extra' => 11000])
            ->assertSessionHasNoErrors();
        $this->assertSame(8000, $celador->fresh()->valor_hora);

        $this->actingAs($this->propietario)->put(route('admin.nomina.tarifas', $this->propietario), ['valor_hora' => 1, 'valor_hora_extra' => 1])
            ->assertNotFound();
        $this->actingAs($this->propietario)->put(route('admin.nomina.tarifas', $celador), ['valor_hora' => 7500.5, 'valor_hora_extra' => 1])
            ->assertInvalid(['valor_hora']);
    }
}
