<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Jornada;
use App\Models\JornadaLago;
use App\Models\Lago;
use App\Models\PesajeEntero;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tratamiento;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F6a: jornada de proceso. Hoy es 2026-10-15 en la finca (reloj fijo de TestCase).
 * L01 tiene dos siembras revueltas de cachama: S1 con 1.000 peces de 400 g (400 kg) y S2 con 500 de 200 g (100 kg).
 * Con 250 kg enteros, el reparto según la biomasa es 200 kg (500 peces) y 50 kg (250 peces).
 */
class JornadaProcesoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $administrador;

    private User $propietario;

    private Lago $lago;

    private Siembra $s1;

    private Siembra $s2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->tenant->id);
        $this->administrador = $this->persona(Rol::ADMINISTRADOR);
        $this->propietario = $this->persona(Rol::PROPIETARIO);
        $this->encender();

        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->s1 = $this->sembrar($this->lago, 1000, 400, '2026-04-01');
        $this->s2 = $this->sembrar($this->lago, 500, 200, '2026-07-01');
    }

    private function persona(string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->tenant->id]))->asignarRoles([$rol]);
    }

    private function encender(string $valor = CatalogoAjustes::ENCENDIDA): void
    {
        AjusteFinca::query()->updateOrCreate(['clave' => CatalogoAjustes::PROCESO_JORNADAS], ['valor' => $valor]);
    }

    private function sembrar(Lago $lago, int $peces, float $gramos, string $fecha, string $especie = 'cachama blanca'): Siembra
    {
        return Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie($especie), 'fecha' => $fecha,
            'cantidad_alevinos' => $peces, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces, 'peso_actual_g' => $gramos,
            'biomasa_kg' => $peces * $gramos / 1000]);
    }

    private function jornada(string $fecha = '2026-10-15', ?array $lagos = null): Jornada
    {
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => $fecha, 'lagos' => $lagos ?? [$this->lago->id]])
            ->assertSessionHasNoErrors();

        return Jornada::query()->latest('id')->firstOrFail();
    }

    private function pesarTotal(Jornada $jornada, float $kg, ?Lago $lago = null, string $especie = 'cachama blanca')
    {
        $jornadaLago = $jornada->lagos()->where('lago_id', ($lago ?? $this->lago)->id)->firstOrFail();

        return $this->actingAs($this->administrador)->post(route('admin.jornadas.pesajes.store', $jornada), [
            'jornada_lago_id' => $jornadaLago->id, 'especie_id' => $this->especie($especie), 'kg' => $kg,
        ]);
    }

    private function cerrar(Jornada $jornada, array $lago = [], ?User $quien = null)
    {
        $jornadaLago = $jornada->lagos()->firstOrFail();

        return $this->actingAs($quien ?? $this->administrador)->post(route('admin.jornadas.cerrar.guardar', $jornada), [
            'lagos' => [$jornadaLago->id => $lago + ['resultado' => JornadaLago::QUEDO_PESCADO]],
        ]);
    }

    // --- Función apagada, menú y permisos ---

    public function test_con_la_funcion_apagada_las_rutas_dan_404_y_no_hay_menu(): void
    {
        $this->encender(CatalogoAjustes::APAGADA);

        $this->actingAs($this->administrador)->get(route('admin.jornadas.index'))->assertNotFound();
        $this->actingAs($this->administrador)->get(route('admin.jornadas.create'))->assertNotFound();
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-15', 'lagos' => [$this->lago->id]])->assertNotFound();
        $this->actingAs($this->propietario)->get(route('admin.jornadas.index'))->assertNotFound();
        $this->assertSame(0, Jornada::query()->count());

        $this->actingAs($this->administrador)->get(route('inicio'))->assertOk()->assertDontSee(route('admin.jornadas.index'), false);
    }

    public function test_por_defecto_la_funcion_esta_apagada(): void
    {
        AjusteFinca::query()->where('clave', CatalogoAjustes::PROCESO_JORNADAS)->delete();

        $this->actingAs($this->administrador)->get(route('admin.jornadas.index'))->assertNotFound();
    }

    public function test_con_la_funcion_encendida_aparece_en_el_menu(): void
    {
        $this->actingAs($this->administrador)->get(route('inicio'))->assertOk()->assertSee(route('admin.jornadas.index'), false);
        $this->actingAs($this->propietario)->get(route('inicio'))->assertOk()->assertSee(route('admin.jornadas.index'), false);
    }

    public function test_el_propietario_consulta_pero_no_crea_ni_edita_ni_cierra(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();

        $this->actingAs($this->propietario)->get(route('admin.jornadas.index'))->assertOk()->assertSee('15/10/2026');
        $this->actingAs($this->propietario)->get(route('admin.jornadas.show', $jornada))->assertOk()
            ->assertDontSee(route('admin.jornadas.cerrar', $jornada), false);

        $this->actingAs($this->propietario)->get(route('admin.jornadas.create'))->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-16', 'lagos' => [$this->lago->id]])->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.jornadas.lagos.store', $jornada), ['lago_id' => $this->lago->id])->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.jornadas.pesajes.store', $jornada), ['kg' => 10])->assertForbidden();
        $this->actingAs($this->propietario)->delete(route('admin.jornadas.pesajes.destroy', [$jornada, PesajeEntero::query()->firstOrFail()]))->assertForbidden();
        $this->actingAs($this->propietario)->get(route('admin.jornadas.cerrar', $jornada))->assertForbidden();
        $this->cerrar($jornada, [], $this->propietario)->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.jornadas.cancelar', $jornada))->assertForbidden();

        $this->assertTrue($jornada->fresh()->estaAbierta());
        $this->assertSame(0, Cosecha::query()->count());
    }

    public function test_el_trabajador_y_el_celador_no_entran_a_jornadas(): void
    {
        $jornada = $this->jornada();

        foreach ([Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = $this->persona($rol);
            $this->actingAs($persona)->get(route('admin.jornadas.index'))->assertForbidden();
            $this->actingAs($persona)->get(route('admin.jornadas.show', $jornada))->assertForbidden();
            $this->actingAs($persona)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-16', 'lagos' => [$this->lago->id]])->assertForbidden();
            $this->actingAs($persona)->get(route('inicio'))->assertOk()->assertDontSee(route('admin.jornadas.index'), false);
        }
    }

    public function test_otra_finca_no_ve_ni_toca_una_jornada_ajena(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();
        $pesaje = PesajeEntero::query()->firstOrFail();

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($otra->id);
        AjusteFinca::query()->create(['clave' => CatalogoAjustes::PROCESO_JORNADAS, 'valor' => CatalogoAjustes::ENCENDIDA]);
        $ajeno = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->assertSame(0, Jornada::query()->count());

        $this->actingAs($ajeno)->get(route('admin.jornadas.show', $jornada))->assertNotFound();
        $this->actingAs($ajeno)->get(route('admin.jornadas.cerrar', $jornada))->assertNotFound();
        $this->actingAs($ajeno)->post(route('admin.jornadas.cerrar.guardar', $jornada), ['lagos' => []])->assertNotFound();
        $this->actingAs($ajeno)->post(route('admin.jornadas.cancelar', $jornada))->assertNotFound();
        $this->actingAs($ajeno)->post(route('admin.jornadas.lagos.store', $jornada), ['lago_id' => $this->lago->id])->assertNotFound();
        $this->actingAs($ajeno)->delete(route('admin.jornadas.pesajes.destroy', [$jornada, $pesaje]))->assertNotFound();
        $this->actingAs($ajeno)->get(route('admin.jornadas.index'))->assertOk()->assertDontSee('15/10/2026');

        app(TenantActual::class)->establecer($this->tenant->id);
        $this->assertTrue($jornada->fresh()->estaAbierta());
        $this->assertSame(1, PesajeEntero::query()->count());
    }

    // --- Programar ---

    public function test_programa_la_jornada_con_sus_lagos_y_queda_en_la_bitacora(): void
    {
        $jornada = $this->jornada('2026-10-16');

        $this->assertSame(Jornada::ABIERTA, $jornada->estado);
        $this->assertTrue($jornada->sin_alimentacion);
        $this->assertSame([$this->lago->id], $jornada->lagos()->pluck('lago_id')->all());
        $this->assertSame($this->tenant->id, $jornada->lagos()->first()->tenant_id);
        $this->assertSame(1, Bitacora::query()->where('accion', 'jornada_creada')->count());
    }

    public function test_sin_alimentacion_se_copia_del_ajuste(): void
    {
        AjusteFinca::query()->create(['clave' => CatalogoAjustes::PROCESO_SIN_ALIMENTACION, 'valor' => CatalogoAjustes::APAGADA]);

        $this->assertFalse($this->jornada()->sin_alimentacion);
    }

    public function test_una_sola_jornada_activa_por_fecha(): void
    {
        $primera = $this->jornada();

        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-15', 'lagos' => [$this->lago->id]])
            ->assertSessionHasErrors('fecha');
        $this->assertSame(1, Jornada::query()->count());

        // La base también lo impide (índice único parcial); la transacción deja seguir la prueba después del error
        try {
            DB::transaction(fn () => Jornada::query()->create(['fecha' => '2026-10-15', 'estado' => Jornada::ABIERTA, 'user_id' => $this->administrador->id]));
            $this->fail('La base dejó crear dos jornadas activas el mismo día.');
        } catch (QueryException) {
            $this->assertSame(1, Jornada::query()->count());
        }

        // Cancelada la primera, se puede programar otra ese día
        $this->actingAs($this->administrador)->post(route('admin.jornadas.cancelar', $primera))->assertSessionHasNoErrors();
        $this->jornada();
        $this->assertSame(2, Jornada::query()->count());
    }

    public function test_no_entra_un_lago_en_retiro_ni_uno_sin_peces(): void
    {
        Tratamiento::create(['lago_id' => $this->lago->id, 'user_id' => $this->administrador->id, 'fecha_inicio' => '2026-10-10',
            'fecha_fin' => '2026-10-12', 'motivo' => 'Parásitos', 'producto' => 'Producto X', 'dosis' => 'D', 'via' => 'baño', 'dias_retiro' => 5]);
        $vacio = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);

        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-15', 'lagos' => [$this->lago->id]])
            ->assertSessionHasErrors('lagos');
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-15', 'lagos' => [$vacio->id]])
            ->assertSessionHasErrors('lagos');
        $this->assertSame(0, Jornada::query()->count());
    }

    public function test_agrega_otro_lago_a_la_jornada_abierta(): void
    {
        $jornada = $this->jornada();
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $this->sembrar($otro, 800, 300, '2026-05-01');

        $this->actingAs($this->administrador)->post(route('admin.jornadas.lagos.store', $jornada), ['lago_id' => $otro->id])->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.jornadas.lagos.store', $jornada), ['lago_id' => $otro->id])->assertSessionHasErrors('lago_id');

        $this->assertSame([$this->lago->id, $otro->id], $jornada->lagos()->pluck('lago_id')->all());
        $this->assertSame(1, Bitacora::query()->where('accion', 'jornada_lago_nuevo')->count());
    }

    // --- Pesajes ---

    public function test_pesaje_con_pesadas_y_con_total(): void
    {
        $jornada = $this->jornada();
        $jornadaLago = $jornada->lagos()->firstOrFail();

        $this->actingAs($this->administrador)->post(route('admin.jornadas.pesajes.store', $jornada), [
            'jornada_lago_id' => $jornadaLago->id, 'especie_id' => $this->especie('cachama blanca'),
            'peso_canasta_kg' => 2, 'peso_canastilla_kg' => 1.5,
            'pesadas' => [['tipo' => 'canasta', 'cantidad' => 20, 'peso_pesado' => 655], ['tipo' => 'canastilla', 'cantidad' => 2, 'peso_pesado' => 53]],
        ])->assertSessionHasNoErrors();
        $this->pesarTotal($jornada, 100.5)->assertSessionHasNoErrors();

        // 655 − 40 = 615; 53 − 3 = 50; más 100,5 escritos
        $this->assertEqualsWithDelta(765.5, PesajeEntero::query()->sum('entero_kg'), 0.001);
        $this->assertSame(['canasta', 'canastilla', 'total'], PesajeEntero::query()->orderBy('id')->pluck('tipo')->all());
    }

    public function test_no_se_pesa_antes_del_dia_de_la_jornada_ni_una_especie_que_no_esta(): void
    {
        $manana = $this->jornada('2026-10-16');
        $this->pesarTotal($manana, 100)->assertSessionHasErrors('jornada');

        $hoy = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $this->sembrar($hoy, 100, 300, '2026-05-01', 'mojarra roja');
        $this->actingAs($this->administrador)->post(route('admin.jornadas.store'), ['fecha' => '2026-10-15', 'lagos' => [$hoy->id]])->assertSessionHasNoErrors();
        $this->pesarTotal(Jornada::query()->latest('id')->firstOrFail(), 50, $hoy, 'cachama blanca')->assertSessionHasErrors('especie_id');

        $this->assertSame(0, PesajeEntero::query()->count());
    }

    // --- Cierre ---

    public function test_cierre_quedo_pescado_reparte_segun_biomasa_y_crea_cosechas(): void
    {
        AvisoPesca::create(['lago_id' => $this->lago->id, 'siembra_id' => $this->s1->id, 'user_id' => $this->administrador->id,
            'estado' => AvisoPesca::PENDIENTE, 'reportado_at' => now()]);
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();

        $this->actingAs($this->administrador)->get(route('admin.jornadas.cerrar', $jornada))->assertOk()->assertSee('value="200"', false)->assertSee('value="50"', false);
        $this->cerrar($jornada)->assertSessionHasNoErrors()->assertRedirect(route('admin.jornadas.show', $jornada));

        $jornada->refresh();
        $this->assertSame(Jornada::CERRADA, $jornada->estado);
        $this->assertSame($this->administrador->id, $jornada->cerrada_por);

        $c1 = Cosecha::query()->where('siembra_id', $this->s1->id)->sole();
        $c2 = Cosecha::query()->where('siembra_id', $this->s2->id)->sole();
        $this->assertSame([200.0, 500, 400.0], [$c1->kg, $c1->peces, $c1->peso_promedio_g]);
        $this->assertSame([50.0, 250, 200.0], [$c2->kg, $c2->peces, $c2->peso_promedio_g]);
        $this->assertSame($jornada->id, $c1->jornada_id);
        $this->assertSame('2026-10-15', $c1->fecha->toDateString());
        // El destino no se guarda: sale de la jornada
        $this->assertNull($c1->destino);
        $this->assertSame('Jornada de proceso 15/10', $c1->textoDestino());

        // El peso promedio no cambia; quedan los peces menos lo cosechado
        $this->assertSame([500, 400.0, null], [$this->s1->fresh()->peces_vivos, (float) $this->s1->fresh()->peso_actual_g, $this->s1->fresh()->finalizada_at]);
        $this->assertSame(250, $this->s2->fresh()->peces_vivos);
        $this->assertEquals(200, (float) $this->s1->fresh()->biomasa_kg);

        $this->assertSame(JornadaLago::QUEDO_PESCADO, $jornada->lagos()->first()->resultado);
        $this->assertSame(AvisoPesca::ATENDIDO, AvisoPesca::query()->sole()->estado);
        $this->assertSame(1, Bitacora::query()->where('accion', 'jornada_cerrada')->count());
        $this->assertSame(1, Bitacora::query()->where('accion', 'jornada_lago_cerrado')->count());
        $this->assertSame(2, Bitacora::query()->where('accion', 'cosecha')->count());
    }

    public function test_el_administrador_ajusta_el_reparto_y_debe_sumar_el_total(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();

        $this->cerrar($jornada, ['kg' => [$this->s1->id => 150, $this->s2->id => 90]])->assertSessionHasErrors();
        $this->assertTrue($jornada->fresh()->estaAbierta());
        $this->assertSame(0, Cosecha::query()->count());

        $this->cerrar($jornada, ['kg' => [$this->s1->id => 150, $this->s2->id => 100]])->assertSessionHasNoErrors();
        $this->assertSame(375, Cosecha::query()->where('siembra_id', $this->s1->id)->sole()->peces);
        // 100 kg ÷ 200 g = 500 peces: la siembra S2 se cosechó completa y se cierra
        $this->assertSame(0, $this->s2->fresh()->peces_vivos);
        $this->assertNotNull($this->s2->fresh()->finalizada_at);
    }

    public function test_quedo_pescado_no_puede_sacar_mas_peces_de_los_que_hay(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();

        // 150 kg ÷ 200 g = 750 peces y S2 solo tiene 500
        $this->cerrar($jornada, ['kg' => [$this->s1->id => 100, $this->s2->id => 150]])->assertSessionHasErrors();
        $this->assertSame(0, Cosecha::query()->count());
        $this->assertSame(500, $this->s2->fresh()->peces_vivos);
    }

    public function test_quedo_pescado_con_los_kg_que_se_estima_que_quedan(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();

        // S1: quedan 500 peces según los registros; se estiman 100 kg ÷ 400 g = 250
        $this->cerrar($jornada, ['restante_kg' => [$this->s1->id => 100, $this->s2->id => '']])->assertSessionHasNoErrors();

        $this->assertSame(250, $this->s1->fresh()->peces_vivos);
        $this->assertSame(250, $this->s2->fresh()->peces_vivos);
        $fila = collect($jornada->lagos()->first()->cierre['siembras'])->firstWhere('siembra_id', $this->s1->id);
        $this->assertSame(250, $fila['diferencia']);

        // No puede quedar más de lo que había
        $otra = $this->sembrarOtraJornada();
        $this->cerrar($otra, ['restante_kg' => [$this->s1->id => 500]])->assertSessionHasErrors();
    }

    private function sembrarOtraJornada(): Jornada
    {
        $jornada = $this->jornada('2026-10-14');
        $this->pesarTotal($jornada, 10)->assertSessionHasNoErrors();

        return $jornada;
    }

    public function test_quedo_vacio_cierra_todas_las_siembras_y_registra_la_diferencia(): void
    {
        $otraEspecie = $this->sembrar($this->lago, 50, 300, '2026-06-01', 'mojarra roja');
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 450)->assertSessionHasNoErrors();

        $this->cerrar($jornada, ['resultado' => JornadaLago::VACIO])->assertSessionHasNoErrors();

        foreach ([$this->s1, $this->s2, $otraEspecie] as $siembra) {
            $this->assertSame(0, $siembra->fresh()->peces_vivos);
            $this->assertNotNull($siembra->fresh()->finalizada_at);
        }

        // 450 kg: S1 360 kg (900 peces calculados de 1.000) y S2 90 kg (450 de 500). La cosecha lleva todos los vivos
        $c1 = Cosecha::query()->where('siembra_id', $this->s1->id)->sole();
        $this->assertSame([360.0, 1000, 360.0], [$c1->kg, $c1->peces, $c1->peso_promedio_g]);
        $cierre = collect($jornada->lagos()->first()->cierre['siembras'])->keyBy('siembra_id');
        $this->assertSame(100, $cierre[$this->s1->id]['diferencia']);
        $this->assertSame(50, $cierre[$this->s2->id]['diferencia']);
        // La mojarra no se pesó: no tiene cosecha y todos sus peces son diferencia
        $this->assertSame(0, Cosecha::query()->where('siembra_id', $otraEspecie->id)->count());
        $this->assertSame(50, $cierre[$otraEspecie->id]['diferencia']);
        $this->assertSame(200, Bitacora::query()->where('accion', 'jornada_lago_cerrado')->sole()->detalle['diferencia']);
    }

    public function test_cerrar_dos_veces_no_duplica_cosechas(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();

        $this->cerrar($jornada)->assertSessionHasNoErrors();
        $this->cerrar($jornada)->assertSessionHasNoErrors()->assertSessionHas('estado', __('jornadas.ya_no_abierta'));

        $this->assertSame(2, Cosecha::query()->count());
        $this->assertSame(500, $this->s1->fresh()->peces_vivos);
        $this->assertSame(1, Bitacora::query()->where('accion', 'jornada_cerrada')->count());
    }

    public function test_al_cerrar_revisa_otra_vez_el_retiro_de_tratamiento(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();
        Tratamiento::create(['lago_id' => $this->lago->id, 'user_id' => $this->administrador->id, 'fecha_inicio' => '2026-10-14',
            'fecha_fin' => '2026-10-15', 'motivo' => 'Parásitos', 'producto' => 'Producto X', 'dosis' => 'D', 'via' => 'baño', 'dias_retiro' => 3]);

        $this->cerrar($jornada)->assertSessionHasErrors('jornada');
        $this->assertTrue($jornada->fresh()->estaAbierta());
        $this->assertSame(0, Cosecha::query()->count());
    }

    public function test_no_cierra_sin_pesajes_ni_sin_decir_como_quedo_el_lago(): void
    {
        $jornada = $this->jornada();
        $this->cerrar($jornada)->assertSessionHasErrors('jornada');

        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->post(route('admin.jornadas.cerrar.guardar', $jornada), [
            'lagos' => [$jornada->lagos()->first()->id => ['kg' => []]],
        ])->assertSessionHasErrors();
        $this->assertTrue($jornada->fresh()->estaAbierta());
    }

    public function test_una_jornada_cerrada_no_se_reabre_ni_se_modifica(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();
        $this->cerrar($jornada)->assertSessionHasNoErrors();
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $this->sembrar($otro, 800, 300, '2026-05-01');

        $this->pesarTotal($jornada, 10)->assertSessionHasErrors('jornada');
        $this->actingAs($this->administrador)->post(route('admin.jornadas.lagos.store', $jornada), ['lago_id' => $otro->id])->assertSessionHasErrors('jornada');
        $this->actingAs($this->administrador)->delete(route('admin.jornadas.pesajes.destroy', [$jornada, PesajeEntero::query()->firstOrFail()]))->assertSessionHasErrors('jornada');
        $this->actingAs($this->administrador)->post(route('admin.jornadas.cancelar', $jornada))->assertSessionHasErrors('jornada');

        $this->assertSame(Jornada::CERRADA, $jornada->fresh()->estado);
        $this->assertSame(1, PesajeEntero::query()->count());
    }

    public function test_cancelar_conserva_los_pesajes_y_queda_en_la_bitacora(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();

        $this->actingAs($this->administrador)->post(route('admin.jornadas.cancelar', $jornada))->assertSessionHasNoErrors();

        $this->assertSame(Jornada::CANCELADA, $jornada->fresh()->estado);
        $this->assertSame(1, PesajeEntero::query()->count());
        $this->assertSame(1, Bitacora::query()->where('accion', 'jornada_cancelada')->count());
        // Cancelada no se cierra: no crea cosechas
        $this->cerrar($jornada)->assertSessionHas('estado', __('jornadas.ya_no_abierta'));
        $this->assertSame(0, Cosecha::query()->count());
    }

    // --- Convivencia con la cosecha manual ---

    public function test_la_cosecha_de_una_jornada_avisa_y_enlaza_a_la_jornada(): void
    {
        $jornada = $this->jornada();
        $this->pesarTotal($jornada, 250)->assertSessionHasNoErrors();
        $this->cerrar($jornada)->assertSessionHasNoErrors();

        $this->actingAs($this->administrador)->get(route('admin.cosechas.show', Cosecha::query()->first()))->assertOk()
            ->assertSee(route('admin.jornadas.show', $jornada), false)
            ->assertSee('Jornada de proceso 15/10');
    }

    public function test_la_cosecha_manual_sigue_funcionando_con_las_jornadas_encendidas(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.cosechas.store'), [
            'siembra_id' => $this->s1->id, 'destino' => 'Restaurante El Río', 'fecha' => '2026-10-15', 'peces' => 100, 'kg' => 40,
        ])->assertSessionHasNoErrors();

        $cosecha = Cosecha::query()->sole();
        $this->assertNull($cosecha->jornada_id);
        $this->assertSame('Restaurante El Río', $cosecha->textoDestino());
        $this->assertSame(900, $this->s1->fresh()->peces_vivos);
    }
}
