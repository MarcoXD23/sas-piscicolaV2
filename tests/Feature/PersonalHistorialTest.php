<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Personal: al guardar, los roles quedan en solo lectura y cada aprobación o cambio queda en un historial (bitácora).
 */
class PersonalHistorialTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-15 09:30', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'name' => 'Dueña']))->asignarRoles([Rol::PROPIETARIO]);
    }

    private function pendiente(string $nombre = 'Pepe'): User
    {
        return User::factory()->pendiente()->create(['tenant_id' => $this->finca->id, 'name' => $nombre]);
    }

    private function guardar(User $persona, array $roles): TestResponse
    {
        return $this->actingAs($this->propietario)->put(route('admin.personal.update', $persona->id), ['roles' => $roles, 'usuario_id' => $persona->id]);
    }

    public function test_al_aprobar_la_fila_queda_en_solo_lectura_y_se_registra_la_aprobacion(): void
    {
        $pepe = $this->pendiente();
        $this->actingAs($this->propietario)->get(route('admin.personal.index'))->assertSee(__('personal.aprobar_asignar'))->assertDontSee(__('personal.cambiar_roles'));

        $this->guardar($pepe, [Rol::TRABAJADOR, Rol::CELADOR])->assertSessionHas('estado');

        $this->actingAs($this->propietario)->get(route('admin.personal.index'))->assertOk()
            ->assertSee('data-roles-actuales', false)
            ->assertSeeInOrder([__('bitacora.roles.trabajador'), __('bitacora.roles.celador'), __('personal.cambiar_roles')])
            ->assertDontSee(__('personal.aprobar_asignar'))
            ->assertSee(__('personal.ver_historial', ['cantidad' => 1]))
            ->assertSee('Pepe aprobado como Trabajador, Celador');

        $registro = Bitacora::sole();
        $this->assertSame(['aprobacion', $pepe->id, $this->propietario->id, Rol::PROPIETARIO], [$registro->accion, $registro->persona_id, $registro->user_id, $registro->rol]);
        $this->assertSame('2026-10-15 09:30', $registro->momentoLocal()->format('Y-m-d H:i'));
    }

    public function test_cada_cambio_de_roles_queda_con_los_roles_de_antes_y_despues_y_sin_repetirse(): void
    {
        $pepe = $this->pendiente();
        $this->guardar($pepe, [Rol::TRABAJADOR, Rol::CELADOR]);
        $this->guardar($pepe, [Rol::ADMINISTRADOR, Rol::TRABAJADOR]);
        $this->guardar($pepe, [Rol::TRABAJADOR, Rol::ADMINISTRADOR]);   // mismos roles en otro orden: no se registra

        $this->assertSame(['aprobacion', 'roles'], Bitacora::query()->orderBy('id')->pluck('accion')->all());
        $this->assertSame('Roles de Pepe: Trabajador, Celador → Administrador, Trabajador', Bitacora::query()->latest('id')->first()->textoDetalle());
        $this->assertTrue($pepe->fresh()->tieneRol(Rol::ADMINISTRADOR));

        $this->actingAs($this->propietario)->get(route('admin.personal.index'))
            ->assertSee(__('personal.ver_historial', ['cantidad' => 2]))
            ->assertSee('Roles de Pepe: Trabajador, Celador → Administrador, Trabajador')
            ->assertSee(__('personal.hecho_por', ['nombre' => 'Dueña']));

        // También aparece en la bitácora
        $this->actingAs($this->propietario)->get(route('admin.bitacora.index'))->assertSee(__('bitacora.acciones.roles'))->assertSee(__('bitacora.acciones.aprobacion'));
    }

    public function test_el_cambio_en_tu_cuenta_tambien_queda_registrado(): void
    {
        $this->actingAs($this->propietario)->put(route('admin.personal.mi-rol-administrador'), ['administrador' => '1']);
        $this->actingAs($this->propietario)->put(route('admin.personal.mi-rol-administrador'), ['administrador' => '1']);   // sin cambio
        $this->actingAs($this->propietario)->put(route('admin.personal.mi-rol-administrador'), ['administrador' => '0']);

        $registros = Bitacora::query()->orderBy('id')->get();
        $this->assertCount(2, $registros);
        $this->assertSame([$this->propietario->id, $this->propietario->id], $registros->pluck('persona_id')->all());
        $this->assertSame('Roles de Dueña: Propietario → Propietario, Administrador', $registros[0]->textoDetalle());
        $this->assertSame('Roles de Dueña: Propietario, Administrador → Propietario', $registros[1]->textoDetalle());

        $this->actingAs($this->propietario)->get(route('admin.personal.index'))->assertSee(__('personal.ver_historial', ['cantidad' => 2]));
    }

    public function test_el_historial_no_se_puede_editar_y_solo_lo_ve_el_propietario_de_la_finca(): void
    {
        $pepe = $this->pendiente();
        $this->guardar($pepe, [Rol::TRABAJADOR]);

        $this->expectExceptionObject(new \LogicException(__('bitacora.solo_insercion')));
        try {
            $administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
            $this->actingAs($administrador)->get(route('admin.personal.index'))->assertForbidden();

            // Otra finca no ve este historial
            $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
            app(TenantActual::class)->establecer($otra->id);
            $ajeno = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::PROPIETARIO]);
            $this->actingAs($ajeno)->get(route('admin.personal.index'))->assertOk()->assertDontSee('Pepe');
            app(TenantActual::class)->establecer($this->finca->id);
        } finally {
            Bitacora::sole()->update(['accion' => 'roles']);
        }
    }
}
