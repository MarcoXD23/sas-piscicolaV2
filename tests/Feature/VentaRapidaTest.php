<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Jornada;
use App\Models\Lago;
use App\Models\PrecioEspecie;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tercero;
use App\Models\User;
use App\Models\Venta;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * F6c-1: venta rápida en la jornada abierta, por especie y sin cosecha, con cupo y forma de pago; y todo lo que lee
 * ventas sigue funcionando con una venta de jornada (Ventas, cartera, informe mensual, nómina, jornalero, Mi trabajo).
 * Hoy es 2026-10-15 (jueves) en la finca. Nombres fijos: varias pantallas se revisan por su texto.
 */
class VentaRapidaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $administrador;

    private User $propietario;

    private User $fijo;

    private Tercero $jornalero;

    private Lago $lago;

    private int $cachama;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->administrador = $this->persona('Ana Admin', Rol::ADMINISTRADOR);
        $this->propietario = $this->persona('Pedro Dueño', Rol::PROPIETARIO);
        $this->fijo = $this->persona('Rosa Fija', Rol::TRABAJADOR);
        $this->jornalero = Tercero::create(['nombres' => 'Luis', 'apellidos' => 'Campo', 'es_jornalero' => true]);
        foreach ([CatalogoAjustes::PROCESO_JORNADAS => CatalogoAjustes::ENCENDIDA, CatalogoAjustes::VENTAS_CUPO_KG => 10] as $clave => $valor) {
            AjusteFinca::query()->create(['clave' => $clave, 'valor' => $valor]);
        }
        $this->cachama = $this->especie('cachama blanca');
        PrecioEspecie::create(['especie_id' => $this->cachama, 'precio_general' => 9000, 'entra_en_cupo' => true, 'precio_cupo' => 7000]);

        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->cachama, 'fecha' => '2026-04-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 400, 'biomasa_kg' => 400]);
    }

    private function persona(string $nombre, string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $nombre]))->asignarRoles([$rol]);
    }

    private function jornada(string $fecha = '2026-10-15'): Jornada
    {
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => $fecha, 'lagos' => [$this->lago->id]])->assertSessionHasNoErrors();

        return Jornada::query()->latest('id')->firstOrFail();
    }

    private function vender(Jornada $jornada, array $datos, ?User $quien = null)
    {
        return $this->actingAs($quien ?? $this->administrador)->post(route('admin.jornadas.ventas.store', $jornada), $datos + ['especie_id' => $this->cachama, 'kg' => 4]);
    }

    public function test_venta_a_particular_de_contado_sin_cosecha(): void
    {
        $jornada = $this->jornada();

        $this->vender($jornada, ['tipo_comprador' => 'particular', 'cliente' => 'Restaurante El Río', 'forma_pago' => 'contado', 'recibio_id' => $this->administrador->id])
            ->assertSessionHasNoErrors();

        $venta = Venta::query()->sole();
        $this->assertSame([null, $jornada->id, $this->cachama, 4 * 9000, 'contado', 0, $this->administrador->id, null, '2026-10-15'],
            [$venta->cosecha_id, $venta->jornada_id, $venta->especie_id, $venta->total, $venta->forma_pago, $venta->saldo, $venta->recibio_id, $venta->kg_cupo, $venta->fecha->toDateString()]);
        $this->assertSame(1, Bitacora::query()->where('accion', 'venta')->count());

        // De contado hay que decir quién recibió el dinero
        $this->vender($jornada, ['tipo_comprador' => 'particular', 'cliente' => 'Otro', 'forma_pago' => 'contado'])->assertSessionHasErrors('recibio_id');
    }

    public function test_venta_a_fijo_y_jornalero_con_cupo_a_credito(): void
    {
        $jornada = $this->jornada();

        // 12 kg: 10 en el cupo y 2 de exceso
        $this->vender($jornada, ['tipo_comprador' => 'fijo', 'comprador_id' => $this->fijo->id, 'kg' => 12, 'forma_pago' => 'credito'])->assertSessionHasNoErrors();
        $venta = Venta::query()->where('comprador_id', $this->fijo->id)->sole();
        $this->assertSame([10.0, 7000, 2.0, 10 * 7000 + 2 * 9000, 10 * 7000 + 2 * 9000], [$venta->kg_cupo, $venta->precio_cupo, $venta->kgExceso(), $venta->total, $venta->saldo]);

        // El jornalero tiene su propio cupo
        $this->vender($jornada, ['tipo_comprador' => 'jornalero', 'tercero_id' => $this->jornalero->id, 'kg' => 3, 'forma_pago' => 'credito'])->assertSessionHasNoErrors();
        $this->assertSame(3 * 7000, Venta::query()->where('tercero_id', $this->jornalero->id)->sole()->total);
        $this->assertSame(3 * 7000, $this->jornalero->fresh()->saldoPorCobrar());
    }

    public function test_sin_elegir_forma_de_pago_usa_la_de_su_ultima_compra(): void
    {
        $jornada = $this->jornada();
        $this->vender($jornada, ['tipo_comprador' => 'jornalero', 'tercero_id' => $this->jornalero->id, 'forma_pago' => 'contado', 'recibio_id' => $this->administrador->id])->assertSessionHasNoErrors();

        $this->vender($jornada, ['tipo_comprador' => 'jornalero', 'tercero_id' => $this->jornalero->id, 'forma_pago' => 'ultima', 'recibio_id' => $this->administrador->id])->assertSessionHasNoErrors();
        $this->assertSame(['contado', 'contado'], Venta::query()->orderBy('id')->pluck('forma_pago')->all());

        // Sin compras anteriores, a un trabajador se le descuenta del pago
        $this->vender($jornada, ['tipo_comprador' => 'fijo', 'comprador_id' => $this->fijo->id, 'forma_pago' => 'ultima'])->assertSessionHasNoErrors();
        $this->assertSame('credito', Venta::query()->where('comprador_id', $this->fijo->id)->sole()->forma_pago);
    }

    public function test_el_cupo_se_cuenta_con_la_fecha_de_la_finca(): void
    {
        // Domingo 18 a las 22:00 en Bogotá es lunes 19 en UTC: la semana de la finca sigue siendo la del 12 al 18
        $this->travelTo(Carbon::parse('2026-10-18 22:00', 'America/Bogota'));
        $lunes = Jornada::create(['fecha' => '2026-10-12', 'estado' => Jornada::CERRADA, 'user_id' => $this->administrador->id]);
        Venta::create(['jornada_id' => $lunes->id, 'especie_id' => $this->cachama, 'user_id' => $this->administrador->id, 'comprador_id' => $this->fijo->id,
            'cliente' => 'Rosa Fija', 'fecha' => '2026-10-12', 'kg' => 8, 'kg_cupo' => 8, 'precio_cupo' => 7000, 'precio_kg' => 9000, 'total' => 56000, 'forma_pago' => 'credito', 'saldo' => 56000]);
        $domingo = $this->jornada('2026-10-18');

        $this->vender($domingo, ['tipo_comprador' => 'fijo', 'comprador_id' => $this->fijo->id, 'kg' => 5, 'forma_pago' => 'credito'])->assertSessionHasNoErrors();
        $this->assertSame(2.0, Venta::query()->where('jornada_id', $domingo->id)->sole()->kg_cupo);
    }

    public function test_solo_con_la_jornada_abierta_y_desde_su_dia(): void
    {
        $manana = $this->jornada('2026-10-16');
        $this->vender($manana, ['tipo_comprador' => 'particular', 'cliente' => 'X', 'forma_pago' => 'credito'])->assertSessionHasErrors('jornada');

        $hoy = $this->jornada();
        $this->actingAs($this->administrador)->post(route('admin.jornadas.cancelar', $hoy))->assertSessionHasNoErrors();
        $this->vender($hoy, ['tipo_comprador' => 'particular', 'cliente' => 'X', 'forma_pago' => 'credito'])->assertSessionHasErrors('jornada');

        $this->assertSame(0, Venta::query()->count());
    }

    public function test_sin_precio_no_se_vende_y_el_propietario_no_vende(): void
    {
        $jornada = $this->jornada();
        PrecioEspecie::query()->delete();
        $this->vender($jornada, ['tipo_comprador' => 'particular', 'cliente' => 'X', 'forma_pago' => 'credito'])->assertSessionHasErrors('especie_id');

        $this->vender($jornada, ['tipo_comprador' => 'particular', 'cliente' => 'X', 'forma_pago' => 'credito'], $this->propietario)->assertForbidden();
        $this->assertSame(0, Venta::query()->count());
    }

    public function test_con_jornadas_ventas_queda_para_consulta_y_abonos(): void
    {
        $jornada = $this->jornada();
        $this->vender($jornada, ['tipo_comprador' => 'particular', 'cliente' => 'Restaurante El Río', 'forma_pago' => 'credito'])->assertSessionHasNoErrors();
        $venta = Venta::query()->sole();

        $this->actingAs($this->administrador)->get(route('admin.ventas.index'))->assertOk()
            ->assertSee(__('ventas.con_jornadas'))->assertSee('Jornada 15/10 · Cachama blanca')->assertSee('Restaurante El Río')
            ->assertDontSee('data-abrir-modal="modal-venta"', false);
        $this->actingAs($this->administrador)->post(route('admin.ventas.store'), [])->assertSessionHasErrors('venta');

        // Los abonos siguen
        $this->actingAs($this->administrador)->post(route('admin.ventas.abonar', $venta), ['fecha' => '2026-10-15', 'valor' => 10000, 'medio' => 'efectivo'])->assertSessionHasNoErrors();
        $this->assertSame(4 * 9000 - 10000, $venta->fresh()->saldo);
    }

    public function test_lo_que_lee_ventas_funciona_con_una_venta_de_jornada(): void
    {
        $jornada = $this->jornada();
        $this->vender($jornada, ['tipo_comprador' => 'fijo', 'comprador_id' => $this->fijo->id, 'kg' => 5, 'forma_pago' => 'credito'])->assertSessionHasNoErrors();
        $this->vender($jornada, ['tipo_comprador' => 'jornalero', 'tercero_id' => $this->jornalero->id, 'kg' => 2, 'forma_pago' => 'credito'])->assertSessionHasNoErrors();

        // Cartera e Inicio del propietario
        $this->actingAs($this->propietario)->get(route('admin.ventas.index'))->assertOk()->assertSee('Rosa Fija')->assertSee('Luis Campo');
        $this->actingAs($this->propietario)->get(route('inicio'))->assertOk();
        // Informe mensual: los totales incluyen la venta de jornada
        $this->actingAs($this->propietario)->get(route('admin.reportes.mensual', ['mes' => '2026-10']))->assertOk();
        // Mi trabajo del fijo: su crédito de pescado
        $this->ponerEnTurno($this->fijo, Rol::TRABAJADOR);
        $this->actingAs($this->fijo)->get(route('mi-trabajo.index'))->assertOk();
        // Nómina: el crédito del fijo se descuenta (saldo por comprador)
        $this->actingAs($this->propietario)->get(route('admin.nomina.index'))->assertOk();
        $this->assertSame(5 * 7000, (int) Venta::query()->conSaldo()->where('comprador_id', $this->fijo->id)->sum('saldo'));
        // Jornalero: su saldo pendiente
        $this->assertSame(2 * 7000, $this->jornalero->fresh()->saldoPorCobrar());
        // Detalle de la jornada
        $this->actingAs($this->administrador)->get(route('admin.jornadas.show', $jornada))->assertOk()->assertSee('Rosa Fija');
    }

    public function test_con_jornadas_apagadas_la_venta_por_cosecha_sigue_igual(): void
    {
        AjusteFinca::query()->updateOrCreate(['clave' => CatalogoAjustes::PROCESO_JORNADAS], ['valor' => CatalogoAjustes::APAGADA]);

        $this->actingAs($this->administrador)->get(route('admin.ventas.index'))->assertOk()->assertDontSee(__('ventas.con_jornadas'));
        $this->actingAs($this->administrador)->post(route('admin.ventas.store'), ['cosecha_id' => 999999, 'cliente' => 'X', 'fecha' => '2026-10-15', 'kg' => 1, 'precio_kg' => 1, 'forma_pago' => 'contado'])
            ->assertSessionHasErrors('cosecha_id')->assertSessionDoesntHaveErrors('venta');
    }
}
