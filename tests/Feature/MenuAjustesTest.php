<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Menu;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entradas del menú que dependen de un ajuste de la finca (Jornadas: proceso.jornadas). Sin finca activa (por ejemplo,
 * al crear la finca con el enlace de invitación) el menú no puede leer ajustes: la entrada no aparece y no falla.
 */
class MenuAjustesTest extends TestCase
{
    use RefreshDatabase;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($finca->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        AjusteFinca::query()->create(['clave' => CatalogoAjustes::PROCESO_JORNADAS, 'valor' => CatalogoAjustes::ENCENDIDA]);
    }

    private function rutas(): array
    {
        return Menu::para($this->administrador)->pluck('ruta')->all();
    }

    public function test_con_el_ajuste_encendido_aparece_jornadas(): void
    {
        $this->assertContains('admin.jornadas.index', $this->rutas());
    }

    public function test_sin_finca_activa_el_menu_no_falla_ni_muestra_jornadas(): void
    {
        app(TenantActual::class)->establecer(null);

        $rutas = $this->rutas();

        $this->assertNotContains('admin.jornadas.index', $rutas);
        $this->assertContains('inicio', $rutas);
    }
}
