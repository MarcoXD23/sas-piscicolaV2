<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\Turno;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TurnosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();

        // Miércoles 7 de octubre de 2026, 10:00 (America/Bogota)
        $this->travelTo(Carbon::parse('2026-10-07 10:00', 'America/Bogota'));

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->propietario = $this->persona(Rol::PROPIETARIO);
    }

    private function persona(string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles($roles);
    }

    private function programar(User $persona, array $datos = [])
    {
        return $this->actingAs($this->propietario)->post(route('admin.turnos.store'), $datos + [
            'user_id' => $persona->id,
            'rol' => Rol::TRABAJADOR,
            'desde' => '2026-10-08',
            'hasta' => '2026-10-14',
            'dias' => [1, 2, 3, 4, 5, 6, 7],
            'hora_inicio' => '06:00',
            'hora_fin' => '14:00',
        ]);
    }

    public function test_propietario_programa_turnos_rotativos_por_dias_de_la_semana(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);

        // Del jueves 8 al miércoles 14, solo lunes, miércoles y viernes → 9, 12 y 14 de octubre
        $this->programar($trabajador, ['dias' => [1, 3, 5]])->assertSessionHasNoErrors();

        $turnos = Turno::orderBy('inicia_at')->get();
        $this->assertSame(['2026-10-09 06:00', '2026-10-12 06:00', '2026-10-14 06:00'],
            $turnos->map(fn ($turno) => $turno->inicia_at->format('Y-m-d H:i'))->all());
        $this->assertSame('2026-10-09 14:00', $turnos->first()->termina_at->format('Y-m-d H:i'));
        $this->assertSame($this->propietario->id, $turnos->first()->asignado_por);
        $this->assertSame($this->tenant->id, $turnos->first()->tenant_id);
    }

    public function test_turno_nocturno_termina_al_dia_siguiente(): void
    {
        $celador = $this->persona(Rol::CELADOR);

        $this->programar($celador, [
            'rol' => Rol::CELADOR, 'desde' => '2026-10-08', 'hasta' => '2026-10-08', 'dias' => [4],
            'hora_inicio' => '18:00', 'hora_fin' => '06:00',
        ])->assertSessionHasNoErrors();

        $turno = Turno::sole();
        $this->assertSame('2026-10-08 18:00', $turno->inicia_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-09 06:00', $turno->termina_at->format('Y-m-d H:i'));
    }

    public function test_no_asigna_un_turno_de_un_rol_que_la_persona_no_tiene(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);

        $this->programar($trabajador, ['rol' => Rol::CELADOR])->assertInvalid(['user_id']);
        $this->programar($trabajador, ['rol' => Rol::ADMINISTRADOR])->assertInvalid(['rol']);
        $this->assertSame(0, Turno::count());
    }

    public function test_no_crea_turnos_cruzados_y_no_deja_programacion_a_medias(): void
    {
        $persona = $this->persona(Rol::TRABAJADOR, Rol::CELADOR);
        $this->programar($persona, ['desde' => '2026-10-10', 'hasta' => '2026-10-10', 'dias' => [6]])->assertSessionHasNoErrors();

        // La semana completa choca el sábado 10 (aunque sea como celador): no se crea ninguno
        $this->programar($persona, ['rol' => Rol::CELADOR, 'hora_inicio' => '12:00', 'hora_fin' => '20:00'])
            ->assertInvalid(['dias']);

        $this->assertSame(1, Turno::count());
    }

    public function test_no_programa_en_fechas_pasadas(): void
    {
        $this->programar($this->persona(Rol::TRABAJADOR), ['desde' => '2026-10-01'])->assertInvalid(['desde']);
    }

    public function test_solo_el_propietario_gestiona_turnos(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $trabajador = $this->persona(Rol::TRABAJADOR);

        $this->actingAs($administrador)->get(route('admin.turnos.index'))->assertForbidden();
        $this->actingAs($trabajador)->post(route('admin.turnos.store'), [])->assertForbidden();
        $this->actingAs($this->propietario)->get(route('admin.turnos.index'))->assertOk();
    }

    public function test_no_programa_turnos_para_personas_de_otra_finca(): void
    {
        $otraFinca = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajeno = tap(User::factory()->create(['tenant_id' => $otraFinca->id]))->asignarRoles([Rol::TRABAJADOR]);

        $this->programar($ajeno)->assertInvalid(['user_id']);
        $this->assertSame(0, Turno::withoutGlobalScopes()->count());
    }

    public function test_solo_se_eliminan_turnos_que_no_han_empezado(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $enCurso = $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);
        $futuro = Turno::create(['user_id' => $trabajador->id, 'rol' => Rol::TRABAJADOR,
            'inicia_at' => now()->addDay(), 'termina_at' => now()->addDay()->addHours(8)]);

        $this->actingAs($this->propietario)->delete(route('admin.turnos.destroy', $enCurso))->assertSessionHasErrors('turno');
        $this->actingAs($this->propietario)->delete(route('admin.turnos.destroy', $futuro))->assertSessionHasNoErrors();

        $this->assertModelExists($enCurso);
        $this->assertModelMissing($futuro);
    }

    public function test_trabajador_solo_alimenta_y_registra_mortalidad_en_su_turno(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create(['lago_id' => $lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subDays(10),
            'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 100, 'peso_actual_g' => 5, 'biomasa_kg' => 0.5]);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 50]);

        $racion = ['fecha' => now()->toDateString(), 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 2];
        $mortalidad = ['fecha' => now()->toDateString(), 'cantidad' => 1];

        // Sin turno
        $this->actingAs($trabajador)->post(route('admin.raciones.store', $lago), $racion)->assertForbidden();
        $this->actingAs($trabajador)->post(route('admin.mortalidades.store', $siembra), $mortalidad)->assertForbidden();

        // Turno ya terminado tampoco sirve
        Turno::create(['user_id' => $trabajador->id, 'rol' => Rol::TRABAJADOR,
            'inicia_at' => now()->subHours(10), 'termina_at' => now()->subHours(2)]);
        $this->actingAs($trabajador)->post(route('admin.raciones.store', $lago), $racion)->assertForbidden();

        // Dentro del turno
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);
        $this->actingAs($trabajador)->post(route('admin.raciones.store', $lago), $racion)->assertSessionHasNoErrors();
        $this->actingAs($trabajador)->post(route('admin.mortalidades.store', $siembra), $mortalidad)->assertSessionHasNoErrors();

        $this->assertSame(99, $siembra->fresh()->peces_vivos);
    }

    public function test_un_turno_de_celador_no_habilita_tareas_de_trabajador(): void
    {
        $persona = $this->persona(Rol::TRABAJADOR, Rol::CELADOR);
        $this->ponerEnTurno($persona, Rol::CELADOR);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);

        $this->actingAs($persona)->post(route('admin.raciones.store', $lago), [])->assertForbidden();
        $this->assertFalse($persona->puedeTrabajarComo(Rol::TRABAJADOR));
        $this->assertTrue($persona->puedeTrabajarComo(Rol::CELADOR));
    }

    public function test_el_administrador_alimenta_sin_turno_y_nadie_salta_el_turno_del_celador(): void
    {
        // Alimentación y mortalidad: el administrador no depende de turnos
        $this->assertTrue($this->persona(Rol::ADMINISTRADOR)->puedeTrabajarComo(Rol::TRABAJADOR));
        $this->assertTrue($this->persona(Rol::PROPIETARIO, Rol::ADMINISTRADOR)->puedeTrabajarComo(Rol::TRABAJADOR));
        // El propietario sin rol de administrador no registra operación
        $this->assertFalse($this->propietario->puedeTrabajarComo(Rol::TRABAJADOR));
        // Seguridad & Noche: solo el celador en su turno (propietario y administrador consultan)
        $this->assertFalse($this->propietario->puedeTrabajarComo(Rol::CELADOR));
        $this->assertFalse($this->persona(Rol::ADMINISTRADOR)->puedeTrabajarComo(Rol::CELADOR));
        $this->assertFalse($this->persona(Rol::ADMINISTRADOR, Rol::CELADOR)->puedeTrabajarComo(Rol::CELADOR));
    }

    public function test_el_inicio_y_los_lagos_muestran_el_estado_del_turno(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);

        $this->actingAs($trabajador)->get(route('inicio'))->assertSee('No tienes turnos programados.');
        $this->actingAs($trabajador)->get(route('admin.lagos.index'))->assertSee('Estás fuera de tu turno');

        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $this->actingAs($trabajador)->get(route('inicio'))->assertSee('Estás en turno')->assertSee('hasta las 17:00');
        $this->actingAs($trabajador)->get(route('admin.lagos.index'))->assertDontSee('Estás fuera de tu turno');
    }
}
