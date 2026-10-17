<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LagoDetalleTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_historial_reune_los_registros_del_lago(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);

        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $siembra = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('cachama blanca'), 'fecha' => now()->subDays(20),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
        StockAlimento::create(['tipo_concentrado' => 'Levante 32 %', 'cantidad_kg' => 100]);

        $hoy = now()->toDateString();
        $this->actingAs($propietario)->post(route('admin.mortalidades.store', $siembra), ['fecha' => $hoy, 'cantidad' => 7]);
        $this->actingAs($propietario)->post(route('admin.muestreos.store', $siembra), ['fecha' => $hoy, 'peces_pesados' => 10, 'peso_total_g' => 600]);
        $this->actingAs($propietario)->post(route('admin.raciones.store', $lago), ['fecha' => $hoy, 'tipo_concentrado' => 'Levante 32 %', 'cantidad_kg' => 3]);
        $this->actingAs($propietario)->post(route('admin.traslados.store', $siembra), ['lago_destino_id' => $otro->id, 'fecha' => $hoy, 'cantidad' => 100, 'peso_promedio_g' => 60, 'modo' => 'aparte']);

        $this->actingAs($propietario)->get(route('admin.lagos.show', $lago))
            ->assertOk()
            ->assertSee('Siembra de Cachama blanca')
            ->assertSee('Mortalidad · Cachama blanca')
            ->assertSee('Muestreo · Cachama blanca')
            ->assertSee('3,0 kg de Levante 32 %')
            ->assertSee('Traslado hacia L02');

        $this->actingAs($propietario)->get(route('admin.lagos.show', $otro))->assertSee('Traslado desde L01');
    }

    public function test_no_se_ve_el_detalle_de_un_lago_de_otra_finca(): void
    {
        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        // Finca pequeña: el dueño también es el administrador (opera lagos, bodega y ventas)
        $propietario = tap(User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $ajeno = Lago::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'nombre' => 'X1', 'tipo' => 'tierra']);

        $this->actingAs($propietario)->get(route('admin.lagos.show', $ajeno))->assertNotFound();
    }

    public function test_el_celador_no_ve_el_detalle(): void
    {
        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $celador = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::CELADOR]);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);

        $this->actingAs($celador)->get(route('admin.lagos.show', $lago))->assertForbidden();
    }
}
