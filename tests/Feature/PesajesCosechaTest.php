<?php

namespace Tests\Feature;

use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\PesajeCosecha;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PesajesCosechaTest extends TestCase
{
    use RefreshDatabase;

    private User $propietario;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $this->propietario = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subMonths(5),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 400, 'biomasa_kg' => 400]);
    }

    private function cosechar(array $datos)
    {
        return $this->actingAs($this->propietario)->post(route('admin.cosechas.store', $this->siembra),
            $datos + ['fecha' => now()->toDateString(), 'peces' => 120]);
    }

    public function test_por_canastas_el_servidor_calcula_el_neto_de_cada_una_y_el_total(): void
    {
        $this->cosechar(['tara_kg' => 2.1, 'pesajes' => [25.4, 24.85, 23.1]])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('estado', fn ($mensaje) => str_contains($mensaje, 'en 3 canastas'));

        $cosecha = Cosecha::sole();
        // (25,40 − 2,10) + (24,85 − 2,10) + (23,10 − 2,10) = 67,05 kg
        $this->assertSame(67.05, $cosecha->kg);
        $this->assertSame(558.75, $cosecha->peso_promedio_g); // 67,05 kg / 120 peces

        $pesajes = $cosecha->pesajes;
        $this->assertSame([1, 2, 3], $pesajes->pluck('numero')->all());
        $this->assertSame([23.3, 22.75, 21.0], $pesajes->pluck('neto_kg')->all());
        $this->assertTrue($pesajes->every(fn (PesajeCosecha $p) => $p->tara_kg === 2.1));
        $this->assertSame(880, $this->siembra->fresh()->peces_vivos);
    }

    public function test_si_vienen_canastas_se_ignora_un_total_enviado_por_el_navegador(): void
    {
        $this->cosechar(['kg' => 999, 'tara_kg' => 2, 'pesajes' => [12, 14]])->assertSessionHasNoErrors();

        $this->assertSame(22.0, Cosecha::sole()->kg);
    }

    public function test_el_modo_total_neto_sigue_funcionando_sin_canastas(): void
    {
        $this->cosechar(['kg' => 48.5])->assertSessionHasNoErrors();

        $this->assertSame(48.5, Cosecha::sole()->kg);
        $this->assertSame(0, PesajeCosecha::count());
    }

    public function test_valida_tara_y_pesos_de_las_canastas(): void
    {
        $this->cosechar([])->assertInvalid(['kg']);
        $this->cosechar(['pesajes' => [20]])->assertInvalid(['tara_kg']);
        $this->cosechar(['tara_kg' => 2, 'pesajes' => [20, '']])->assertInvalid(['pesajes.1']);
        // Una canasta que pesa igual o menos que la tara
        $this->cosechar(['tara_kg' => 2, 'pesajes' => [20, 2]])->assertInvalid(['pesajes.1' => 'La canasta 2 pesa igual o menos que la tara']);
        $this->cosechar(['tara_kg' => 2, 'pesajes' => array_fill(0, 201, 10)])->assertInvalid(['pesajes']);

        $this->assertSame(0, Cosecha::count() + PesajeCosecha::count());
        $this->assertSame(1000, $this->siembra->fresh()->peces_vivos);
    }

    public function test_si_la_cosecha_falla_no_quedan_canastas_sueltas(): void
    {
        $this->cosechar(['peces' => 5000, 'tara_kg' => 2, 'pesajes' => [20, 22]])->assertInvalid(['peces']);

        $this->assertSame(0, PesajeCosecha::count());
    }

    public function test_la_base_de_datos_exige_bruto_mayor_que_la_tara_y_neto_coherente(): void
    {
        $this->cosechar(['kg' => 10]);
        $cosecha = Cosecha::sole();

        $this->expectException(QueryException::class);
        PesajeCosecha::create(['cosecha_id' => $cosecha->id, 'numero' => 1, 'bruto_kg' => 10, 'tara_kg' => 2, 'neto_kg' => 9]);
    }

    public function test_el_historial_dice_en_cuantas_canastas_se_peso(): void
    {
        $this->cosechar(['tara_kg' => 2, 'pesajes' => [12, 14]]);

        $this->actingAs($this->propietario)->get(route('admin.lagos.show', $this->lago))
            ->assertOk()->assertSee('22,00 kg (183 g en promedio) en 2 canastas');
    }

    public function test_si_la_cosecha_vuelve_con_errores_se_reabre_con_las_canastas_escritas(): void
    {
        $this->actingAs($this->propietario)->from(route('admin.lagos.show', $this->lago))
            ->post(route('admin.cosechas.store', $this->siembra), [
                'fecha' => now()->toDateString(), 'peces' => 120, 'tara_kg' => 2, 'pesajes' => [20, 1.5],
                '_modal' => 'modal-cosecha-'.$this->siembra->id,
            ]);

        $this->actingAs($this->propietario)->get(route('admin.lagos.show', $this->lago))
            ->assertOk()
            ->assertSee('value="1.5"', false)
            ->assertSee('La canasta 2 pesa igual o menos que la tara');
    }
}
