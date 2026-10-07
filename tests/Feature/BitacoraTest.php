<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private User $administrador;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 08:30:15', 'America/Bogota'));

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = $this->persona(Rol::PROPIETARIO, 'Dueña');
        $this->administrador = $this->persona(Rol::ADMINISTRADOR, 'Ana Admin');
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 1000, 'peso_actual_g' => 200, 'biomasa_kg' => 200]);
    }

    private function persona(string|array $roles, string $nombre = 'Persona'): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->finca->id, 'name' => $nombre]))->asignarRoles((array) $roles);
    }

    private function unicoRegistro(): Bitacora
    {
        return Bitacora::query()->sole();
    }

    public function test_la_racion_del_trabajador_queda_en_la_bitacora_con_su_rol_y_la_hora_de_la_finca(): void
    {
        $trabajador = $this->persona([Rol::TRABAJADOR, Rol::CELADOR], 'Juan');
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 100]);

        $this->actingAs($trabajador)->post(route('admin.raciones.store', $this->lago), ['fecha' => '2026-10-15', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 12.5])
            ->assertSessionHasNoErrors();

        $registro = $this->unicoRegistro();
        $this->assertSame([$trabajador->id, Rol::TRABAJADOR, 'racion', $this->lago->id], [$registro->user_id, $registro->rol, $registro->accion, $registro->lago_id]);
        $this->assertSame('2026-10-15 08:30:15', $registro->momentoLocal()->format('Y-m-d H:i:s'));
        $this->assertSame('America/Bogota', $registro->zona_horaria);
        $this->assertSame('12,5 kg de Engorde (fecha 15/10/2026)', $registro->textoDetalle());
    }

    public function test_mortalidad_muestreo_traslado_y_cosecha_dejan_su_registro(): void
    {
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);

        $this->actingAs($this->administrador)->post(route('admin.mortalidades.store', $this->siembra), ['fecha' => '2026-10-15', 'cantidad' => 7])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.muestreos.store', $this->siembra), ['fecha' => '2026-10-15', 'peces_pesados' => 10, 'peso_total_g' => 2500])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.traslados.store', $this->siembra), ['lago_destino_id' => $otro->id, 'fecha' => '2026-10-15', 'cantidad' => 100, 'peso_promedio_g' => 250])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.cosechas.store'), ['siembra_id' => $this->siembra->id, 'destino' => 'Plaza de mercado', 'fecha' => '2026-10-15', 'peces' => 50, 'kg' => 12.5])->assertSessionHasNoErrors();

        $registros = Bitacora::query()->orderBy('id')->get();
        $this->assertSame(['mortalidad', 'muestreo', 'traslado', 'cosecha'], $registros->pluck('accion')->all());
        $this->assertSame([Rol::ADMINISTRADOR], $registros->pluck('rol')->unique()->values()->all());
        $this->assertSame([$this->lago->id], $registros->pluck('lago_id')->unique()->values()->all());
        $this->assertSame('100 peces de Mojarra roja hacia L02 (fecha 15/10/2026)', $registros[2]->textoDetalle());
    }

    public function test_la_venta_deja_su_registro(): void
    {
        $cosecha = Cosecha::create(['siembra_id' => $this->siembra->id, 'lago_id' => $this->lago->id, 'user_id' => $this->propietario->id,
            'fecha' => '2026-10-14', 'peces' => 100, 'kg' => 30, 'peso_promedio_g' => 300]);

        $this->actingAs($this->administrador)->post(route('admin.ventas.store'), [
            'cosecha_id' => $cosecha->id, 'cliente' => 'Doña Rosa', 'fecha' => '2026-10-15', 'kg' => 10, 'precio_kg' => 12000, 'forma_pago' => 'contado',
        ])->assertSessionHasNoErrors();

        $registro = $this->unicoRegistro();
        $this->assertSame('venta', $registro->accion);
        $this->assertSame('10 kg a Doña Rosa por $ 120.000, De contado (fecha 15/10/2026)', $registro->textoDetalle());
    }

    public function test_la_ronda_y_la_novedad_del_celador_quedan_registradas(): void
    {
        $celador = $this->persona([Rol::TRABAJADOR, Rol::CELADOR], 'Pedro');
        $this->ponerEnTurno($celador, Rol::CELADOR);

        $this->actingAs($celador)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])->assertSessionHasNoErrors();
        $this->actingAs($celador)->post(route('noche.rondas.store'), ['tipo' => 'intrusion', 'lago_id' => $this->lago->id, 'descripcion' => 'Malla rota'])->assertSessionHasNoErrors();

        $registros = Bitacora::query()->orderBy('id')->get();
        $this->assertSame(['ronda', 'novedad'], $registros->pluck('accion')->all());
        $this->assertSame([Rol::CELADOR, Rol::CELADOR], $registros->pluck('rol')->all());
        $this->assertNull($registros[0]->lago_id);
        $this->assertSame($this->lago->id, $registros[1]->lago_id);
        $this->assertSame('Intrusión o robo: Malla rota', $registros[1]->textoDetalle());
    }

    public function test_una_accion_rechazada_no_deja_registro(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.mortalidades.store', $this->siembra), ['fecha' => '2026-10-15', 'cantidad' => 5000])
            ->assertSessionHasErrors('cantidad');
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 1]);
        $this->actingAs($this->administrador)->post(route('admin.raciones.store', $this->lago), ['fecha' => '2026-10-15', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 10])
            ->assertSessionHasErrors('cantidad_kg');

        $this->assertSame(0, Bitacora::query()->count());
    }

    public function test_no_se_puede_editar_ni_borrar_desde_el_codigo(): void
    {
        $registro = Bitacora::registrar($this->propietario, 'racion', $this->lago, [Rol::PROPIETARIO], ['kg' => 1.0, 'concentrado' => 'Engorde', 'fecha' => '2026-10-15']);

        foreach ([fn () => $registro->update(['accion' => 'venta']), fn () => $registro->delete()] as $intento) {
            try {
                $intento();
                $this->fail('Se pudo modificar la bitácora');
            } catch (LogicException $e) {
                $this->assertSame(__('bitacora.solo_insercion'), $e->getMessage());
            }
        }

        $this->assertSame('racion', $registro->fresh()->accion);
    }

    public function test_la_base_de_datos_rechaza_update_delete_y_truncate(): void
    {
        $registro = Bitacora::registrar($this->propietario, 'racion', $this->lago, [Rol::PROPIETARIO], ['kg' => 1.0, 'concentrado' => 'Engorde', 'fecha' => '2026-10-15']);

        foreach ([
            fn () => DB::table('bitacora')->where('id', $registro->id)->update(['accion' => 'venta']),
            fn () => DB::table('bitacora')->where('id', $registro->id)->delete(),
            fn () => DB::statement('TRUNCATE bitacora'),
            fn () => Bitacora::query()->whereKey($registro->id)->update(['accion' => 'venta']),  // update masivo (sin eventos de modelo)
        ] as $intento) {
            DB::beginTransaction();  // cada intento falla dentro de su propio punto de guardado
            try {
                $intento();
                $this->fail('La base permitió modificar la bitácora');
            } catch (QueryException $e) {
                $this->assertMatchesRegularExpression('/solo inserción|permission denied|permiso denegado/u', $e->getMessage());
            } finally {
                DB::rollBack();
            }
        }

        $this->assertSame('racion', DB::table('bitacora')->where('id', $registro->id)->value('accion'));
    }

    public function test_borrar_la_finca_o_el_usuario_no_puede_llevarse_la_bitacora(): void
    {
        Bitacora::registrar($this->propietario, 'racion', $this->lago, [Rol::PROPIETARIO], ['kg' => 1.0, 'concentrado' => 'Engorde', 'fecha' => '2026-10-15']);

        foreach ([fn () => DB::table('users')->where('id', $this->propietario->id)->delete(), fn () => DB::table('tenants')->where('id', $this->finca->id)->delete()] as $intento) {
            DB::beginTransaction();
            try {
                $intento();
                $this->fail('Se borró un dueño de registros de la bitácora');
            } catch (QueryException) {
                $this->addToAssertionCount(1);
            } finally {
                DB::rollBack();
            }
        }

        $this->assertSame(1, DB::table('bitacora')->count());
    }

    public function test_el_propietario_filtra_por_persona_rol_fecha_y_lago(): void
    {
        $juan = $this->persona(Rol::TRABAJADOR, 'Juan Pérez');
        $ana = $this->persona(Rol::ADMINISTRADOR, 'Ana Ruiz');
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);

        Bitacora::registrar($juan, 'racion', $this->lago, [Rol::TRABAJADOR], ['kg' => 5.0, 'concentrado' => 'Engorde', 'fecha' => '2026-10-15']);
        $this->travelTo(Carbon::parse('2026-10-16 23:50', 'America/Bogota')); // en UTC ya es el 17
        Bitacora::registrar($ana, 'muestreo', $otro, [Rol::ADMINISTRADOR], ['especie' => 'Mojarra roja', 'gramos' => 250.0, 'peces' => 10, 'fecha' => '2026-10-16']);

        $ver = fn (array $filtros) => $this->actingAs($this->propietario)->get(route('admin.bitacora.index', $filtros))->assertOk();

        $ver([])->assertSee('5 kg de Engorde')->assertSee('promedio 250 g');
        $ver(['user_id' => $juan->id])->assertSee('5 kg de Engorde')->assertDontSee('promedio 250 g');
        $ver(['rol' => Rol::ADMINISTRADOR])->assertDontSee('5 kg de Engorde')->assertSee('promedio 250 g');
        $ver(['lago_id' => $otro->id])->assertDontSee('5 kg de Engorde')->assertSee('promedio 250 g');
        // Los días son de la finca: el 16 a las 23:50 en Bogotá cuenta como 16
        $ver(['desde' => '2026-10-16', 'hasta' => '2026-10-16'])->assertDontSee('5 kg de Engorde')->assertSee('promedio 250 g')->assertSee('16/10/2026 23:50:00');
        $ver(['desde' => '2026-10-15', 'hasta' => '2026-10-15'])->assertSee('5 kg de Engorde')->assertDontSee('promedio 250 g');
    }

    public function test_solo_el_propietario_ve_la_bitacora_y_solo_la_de_su_finca(): void
    {
        foreach ([Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $this->actingAs($this->persona($rol))->get(route('admin.bitacora.index'))->assertForbidden();
        }

        $otraFinca = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otraFinca->id);
        $ajeno = tap(User::factory()->create(['tenant_id' => $otraFinca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $lagoAjeno = Lago::create(['nombre' => 'Ajeno', 'tipo' => 'tierra']);
        Bitacora::registrar($ajeno, 'racion', $lagoAjeno, [Rol::PROPIETARIO], ['kg' => 99.0, 'concentrado' => 'Secreto', 'fecha' => '2026-10-15']);
        app(TenantActual::class)->establecer($this->finca->id);

        $this->actingAs($this->propietario)->get(route('admin.bitacora.index'))->assertOk()->assertDontSee('Secreto');
        $this->actingAs($this->propietario)->get(route('admin.bitacora.index', ['user_id' => $ajeno->id]))->assertSessionHasErrors('user_id');
    }
}
