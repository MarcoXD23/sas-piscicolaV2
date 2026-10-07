<?php

namespace Tests\Feature;

use App\Models\HoraExtra;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Tarea;
use App\Models\Tenant;
use App\Models\Turno;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PersonalDeCampoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    private User $trabajador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 10:00', 'America/Bogota'));

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->tenant->id);
        // Las tareas las asigna el administrador; en esta finca el dueño también lo es
        $this->propietario = $this->persona(Rol::PROPIETARIO, Rol::ADMINISTRADOR);
        $this->trabajador = $this->persona(Rol::TRABAJADOR);
    }

    private function persona(string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles($roles);
    }

    private function tarea(array $datos = []): Tarea
    {
        return Tarea::create($datos + ['asignada_a' => $this->trabajador->id, 'creada_por' => $this->propietario->id,
            'titulo' => 'Limpiar la entrada de agua', 'fecha' => today()]);
    }

    // --- Tareas ---

    public function test_propietario_y_administrador_asignan_tareas_al_personal_de_campo(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);

        $this->actingAs($administrador)->post(route('admin.tareas.store'), [
            'asignada_a' => $this->trabajador->id, 'titulo' => 'Revisar mallas', 'fecha' => '2026-10-08',
        ])->assertSessionHasNoErrors();

        $tarea = Tarea::sole();
        $this->assertSame($administrador->id, $tarea->creada_por);
        $this->assertNull($tarea->completada_at);
    }

    public function test_no_se_asignan_tareas_a_quien_no_es_de_campo_ni_en_fechas_pasadas(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $pendiente = tap(User::factory()->pendiente()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::TRABAJADOR]);
        $otraFinca = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajeno = tap(User::factory()->create(['tenant_id' => $otraFinca->id]))->asignarRoles([Rol::TRABAJADOR]);

        foreach ([$administrador, $pendiente, $ajeno] as $persona) {
            $this->actingAs($this->propietario)->post(route('admin.tareas.store'), [
                'asignada_a' => $persona->id, 'titulo' => 'X', 'fecha' => '2026-10-08',
            ])->assertInvalid(['asignada_a']);
        }

        $this->actingAs($this->propietario)->post(route('admin.tareas.store'), [
            'asignada_a' => $this->trabajador->id, 'titulo' => 'X', 'fecha' => '2026-10-06',
        ])->assertInvalid(['fecha']);

        $this->assertSame(0, Tarea::withoutGlobalScopes()->count());
    }

    public function test_el_trabajador_completa_sus_tareas_solo_en_turno(): void
    {
        $tarea = $this->tarea();

        $this->actingAs($this->trabajador)->post(route('mi-trabajo.tareas.completar', $tarea))->assertForbidden();
        $this->assertNull($tarea->fresh()->completada_at);

        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $this->actingAs($this->trabajador)->post(route('mi-trabajo.tareas.completar', $tarea), ['nota_cierre' => 'Listo'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Listo', $tarea->fresh()->nota_cierre);
        $this->assertNotNull($tarea->fresh()->completada_at);

        // Una segunda vez no se completa de nuevo
        $this->actingAs($this->trabajador)->post(route('mi-trabajo.tareas.completar', $tarea))->assertSessionHasErrors('tarea');
    }

    public function test_nadie_completa_tareas_ajenas(): void
    {
        $otro = $this->persona(Rol::TRABAJADOR);
        $this->ponerEnTurno($otro, Rol::TRABAJADOR);

        $this->actingAs($otro)->post(route('mi-trabajo.tareas.completar', $this->tarea()))->assertForbidden();
    }

    public function test_una_tarea_completada_no_se_elimina_y_una_pendiente_si(): void
    {
        $hecha = $this->tarea(['completada_at' => now()]);
        $pendiente = $this->tarea();

        $this->actingAs($this->propietario)->delete(route('admin.tareas.destroy', $hecha))->assertSessionHasErrors('tarea');
        $this->actingAs($this->propietario)->delete(route('admin.tareas.destroy', $pendiente))->assertSessionHasNoErrors();

        $this->assertModelExists($hecha);
        $this->assertModelMissing($pendiente);
    }

    public function test_el_personal_de_campo_no_gestiona_tareas(): void
    {
        $this->actingAs($this->trabajador)->get(route('admin.tareas.index'))->assertForbidden();
        $this->actingAs($this->propietario)->get(route('mi-trabajo.index'))->assertForbidden();
    }

    // --- Permisos ---

    public function test_solicitar_permiso_y_reglas_de_fechas(): void
    {
        $pedir = fn (array $datos) => $this->actingAs($this->trabajador)->post(route('mi-trabajo.permisos.store'), $datos + ['motivo' => 'Cita médica']);

        $pedir(['fecha_inicio' => '2026-10-06', 'fecha_fin' => '2026-10-06'])->assertInvalid(['fecha_inicio']);
        $pedir(['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-09'])->assertInvalid(['fecha_fin']);
        $pedir(['fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-12'])->assertSessionHasNoErrors();
        // Se cruza con el anterior (pendiente)
        $pedir(['fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-13'])->assertInvalid(['fecha_inicio']);

        $permiso = Permiso::sole();
        $this->assertSame('pendiente', $permiso->estado);
        $this->assertSame(3, $permiso->dias());

        // Si el anterior se rechaza, ya no bloquea las fechas
        $permiso->update(['estado' => 'rechazado']);
        $pedir(['fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-10-13'])->assertSessionHasNoErrors();
    }

    public function test_solo_el_propietario_revisa_y_avisa_los_turnos_afectados(): void
    {
        $permiso = Permiso::create(['user_id' => $this->trabajador->id, 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-11',
            'motivo' => 'Viaje', 'estado' => 'pendiente']);
        Turno::create(['user_id' => $this->trabajador->id, 'rol' => Rol::TRABAJADOR,
            'inicia_at' => '2026-10-11 06:00', 'termina_at' => '2026-10-11 14:00']);

        $this->actingAs($this->persona(Rol::ADMINISTRADOR))->post(route('admin.permisos.revisar', $permiso), ['decision' => 'aprobado'])
            ->assertForbidden();

        $this->actingAs($this->propietario)->post(route('admin.permisos.revisar', $permiso), ['decision' => 'aprobado'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('estado', fn ($mensaje) => str_contains($mensaje, 'Tiene 1 turno(s) en esas fechas'));

        $permiso->refresh();
        $this->assertSame('aprobado', $permiso->estado);
        $this->assertSame($this->propietario->id, $permiso->revisado_por);

        // Ya revisado: no se vuelve a revisar
        $this->actingAs($this->propietario)->post(route('admin.permisos.revisar', $permiso), ['decision' => 'rechazado', 'respuesta' => 'No'])
            ->assertInvalid(['decision']);
        $this->assertSame('aprobado', $permiso->fresh()->estado);
    }

    public function test_rechazar_exige_motivo(): void
    {
        $permiso = Permiso::create(['user_id' => $this->trabajador->id, 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-10',
            'motivo' => 'Viaje', 'estado' => 'pendiente']);

        $this->actingAs($this->propietario)->post(route('admin.permisos.revisar', $permiso), ['decision' => 'rechazado'])
            ->assertInvalid(['respuesta']);
        $this->actingAs($this->propietario)->post(route('admin.permisos.revisar', $permiso), ['decision' => 'rechazado', 'respuesta' => 'Temporada de cosecha'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Temporada de cosecha', $permiso->fresh()->respuesta);
    }

    // --- Horas extra ---

    public function test_reportar_horas_extra_con_limites(): void
    {
        $reportar = fn (array $datos) => $this->actingAs($this->trabajador)->post(route('mi-trabajo.horas-extras.store'), $datos + ['motivo' => 'Cosecha L02']);

        $reportar(['fecha' => '2026-10-08', 'horas' => 2])->assertInvalid(['fecha']);   // futura
        $reportar(['fecha' => '2026-09-01', 'horas' => 2])->assertInvalid(['fecha']);   // más de 30 días
        $reportar(['fecha' => '2026-10-06', 'horas' => 13])->assertInvalid(['horas']);
        $reportar(['fecha' => '2026-10-06', 'horas' => 0.25])->assertInvalid(['horas']);
        $reportar(['fecha' => '2026-10-06', 'horas' => 2.5])->assertSessionHasNoErrors();

        $hora = HoraExtra::sole();
        $this->assertSame(2.5, $hora->horas);
        $this->assertSame('pendiente', $hora->estado);
    }

    public function test_las_horas_aprobadas_del_mes_se_suman_en_mi_trabajo(): void
    {
        foreach ([[2.5, 'aprobado'], [1.5, 'aprobado'], [4, 'rechazado'], [3, 'pendiente']] as [$horas, $estado]) {
            HoraExtra::create(['user_id' => $this->trabajador->id, 'fecha' => '2026-10-05', 'horas' => $horas, 'motivo' => 'X', 'estado' => $estado]);
        }
        HoraExtra::create(['user_id' => $this->trabajador->id, 'fecha' => '2026-09-28', 'horas' => 6, 'motivo' => 'X', 'estado' => 'aprobado']);

        $this->actingAs($this->trabajador)->get(route('mi-trabajo.index'))
            ->assertOk()
            ->assertViewHas('horasAprobadasMes', 4.0)
            ->assertSee('Aprobadas este mes: 4,0 h');
    }

    public function test_el_inicio_avisa_tareas_y_solicitudes_pendientes(): void
    {
        $this->tarea();
        Permiso::create(['user_id' => $this->trabajador->id, 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-10', 'motivo' => 'X', 'estado' => 'pendiente']);
        HoraExtra::create(['user_id' => $this->trabajador->id, 'fecha' => '2026-10-06', 'horas' => 2, 'motivo' => 'X', 'estado' => 'pendiente']);

        $this->actingAs($this->trabajador)->get(route('inicio'))->assertSee('Tienes 1 tarea(s) pendiente(s)');
        $this->actingAs($this->propietario)->get(route('inicio'))->assertSee('2 solicitud(es) de permiso u horas extra por revisar');
        $this->actingAs($this->propietario)->get(route('admin.solicitudes.index'))->assertOk()->assertSee($this->trabajador->name);
    }

    public function test_la_pagina_de_solicitudes_muestra_las_revisadas(): void
    {
        $permiso = Permiso::create(['user_id' => $this->trabajador->id, 'fecha_inicio' => '2026-10-10', 'fecha_fin' => '2026-10-10', 'motivo' => 'X', 'estado' => 'pendiente']);
        $hora = HoraExtra::create(['user_id' => $this->trabajador->id, 'fecha' => '2026-10-06', 'horas' => 2, 'motivo' => 'X', 'estado' => 'pendiente']);
        // Datos aprobados sin fecha de revisión (por ejemplo, importados) no deben romper la página
        HoraExtra::create(['user_id' => $this->trabajador->id, 'fecha' => '2026-10-05', 'horas' => 1, 'motivo' => 'Y', 'estado' => 'aprobado']);

        $this->actingAs($this->propietario)->post(route('admin.permisos.revisar', $permiso), ['decision' => 'aprobado']);
        $this->actingAs($this->propietario)->post(route('admin.horas-extras.revisar', $hora), ['decision' => 'rechazado', 'respuesta' => 'Sin soporte']);

        $this->actingAs($this->propietario)->get(route('admin.solicitudes.index'))
            ->assertOk()
            ->assertSee('Revisadas recientemente')
            ->assertSee('Sin soporte')
            ->assertSee('No hay permisos por revisar.');
    }

    public function test_el_menu_muestra_mi_trabajo_y_tareas_segun_el_rol(): void
    {
        $this->actingAs($this->trabajador)->get(route('inicio'))
            ->assertSee(route('mi-trabajo.index'))->assertDontSee(route('admin.tareas.index'));
        $this->actingAs($this->persona(Rol::ADMINISTRADOR))->get(route('inicio'))
            ->assertSee(route('admin.tareas.index'))->assertDontSee(route('mi-trabajo.index'));
    }
}
