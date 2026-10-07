<?php

namespace Tests\Feature;

use App\Models\Abono;
use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tratamiento;
use App\Models\User;
use App\Models\Venta;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CosechasYVentasTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 08:00', 'America/Bogota'));

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $this->propietario = tap($this->persona(Rol::PROPIETARIO))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie' => 'mojarra roja', 'fecha' => '2026-05-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 400, 'biomasa_kg' => 400]);
    }

    private function persona(string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([$rol]);
    }

    private function cosechar(array $datos = [], ?User $quien = null)
    {
        return $this->actingAs($quien ?? $this->propietario)->post(route('admin.cosechas.store'),
            $datos + ['siembra_id' => $this->siembra->id, 'destino' => 'Restaurante El Río', 'fecha' => '2026-10-20', 'peces' => 300, 'kg' => 126]);
    }

    private function cosecha(float $kg = 100): Cosecha
    {
        return Cosecha::create(['siembra_id' => $this->siembra->id, 'lago_id' => $this->lago->id, 'user_id' => $this->propietario->id,
            'fecha' => '2026-10-18', 'peces' => 250, 'kg' => $kg, 'peso_promedio_g' => $kg * 1000 / 250]);
    }

    private function vender(Cosecha $cosecha, array $datos = [])
    {
        return $this->actingAs($this->propietario)->post(route('admin.ventas.store'), $datos + [
            'cosecha_id' => $cosecha->id, 'cliente' => 'Restaurante El Río', 'fecha' => '2026-10-20',
            'kg' => 12.5, 'precio_kg' => 9500, 'forma_pago' => 'contado',
        ]);
    }

    // --- Cosecha ---

    public function test_cosecha_parcial_descuenta_peces_y_recalcula_biomasa(): void
    {
        $this->cosechar()->assertSessionHasNoErrors();

        $cosecha = Cosecha::sole();
        $this->assertSame(126.0, $cosecha->kg);
        $this->assertSame(420.0, $cosecha->peso_promedio_g);
        $this->assertSame($this->lago->id, $cosecha->lago_id);

        $siembra = $this->siembra->fresh();
        $this->assertSame(700, $siembra->peces_vivos);
        $this->assertEquals(280, (float) $siembra->biomasa_kg);
        $this->assertNull($siembra->finalizada_at);
    }

    public function test_cosecha_total_finaliza_la_siembra(): void
    {
        $this->cosechar(['peces' => 1000, 'kg' => 410])->assertSessionHasNoErrors();

        $this->assertNotNull($this->siembra->fresh()->finalizada_at);
        $this->assertSame(0, $this->lago->siembrasActivas()->count());
    }

    public function test_no_cosecha_mas_peces_de_los_vivos_ni_siembras_finalizadas(): void
    {
        $this->cosechar(['peces' => 1001])->assertInvalid(['peces']);

        // Una siembra finalizada ya no es un lago con siembra activa para cosechar
        $this->siembra->update(['finalizada_at' => now()]);
        $this->cosechar()->assertInvalid(['siembra_id' => __('mensajes.cosecha.lago_sin_siembra')]);

        $this->assertSame(0, Cosecha::count());
    }

    public function test_el_retiro_sanitario_bloquea_la_cosecha_hasta_que_termina(): void
    {
        Tratamiento::create(['lago_id' => $this->lago->id, 'user_id' => $this->propietario->id, 'fecha_inicio' => '2026-10-15',
            'fecha_fin' => '2026-10-18', 'motivo' => 'Parásitos', 'producto' => 'Producto X', 'dosis' => 'D', 'via' => 'baño', 'dias_retiro' => 5]);

        // Retiro hasta el 23/10
        $this->cosechar()->assertInvalid(['fecha' => 'retiro sanitario hasta el 23/10/2026']);
        $this->assertSame(1000, $this->siembra->fresh()->peces_vivos);

        $this->actingAs($this->propietario)->get(route('admin.lagos.show', $this->lago))->assertSee('Lago en retiro sanitario', false);

        $this->travelTo(Carbon::parse('2026-10-24 08:00', 'America/Bogota'));
        $this->cosechar(['fecha' => '2026-10-24'])->assertSessionHasNoErrors();
    }

    public function test_el_administrador_cosecha_pero_el_trabajador_no(): void
    {
        $this->cosechar([], $this->persona(Rol::ADMINISTRADOR))->assertSessionHasNoErrors();

        $trabajador = $this->persona(Rol::TRABAJADOR);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);
        $this->cosechar([], $trabajador)->assertForbidden();
    }

    public function test_la_cosecha_queda_en_el_historial_del_lago(): void
    {
        $this->cosechar();

        $this->actingAs($this->propietario)->get(route('admin.lagos.show', $this->lago))
            ->assertSee('Cosecha · Mojarra roja')->assertSee('300 peces, 126,00 kg limpios')->assertSee('destino: Restaurante El Río');
    }

    // --- Ventas ---

    public function test_venta_de_contado_y_a_credito(): void
    {
        $cosecha = $this->cosecha();

        $this->vender($cosecha)->assertSessionHasNoErrors();
        $this->vender($cosecha, ['forma_pago' => 'credito', 'kg' => 10, 'precio_kg' => 9000])->assertSessionHasNoErrors();

        [$contado, $credito] = Venta::orderBy('id')->get();
        // 12,5 kg × $ 9.500 = $ 118.750
        $this->assertSame(118750, $contado->total);
        $this->assertSame(0, $contado->saldo);
        $this->assertSame(90000, $credito->total);
        $this->assertSame(90000, $credito->saldo);
        $this->assertSame(77.5, $cosecha->fresh()->kgDisponibles());
    }

    public function test_no_se_vende_mas_pescado_del_cosechado(): void
    {
        $cosecha = $this->cosecha(20);

        $this->vender($cosecha, ['kg' => 15])->assertSessionHasNoErrors();
        $this->vender($cosecha, ['kg' => 5.01])->assertInvalid(['kg' => '5,00 kg']);
        $this->vender($cosecha, ['kg' => 5])->assertSessionHasNoErrors();

        $this->assertSame(0.0, $cosecha->fresh()->kgDisponibles());
    }

    public function test_la_venta_no_es_anterior_a_la_cosecha(): void
    {
        $this->vender($this->cosecha(), ['fecha' => '2026-10-17'])->assertInvalid(['fecha']);
    }

    public function test_venta_a_un_empleado_y_no_a_personas_de_otra_finca(): void
    {
        $cosecha = $this->cosecha();
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajeno = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::TRABAJADOR]);

        $this->vender($cosecha, ['cliente' => null, 'comprador_id' => $ajeno->id])->assertInvalid(['comprador_id']);
        $this->vender($cosecha, ['cliente' => null])->assertInvalid(['cliente']);
        $this->vender($cosecha, ['cliente' => null, 'comprador_id' => $trabajador->id, 'forma_pago' => 'credito', 'kg' => 4, 'precio_kg' => 8000])
            ->assertSessionHasNoErrors();

        $venta = Venta::sole();
        $this->assertSame($trabajador->name, $venta->cliente);
        $this->assertSame($trabajador->id, $venta->comprador_id);

        $this->actingAs($trabajador)->get(route('mi-trabajo.index'))->assertSee('Mi saldo de pescado')->assertSee('$ 32.000');
    }

    public function test_abonos_reducen_el_saldo_sin_pasarse(): void
    {
        $this->vender($this->cosecha(), ['forma_pago' => 'credito', 'kg' => 10, 'precio_kg' => 9000]);
        $venta = Venta::sole();
        $abonar = fn (int $valor) => $this->actingAs($this->propietario)->post(route('admin.ventas.abonar', $venta),
            ['fecha' => '2026-10-20', 'valor' => $valor, 'medio' => 'efectivo']);

        $abonar(100000)->assertInvalid(['valor']);
        $abonar(40000)->assertSessionHasNoErrors();
        $this->assertSame(50000, $venta->fresh()->saldo);
        $abonar(50000)->assertSessionHasNoErrors();
        $this->assertSame(0, $venta->fresh()->saldo);
        $abonar(1)->assertInvalid(['valor']);

        $this->assertSame(90000, (int) Abono::sum('valor'));
    }

    public function test_el_administrador_registra_ventas_y_el_propietario_las_consulta_en_su_panel(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $soloDueno = $this->persona(Rol::PROPIETARIO);
        $this->actingAs($administrador)->get(route('admin.ventas.index'))->assertOk()->assertSee(__('ventas.nueva_venta'));
        // El propietario sin rol de administrador solo consulta: sin botón y con 403 al registrar
        $this->actingAs($soloDueno)->get(route('admin.ventas.index'))->assertOk()->assertDontSee(__('ventas.nueva_venta'));
        $this->actingAs($soloDueno)->post(route('admin.ventas.store'), [])->assertForbidden();
        $this->actingAs($this->persona(Rol::TRABAJADOR))->get(route('admin.ventas.index'))->assertForbidden();

        $this->vender($this->cosecha(), ['forma_pago' => 'credito']);

        $this->actingAs($this->propietario)->get(route('inicio'))
            ->assertSee('Ventas del mes')->assertSee('$ 118.750')->assertSee('Por cobrar');
        $this->actingAs($administrador)->get(route('inicio'))->assertDontSee('Ventas del mes');
        $this->actingAs($this->propietario)->get(route('admin.ventas.index'))->assertOk()->assertSee('Restaurante El Río');
    }
}
