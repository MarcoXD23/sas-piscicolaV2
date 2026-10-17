<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Jornada;
use App\Models\Lago;
use App\Models\Obsequio;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F6c-1: obsequios de la jornada. Quien registra elige quién autorizó; nunca él mismo. Por defecto autoriza solo el
 * propietario; con el ajuste, también un administrador. El propietario ve el aviso en su Inicio (últimos 7 días).
 */
class ObsequiosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $administrador;

    private User $propietario;

    private Jornada $jornada;

    private int $cachama;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->administrador = $this->persona('Ana Admin', [Rol::ADMINISTRADOR]);
        $this->propietario = $this->persona('Pedro Dueño', [Rol::PROPIETARIO]);
        AjusteFinca::query()->create(['clave' => CatalogoAjustes::PROCESO_JORNADAS, 'valor' => CatalogoAjustes::ENCENDIDA]);
        $this->cachama = $this->especie('cachama blanca');

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->cachama, 'fecha' => '2026-04-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 400, 'biomasa_kg' => 400]);
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-15', 'lagos' => [$lago->id]])->assertSessionHasNoErrors();
        $this->jornada = Jornada::query()->sole();
    }

    private function persona(string $nombre, array $roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $nombre]))->asignarRoles($roles);
    }

    private function obsequiar(int $autoriza, ?User $quien = null)
    {
        return $this->actingAs($quien ?? $this->administrador)->post(route('admin.jornadas.obsequios.store', $this->jornada), [
            'especie_id' => $this->cachama, 'kg' => 3, 'destinatario' => 'Vecino de la vereda', 'autorizado_por' => $autoriza,
        ]);
    }

    public function test_el_administrador_lo_registra_y_el_propietario_lo_autoriza(): void
    {
        $this->obsequiar($this->propietario->id)->assertSessionHasNoErrors();

        $obsequio = Obsequio::query()->sole();
        $this->assertSame([3.0, 'Vecino de la vereda', $this->administrador->id, $this->propietario->id],
            [$obsequio->kg, $obsequio->destinatario, $obsequio->registrado_por, $obsequio->autorizado_por]);
        $this->assertSame(1, Bitacora::query()->where('accion', 'obsequio')->count());
        $this->actingAs($this->administrador)->get(route('admin.jornadas.show', $this->jornada))->assertOk()->assertSee('Vecino de la vereda');
    }

    public function test_nadie_se_autoriza_a_si_mismo(): void
    {
        $this->obsequiar($this->administrador->id)->assertSessionHasErrors('autorizado_por');

        // Ni el propietario que también es administrador
        $dueno = $this->persona('Marta Dueña', [Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $this->obsequiar($dueno->id, $dueno)->assertSessionHasErrors('autorizado_por');
        $this->assertSame(0, Obsequio::query()->count());
    }

    public function test_por_defecto_solo_autoriza_el_propietario(): void
    {
        $otroAdmin = $this->persona('Carlos Admin', [Rol::ADMINISTRADOR]);
        $this->obsequiar($otroAdmin->id)->assertSessionHasErrors('autorizado_por');

        AjusteFinca::query()->create(['clave' => CatalogoAjustes::OBSEQUIOS_AUTORIZA, 'valor' => CatalogoAjustes::AUTORIZA_PROPIETARIO_O_ADMINISTRADOR]);
        $this->obsequiar($otroAdmin->id)->assertSessionHasNoErrors();
        $this->assertSame($otroAdmin->id, Obsequio::query()->sole()->autorizado_por);
    }

    public function test_solo_con_la_jornada_abierta(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.jornadas.cancelar', $this->jornada))->assertSessionHasNoErrors();

        $this->obsequiar($this->propietario->id)->assertSessionHasErrors('jornada');
        $this->assertSame(0, Obsequio::query()->count());
    }

    public function test_el_propietario_ve_el_aviso_en_su_inicio_los_ultimos_7_dias(): void
    {
        $this->obsequiar($this->propietario->id)->assertSessionHasNoErrors();

        $this->actingAs($this->propietario)->get(route('inicio'))->assertOk()->assertSee(__('obsequios.aviso_inicio'))->assertSee('Vecino de la vereda');
        $this->actingAs($this->administrador)->get(route('inicio'))->assertOk()->assertDontSee(__('obsequios.aviso_inicio'));

        $this->travel(8)->days();
        $this->actingAs($this->propietario)->get(route('inicio'))->assertOk()->assertDontSee(__('obsequios.aviso_inicio'));
    }
}
