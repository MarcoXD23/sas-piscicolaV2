<?php

namespace Tests\Feature;

use App\Models\EventoAgenda;
use App\Models\Lago;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\Tratamiento;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 10:00'));

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::PROPIETARIO]);
    }

    private function evento(string $fecha, int $avisoDias = 0, array $datos = []): EventoAgenda
    {
        return EventoAgenda::create($datos + ['creado_por' => $this->propietario->id, 'titulo' => "Evento {$fecha}", 'tipo' => 'compra',
            'inicia_at' => Carbon::parse($fecha), 'todo_el_dia' => true, 'recordar_dias_antes' => $avisoDias]);
    }

    private function agendar(array $datos)
    {
        return $this->actingAs($this->propietario)->post(route('admin.agenda.store'), $datos + [
            'titulo' => 'Cosecha L02', 'tipo' => 'cosecha', 'fecha' => '2026-10-15', 'recordar_dias_antes' => 2,
        ]);
    }

    public function test_la_agenda_es_exclusiva_del_propietario(): void
    {
        foreach ([Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([$rol]);
            $this->actingAs($persona)->get(route('admin.agenda.index'))->assertForbidden();
            $this->actingAs($persona)->post(route('admin.agenda.store'), [])->assertForbidden();
        }

        $this->actingAs($this->propietario)->get(route('admin.agenda.index'))->assertOk();
    }

    public function test_agenda_eventos_de_todo_el_dia_y_con_horario(): void
    {
        $this->agendar([])->assertSessionHasNoErrors()->assertRedirect(route('admin.agenda.index', ['mes' => '2026-10']));
        $this->agendar(['titulo' => 'Visita técnica', 'tipo' => 'visita', 'hora' => '08:00', 'hora_fin' => '10:30'])->assertSessionHasNoErrors();

        [$todoElDia, $visita] = EventoAgenda::orderBy('id')->get();
        $this->assertTrue($todoElDia->todo_el_dia);
        $this->assertSame('2026-10-15 00:00', $todoElDia->inicia_at->format('Y-m-d H:i'));
        $this->assertFalse($visita->todo_el_dia);
        $this->assertSame('2026-10-15 08:00', $visita->inicia_at->format('Y-m-d H:i'));
        $this->assertSame('10:30', $visita->termina_at->format('H:i'));
    }

    public function test_valida_horas_tipo_y_lago(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajeno = Lago::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'nombre' => 'X1', 'tipo' => 'tierra']);

        $this->agendar(['hora' => '10:00', 'hora_fin' => '09:00'])->assertInvalid(['hora_fin']);
        $this->agendar(['hora_fin' => '09:00'])->assertInvalid(['hora']);
        $this->agendar(['tipo' => 'fiesta'])->assertInvalid(['tipo']);
        $this->agendar(['lago_id' => $ajeno->id])->assertInvalid(['lago_id']);
        $this->agendar(['recordar_dias_antes' => 90])->assertInvalid(['recordar_dias_antes']);

        $this->assertSame(0, EventoAgenda::count());
    }

    public function test_recordatorios_segun_los_dias_de_aviso_y_vencidos(): void
    {
        $dentroDelAviso = $this->evento('2026-10-12', 7);   // aviso desde el 5
        $fueraDelAviso = $this->evento('2026-10-20', 3);    // aviso desde el 17
        $hoy = $this->evento('2026-10-07 15:00', 0);
        $vencido = $this->evento('2026-10-05', 0);
        $hecho = $this->evento('2026-10-06', 0, ['completado_at' => now()]);

        $ids = EventoAgenda::paraRecordar()->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$dentroDelAviso->id, $hoy->id, $vencido->id], $ids);
        $this->assertNotContains($fueraDelAviso->id, $ids);
        $this->assertNotContains($hecho->id, $ids);

        $this->actingAs($this->propietario)->get(route('inicio'))
            ->assertSee('Agenda: no olvides')
            ->assertSee('Evento 2026-10-12')
            ->assertSee('Vencido 05/10')
            ->assertDontSee('Evento 2026-10-20');
    }

    public function test_marcar_hecho_y_no_eliminar_lo_hecho(): void
    {
        $evento = $this->evento('2026-10-10');

        $this->actingAs($this->propietario)->post(route('admin.agenda.completar', $evento))->assertSessionHasNoErrors();
        $this->assertNotNull($evento->fresh()->completado_at);

        $this->actingAs($this->propietario)->delete(route('admin.agenda.destroy', $evento))->assertSessionHasErrors('evento');
        $this->assertModelExists($evento);

        $pendiente = $this->evento('2026-10-11');
        $this->actingAs($this->propietario)->delete(route('admin.agenda.destroy', $pendiente))->assertSessionHasNoErrors();
        $this->assertModelMissing($pendiente);
    }

    public function test_el_mes_muestra_eventos_retiros_sanitarios_y_permisos(): void
    {
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->evento('2026-10-15', 0, ['titulo' => 'Compra de concentrado']);
        Tratamiento::create(['lago_id' => $lago->id, 'user_id' => $this->propietario->id, 'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-05', 'motivo' => 'M', 'producto' => 'P', 'dosis' => 'D', 'via' => 'baño', 'dias_retiro' => 10]);
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Trabajador Uno']))->asignarRoles([Rol::TRABAJADOR]);
        Permiso::create(['user_id' => $trabajador->id, 'fecha_inicio' => '2026-10-20', 'fecha_fin' => '2026-10-21', 'motivo' => 'X', 'estado' => 'aprobado']);
        Permiso::create(['user_id' => $trabajador->id, 'fecha_inicio' => '2026-10-25', 'fecha_fin' => '2026-10-25', 'motivo' => 'X', 'estado' => 'pendiente']);

        $respuesta = $this->actingAs($this->propietario)->get(route('admin.agenda.index', ['mes' => '2026-10']))->assertOk()
            ->assertSee('Compra de concentrado')
            ->assertSee('Fin del retiro sanitario · L01');

        $porDia = $respuesta->viewData('porDia');
        $this->assertTrue($porDia->has('2026-10-15'));
        $this->assertSame('retiro', $porDia['2026-10-15']->firstWhere('color', 'retiro')['color']);
        $this->assertTrue($porDia->has('2026-10-20'));
        $this->assertTrue($porDia->has('2026-10-21'));
        $this->assertFalse($porDia->has('2026-10-25')); // el permiso pendiente no aparece
    }

    public function test_editar_un_evento_cambia_fecha_horario_y_datos(): void
    {
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $evento = $this->evento('2026-10-15', 2);

        $this->actingAs($this->propietario)->put(route('admin.agenda.update', $evento), [
            'titulo' => 'Cosecha L01', 'tipo' => 'cosecha', 'fecha' => '2026-11-03', 'hora' => '05:30', 'hora_fin' => '09:00',
            'lago_id' => $lago->id, 'recordar_dias_antes' => 5, 'descripcion' => 'Llevar hielo',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.agenda.index', ['mes' => '2026-11']));

        $evento->refresh();
        $this->assertSame('Cosecha L01', $evento->titulo);
        $this->assertSame('cosecha', $evento->tipo);
        $this->assertSame('2026-11-03 05:30', $evento->inicia_at->format('Y-m-d H:i'));
        $this->assertSame('09:00', $evento->termina_at->format('H:i'));
        $this->assertFalse($evento->todo_el_dia);
        $this->assertSame($lago->id, $evento->lago_id);
        $this->assertSame(5, $evento->recordar_dias_antes);
        $this->assertSame($this->propietario->id, $evento->creado_por);

        // Quitar la hora lo vuelve un evento de todo el día
        $this->actingAs($this->propietario)->put(route('admin.agenda.update', $evento), [
            'titulo' => 'Cosecha L01', 'tipo' => 'cosecha', 'fecha' => '2026-11-03', 'recordar_dias_antes' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertTrue($evento->fresh()->todo_el_dia);
        $this->assertNull($evento->fresh()->termina_at);
    }

    public function test_editar_usa_las_mismas_validaciones_y_no_aplica_a_eventos_hechos(): void
    {
        $evento = $this->evento('2026-10-15');
        $base = ['titulo' => 'X', 'tipo' => 'compra', 'fecha' => '2026-10-16', 'recordar_dias_antes' => 0];

        $this->actingAs($this->propietario)->put(route('admin.agenda.update', $evento), ['hora' => '10:00', 'hora_fin' => '09:00'] + $base)
            ->assertInvalid(['hora_fin']);
        $this->actingAs($this->propietario)->put(route('admin.agenda.update', $evento), ['tipo' => 'fiesta'] + $base)
            ->assertInvalid(['tipo']);
        $this->assertSame('Evento 2026-10-15', $evento->fresh()->titulo);

        $hecho = $this->evento('2026-10-05', 0, ['completado_at' => now()]);
        $this->actingAs($this->propietario)->put(route('admin.agenda.update', $hecho), $base)->assertSessionHasErrors('evento');
        $this->assertSame('Evento 2026-10-05', $hecho->fresh()->titulo);
    }

    public function test_solo_el_propietario_edita_y_la_ventana_de_edicion_viene_llena(): void
    {
        $evento = $this->evento('2026-10-15', 3, ['titulo' => 'Compra de alevinos']);
        $administrador = tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([Rol::ADMINISTRADOR]);

        $this->actingAs($administrador)->put(route('admin.agenda.update', $evento), [])->assertForbidden();

        $this->actingAs($this->propietario)->get(route('admin.agenda.index', ['mes' => '2026-10']))
            ->assertOk()
            ->assertSee('id="editar-evento-'.$evento->id.'"', false)
            ->assertSee('value="Compra de alevinos"', false);
    }

    public function test_un_mes_invalido_muestra_el_mes_actual(): void
    {
        $this->actingAs($this->propietario)->get(route('admin.agenda.index', ['mes' => '2026-13']))
            ->assertOk()
            ->assertViewHas('mes', fn ($mes) => $mes->format('Y-m') === '2026-10');
    }
}
