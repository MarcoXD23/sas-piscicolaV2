<?php

namespace Tests\Feature;

use App\Models\Especie;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PrimerUso;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lista de primeros pasos de una finca nueva: aparece al propietario y al administrador, cada paso se marca solo,
 * se oculta para toda la finca y no vuelve a aparecer al ocultarse ni al completarse.
 */
class PrimerUsoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finca = Tenant::create(['nombre' => 'Finca Nueva', 'codigo' => 'NUEVA-01', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'idioma' => 'es']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
    }

    private function inicio(User $usuario)
    {
        return $this->actingAs($usuario)->get(route('inicio'))->assertOk();
    }

    public function test_aparece_en_una_finca_nueva_al_propietario_y_al_administrador(): void
    {
        // El administrador ya se registró con el código: «Invita a tu personal» sale hecho
        $this->inicio($this->propietario)->assertSee(__('primer_uso.titulo'))->assertSee('Llevas 1 de 6')
            ->assertSee('value="NUEVA-01"', false)->assertSee(__('primer_uso.codigo_ayuda'))->assertSee(route('admin.ajustes.edit'));
        $this->inicio($this->administrador)->assertSee(__('primer_uso.titulo'));

        foreach ([Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = User::factory()->conRoles($rol)->create(['tenant_id' => $this->finca->id]);
            $this->inicio($persona)->assertDontSee(__('primer_uso.titulo'));
        }
    }

    public function test_cada_paso_se_marca_al_cumplirse_y_al_completarse_no_vuelve(): void
    {
        $hechos = fn (User $quien) => collect(PrimerUso::pasos($quien->fresh()))->where('hecho', true)->pluck('clave')->all();

        // 1. Guardar Ajustes (propietario)
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), [
            'nombre' => 'Finca Nueva', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'idioma' => 'es',
            'alerta_stock_dias' => 7, 'alerta_mortalidad_porcentaje' => 2, 'alerta_mortalidad_dias' => 7, 'alerta_despacho_dias' => 3,
        ])->assertSessionHasNoErrors();
        $this->assertSame(['ajustes', 'invitar'], $hechos($this->propietario));   // invitar: el administrador ya se registró

        // 2. Revisar especies y 3. definir pesos (administrador)
        $this->actingAs($this->administrador)->post(route('admin.especies.activo', Especie::deClave('bocachico')))->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->put(route('admin.ajustes-pesaje.update'), ['peso_canasta_kg' => 2, 'peso_canastilla_kg' => 1.5])->assertSessionHasNoErrors();
        // 4. Primer lago
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->assertSame(['ajustes', 'especies', 'pesos', 'lago', 'invitar'], $hechos($this->propietario));
        $this->inicio($this->propietario)->assertSee('Llevas 5 de 6');

        // 6. Alguien se registra con el código y el propietario lo aprueba como trabajador
        $nueva = User::factory()->pendiente()->create(['tenant_id' => $this->finca->id]);
        $this->assertCount(5, $hechos($this->propietario));   // pendiente: todavía no cuenta
        $this->actingAs($this->propietario)->put(route('admin.personal.update', $nueva->id), ['usuario_id' => $nueva->id, 'roles' => [Rol::TRABAJADOR]])
            ->assertSessionHasNoErrors();
        $this->assertCount(6, $hechos($this->propietario));

        // Completa: desaparece y queda completada; aunque se borre el único lago no vuelve
        $this->inicio($this->propietario)->assertDontSee(__('primer_uso.titulo'));
        $this->assertNotNull($this->finca->fresh()->primer_uso_completado_at);
        $lago->delete();
        $this->inicio($this->propietario)->assertDontSee(__('primer_uso.titulo'));
        $this->inicio($this->administrador)->assertDontSee(__('primer_uso.titulo'));
    }

    public function test_se_oculta_para_toda_la_finca_y_no_reaparece(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.primer-uso.ocultar'))->assertRedirect(route('inicio'));

        $this->assertNotNull($this->finca->fresh()->primer_uso_oculto_at);
        $this->inicio($this->propietario)->assertDontSee(__('primer_uso.titulo'));
        $this->inicio($this->administrador)->assertDontSee(__('primer_uso.titulo'));

        // Ni siquiera al cumplir pasos después
        Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->inicio($this->propietario)->assertDontSee(__('primer_uso.titulo'));
    }

    public function test_respeta_los_roles(): void
    {
        // El administrador no hace Ajustes ni Personal: sin enlace, con el aviso de que lo hace el propietario
        $this->inicio($this->administrador)->assertSee(__('primer_uso.lo_hace_propietario'))
            ->assertDontSee('href="'.route('admin.ajustes.edit').'"', false)->assertSee(route('admin.ajustes-pesaje.edit'));
        // El propietario sin rol de administrador no define los pesos
        $this->inicio($this->propietario)->assertSee(__('primer_uso.lo_hace_administrador'))->assertDontSee(route('admin.ajustes-pesaje.edit'));

        $trabajador = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->finca->id]);
        $this->actingAs($trabajador)->post(route('admin.primer-uso.ocultar'))->assertForbidden();
        $this->assertNull($this->finca->fresh()->primer_uso_oculto_at);
    }

    public function test_aislamiento_cada_finca_tiene_su_lista(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $duenoOtra = User::factory()->conRoles(Rol::PROPIETARIO, Rol::ADMINISTRADOR)->create(['tenant_id' => $otra->id]);
        Lago::create(['nombre' => 'X1', 'tipo' => 'tierra']);
        $this->actingAs($duenoOtra)->post(route('admin.primer-uso.ocultar'));

        // El lago y el ocultar de la otra finca no cuentan aquí
        app(TenantActual::class)->establecer($this->finca->id);
        $this->inicio($this->propietario)->assertSee(__('primer_uso.titulo'))->assertSee('Llevas 1 de 6');
        $this->assertNull($this->finca->fresh()->primer_uso_oculto_at);
    }

    public function test_la_migracion_da_por_completada_la_lista_de_fincas_que_ya_tienen_lagos(): void
    {
        $operando = Tenant::create(['nombre' => 'Operando', 'codigo' => 'F03']);
        app(TenantActual::class)->establecer($operando->id);
        Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);

        $migracion = require database_path('migrations/2026_10_29_000001_add_primer_uso_to_tenants_table.php');
        $migracion->down();
        $migracion->up();

        $this->assertNotNull(DB::table('tenants')->where('id', $operando->id)->value('primer_uso_completado_at'));
        $this->assertNull(DB::table('tenants')->where('id', $this->finca->id)->value('primer_uso_completado_at'));
    }
}
