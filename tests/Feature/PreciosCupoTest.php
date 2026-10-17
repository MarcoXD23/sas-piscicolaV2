<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Jornada;
use App\Models\PrecioEspecie;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\Tercero;
use App\Models\User;
use App\Models\Venta;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\TenantActual;
use App\Support\Ventas\Cupo;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6c-1: Ajustes › Precios (solo el propietario, con bitácora) y el cupo del trabajador: período con la fecha de la
 * finca y reparto entre cupo y exceso. Los valores son de prueba, no de la finca piloto.
 */
class PreciosCupoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    private User $administrador;

    private int $cachama;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pedro Dueño']))->asignarRoles([Rol::PROPIETARIO]);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Admin']))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->cachama = $this->especie('cachama blanca');
    }

    private function ajuste(string $clave, string|int $valor): void
    {
        AjusteFinca::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }

    private function precio(int $general = 9000, bool $cupo = true, ?int $precioCupo = 7000): PrecioEspecie
    {
        return PrecioEspecie::create(['especie_id' => $this->cachama, 'precio_general' => $general, 'entra_en_cupo' => $cupo, 'precio_cupo' => $cupo ? $precioCupo : null]);
    }

    public function test_solo_el_propietario_cambia_los_precios_y_queda_en_la_bitacora(): void
    {
        $this->actingAs($this->propietario)->get(route('admin.ajustes.precios'))->assertOk()->assertSee('Cachama blanca');
        $this->actingAs($this->propietario)->put(route('admin.ajustes.precios.update'), [
            'precios' => [$this->cachama => ['general' => 9000, 'entra_en_cupo' => '1', 'cupo' => 7000]],
        ])->assertSessionHasNoErrors();

        $precio = PrecioEspecie::query()->sole();
        $this->assertSame([9000, true, 7000, $this->propietario->id], [$precio->precio_general, $precio->entra_en_cupo, $precio->precio_cupo, $precio->cambiado_por]);
        $this->assertSame(1, Bitacora::query()->where('accion', 'precio')->count());

        // Sin cambios no se registra otra vez
        $this->actingAs($this->propietario)->put(route('admin.ajustes.precios.update'), [
            'precios' => [$this->cachama => ['general' => 9000, 'entra_en_cupo' => '1', 'cupo' => 7000]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, Bitacora::query()->where('accion', 'precio')->count());

        $this->actingAs($this->administrador)->get(route('admin.ajustes.precios'))->assertForbidden();
        $this->actingAs($this->administrador)->put(route('admin.ajustes.precios.update'), ['precios' => [$this->cachama => ['general' => 1]]])->assertForbidden();
    }

    public function test_si_entra_en_el_cupo_necesita_su_precio_en_el_cupo(): void
    {
        $this->actingAs($this->propietario)->put(route('admin.ajustes.precios.update'), [
            'precios' => [$this->cachama => ['general' => 9000, 'entra_en_cupo' => '1']],
        ])->assertSessionHasErrors("precios.{$this->cachama}.cupo");
        $this->assertSame(0, PrecioEspecie::query()->count());
    }

    public function test_periodos_del_cupo(): void
    {
        $miercoles = CarbonImmutable::parse('2026-10-14');
        $this->assertSame(['2026-10-12', '2026-10-18'], array_map(fn ($d) => $d->toDateString(), Cupo::periodo($miercoles)));

        $this->ajuste(CatalogoAjustes::VENTAS_CUPO_PERIODO, Cupo::QUINCENAL);
        $this->assertSame(['2026-10-01', '2026-10-15'], array_map(fn ($d) => $d->toDateString(), Cupo::periodo($miercoles)));
        $this->assertSame(['2026-10-16', '2026-10-31'], array_map(fn ($d) => $d->toDateString(), Cupo::periodo(CarbonImmutable::parse('2026-10-20'))));

        $this->ajuste(CatalogoAjustes::VENTAS_CUPO_PERIODO, Cupo::MENSUAL);
        $this->assertSame(['2026-10-01', '2026-10-31'], array_map(fn ($d) => $d->toDateString(), Cupo::periodo($miercoles)));
    }

    public function test_reparte_entre_cupo_y_exceso_con_lo_ya_comprado_en_el_periodo(): void
    {
        $this->ajuste(CatalogoAjustes::VENTAS_CUPO_KG, 10);
        $precio = $this->precio();
        $jornalero = Tercero::create(['nombres' => 'Luis', 'apellidos' => 'Campo', 'es_jornalero' => true]);
        $jornada = Jornada::create(['fecha' => '2026-10-12', 'estado' => Jornada::CERRADA, 'user_id' => $this->administrador->id]);
        // Ya compró 7 kg en el cupo el lunes de esta semana; la semana pasada no cuenta
        foreach (['2026-10-12' => 7, '2026-10-05' => 10] as $fecha => $kg) {
            Venta::create(['jornada_id' => $jornada->id, 'especie_id' => $this->cachama, 'user_id' => $this->administrador->id, 'tercero_id' => $jornalero->id,
                'cliente' => 'Luis Campo', 'fecha' => $fecha, 'kg' => $kg, 'kg_cupo' => $kg, 'precio_cupo' => 7000, 'precio_kg' => 9000, 'total' => $kg * 7000, 'forma_pago' => 'credito']);
        }

        $partes = Cupo::repartir(5, $precio, null, $jornalero, CarbonImmutable::parse('2026-10-15'));
        $this->assertSame([3.0, 7000, 2.0, 9000, 3 * 7000 + 2 * 9000, 3.0],
            [$partes['kg_cupo'], $partes['precio_cupo'], $partes['kg_exceso'], $partes['precio_general'], $partes['total'], $partes['quedaba']]);

        // Particular: sin cupo
        $this->assertSame(5 * 9000, Cupo::repartir(5, $precio, null, null, CarbonImmutable::parse('2026-10-15'))['total']);
        // Especie que no entra en el cupo: todo al precio general
        $this->assertSame(0.0, Cupo::repartir(5, $this->precioSinCupo($precio), null, $jornalero, CarbonImmutable::parse('2026-10-15'))['kg_cupo']);
    }

    private function precioSinCupo(PrecioEspecie $precio): PrecioEspecie
    {
        $precio->update(['entra_en_cupo' => false, 'precio_cupo' => null]);

        return $precio->fresh();
    }

    public function test_sin_cupo_todos_pagan_el_precio_general(): void
    {
        $precio = $this->precio();
        $fijo = tap(User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rosa Fija']))->asignarRoles([Rol::TRABAJADOR]);

        $partes = Cupo::repartir(4, $precio, $fijo, null, CarbonImmutable::parse('2026-10-15'));
        $this->assertSame([0.0, null, 4 * 9000], [$partes['kg_cupo'], $partes['precio_cupo'], $partes['total']]);
    }
}
