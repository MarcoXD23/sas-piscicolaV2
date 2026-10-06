<?php

namespace Tests\Feature;

use App\Models\Aireacion;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeguridadNocheTest extends TestCase
{
    use RefreshDatabase;

    private User $celador;

    private User $propietario;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 02:30', 'America/Bogota'));

        $tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($tenant->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $tenant->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->celador = tap(User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Celador Uno']))->asignarRoles([Rol::CELADOR]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $this->lago->id, 'especie' => 'mojarra roja', 'fecha' => now()->subDays(30),
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 50, 'biomasa_kg' => 50]);
    }

    public function test_el_celador_en_turno_registra_una_ronda_ligada_a_su_turno(): void
    {
        $turno = $this->ponerEnTurno($this->celador, Rol::CELADOR);

        $this->actingAs($this->celador)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])
            ->assertSessionHasNoErrors()->assertRedirect(route('noche.index'));

        $ronda = Ronda::sole();
        $this->assertSame($turno->id, $ronda->turno_id);
        $this->assertSame('2026-10-08 02:30', $ronda->registrada_at->format('Y-m-d H:i'));
        $this->assertFalse($ronda->esNovedad());
    }

    public function test_una_novedad_exige_descripcion_y_aparece_en_el_inicio_del_propietario(): void
    {
        $this->ponerEnTurno($this->celador, Rol::CELADOR);

        $this->actingAs($this->celador)->post(route('noche.rondas.store'), ['tipo' => 'depredador'])->assertInvalid(['descripcion']);
        $this->actingAs($this->celador)->post(route('noche.rondas.store'), ['tipo' => 'inventado', 'descripcion' => 'x'])->assertInvalid(['tipo']);

        $this->actingAs($this->celador)->post(route('noche.rondas.store'), [
            'tipo' => 'depredador', 'lago_id' => $this->lago->id, 'descripcion' => 'Garzas en la orilla norte',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->propietario)->get(route('inicio'))
            ->assertSee('Novedades de seguridad')
            ->assertSee('Garzas en la orilla norte');

        // Pasadas 24 horas ya no se muestra
        $this->travelTo(now()->addHours(25));
        $this->actingAs($this->propietario)->get(route('inicio'))->assertDontSee('Garzas en la orilla norte');
    }

    public function test_una_ronda_sin_novedad_no_alerta_al_propietario(): void
    {
        $this->ponerEnTurno($this->celador, Rol::CELADOR);
        $this->actingAs($this->celador)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD]);

        $this->actingAs($this->propietario)->get(route('inicio'))->assertDontSee('Novedades de seguridad');
    }

    public function test_fuera_de_turno_el_celador_consulta_pero_no_registra(): void
    {
        $this->actingAs($this->celador)->get(route('noche.index'))->assertOk()->assertSee('Estás fuera de tu turno');

        $this->actingAs($this->celador)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])->assertForbidden();
        $this->actingAs($this->celador)->post(route('noche.aireador.encender', $this->lago))->assertForbidden();
        $this->actingAs($this->celador)->post(route('noche.mediciones.store', $this->lago), ['oxigeno_mg_l' => 5])->assertForbidden();

        $this->assertSame(0, Ronda::count() + Aireacion::count() + MedicionAgua::count());
    }

    public function test_un_turno_de_trabajador_no_habilita_tareas_de_celador(): void
    {
        $persona = tap(User::factory()->create(['tenant_id' => $this->celador->tenant_id]))->asignarRoles([Rol::TRABAJADOR, Rol::CELADOR]);
        $this->ponerEnTurno($persona, Rol::TRABAJADOR);

        $this->actingAs($persona)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])->assertForbidden();
    }

    public function test_el_trabajador_no_entra_a_seguridad_y_noche(): void
    {
        $trabajador = tap(User::factory()->create(['tenant_id' => $this->celador->tenant_id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);

        $this->actingAs($trabajador)->get(route('noche.index'))->assertForbidden();
    }

    public function test_encender_y_apagar_el_aireador_deja_el_periodo_registrado(): void
    {
        $this->ponerEnTurno($this->celador, Rol::CELADOR);

        $this->actingAs($this->celador)->post(route('noche.aireador.encender', $this->lago), ['motivo' => 'Oxígeno bajo'])
            ->assertSessionHasNoErrors();
        // No se puede encender dos veces
        $this->actingAs($this->celador)->post(route('noche.aireador.encender', $this->lago))->assertSessionHasErrors('aireador');

        $aireacion = Aireacion::sole();
        $this->assertTrue($aireacion->estaEncendido());

        $this->travelTo(now()->addHours(2));
        $this->actingAs($this->celador)->post(route('noche.aireador.apagar', $aireacion))->assertSessionHasNoErrors();
        $this->actingAs($this->celador)->post(route('noche.aireador.apagar', $aireacion))->assertSessionHasErrors('aireador');

        $aireacion->refresh();
        $this->assertSame('04:30', $aireacion->apagado_at->format('H:i'));
        $this->assertSame($this->celador->id, $aireacion->apagado_por);
        $this->assertNull($this->lago->aireacionActiva()->first());
    }

    public function test_la_base_de_datos_impide_dos_encendidos_abiertos_en_un_lago(): void
    {
        Aireacion::create(['lago_id' => $this->lago->id, 'user_id' => $this->celador->id, 'encendido_at' => now()]);

        $this->expectException(QueryException::class);
        Aireacion::create(['lago_id' => $this->lago->id, 'user_id' => $this->celador->id, 'encendido_at' => now()]);
    }

    public function test_la_medicion_nocturna_usa_la_hora_actual_y_alerta_el_oxigeno_bajo(): void
    {
        $this->ponerEnTurno($this->celador, Rol::CELADOR);

        $this->actingAs($this->celador)->from(route('noche.index'))
            ->post(route('noche.mediciones.store', $this->lago), ['oxigeno_mg_l' => 2.8])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('noche.index'));

        $medicion = MedicionAgua::sole();
        $this->assertSame('2026-10-08 02:30', $medicion->medido_at->format('Y-m-d H:i'));
        $this->assertSame($this->celador->id, $medicion->user_id);

        $this->actingAs($this->celador)->get(route('noche.index'))
            ->assertSee('Oxígeno de 2,8 mg/L por debajo del mínimo')
            ->assertSee('Enciende el aireador y vuelve a medir.');

        // Queda en el historial del lago (un solo concepto de medición de agua)
        $this->actingAs($this->propietario)->get(route('admin.lagos.show', $this->lago))->assertSee('oxígeno 2,80 mg/L');
    }

    public function test_la_bitacora_muestra_lo_que_paso_en_la_noche(): void
    {
        $this->ponerEnTurno($this->celador, Rol::CELADOR);
        $this->actingAs($this->celador)->post(route('noche.rondas.store'), ['tipo' => 'energia', 'lago_id' => $this->lago->id, 'descripcion' => 'Se fue la luz 10 minutos']);
        $this->actingAs($this->celador)->post(route('noche.aireador.encender', $this->lago));

        $this->actingAs($this->propietario)->get(route('noche.index'))
            ->assertOk()
            ->assertSee('Corte de energía · L01', false)
            ->assertDontSee('Corte de energía · L01 ·', false)
            ->assertSee('Se fue la luz 10 minutos · Celador Uno')
            ->assertSee('Aireador encendido · L01');
    }
}
