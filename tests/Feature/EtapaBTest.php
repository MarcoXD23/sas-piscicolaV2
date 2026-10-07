<?php

namespace Tests\Feature;

use App\Models\AvisoPesca;
use App\Models\Bitacora;
use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\Muestreo;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Etapa B: muestreo con promedio, aviso «listo para pescar» y botón de nueva venta.
 */
class EtapaBTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private User $trabajador;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = $this->persona(Rol::ADMINISTRADOR);
        $this->trabajador = $this->persona(Rol::TRABAJADOR);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie' => 'mojarra roja', 'fecha' => '2026-09-01', 'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 1000, 'peso_actual_g' => 200, 'biomasa_kg' => 200]);
    }

    private function persona(string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles($roles);
    }

    private function muestrear(int $peces, float $pesoTotal): TestResponse
    {
        return $this->actingAs($this->administrador)->post(route('admin.muestreos.store', $this->siembra), ['fecha' => '2026-10-15', 'peces_pesados' => $peces, 'peso_total_g' => $pesoTotal]);
    }

    private function avisar(array $datos = []): TestResponse
    {
        return $this->actingAs($this->trabajador)->post(route('admin.avisos-pesca.store', $this->lago), $datos + ['siembra_id' => $this->siembra->id]);
    }

    // --- 1. Muestreo ---

    public function test_el_muestreo_calcula_el_promedio_muestra_la_diferencia_y_queda_en_el_historial(): void
    {
        $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertOk()
            ->assertSee('data-muestreo', false)->assertSee('data-promedio', false)
            ->assertSee(__('lagos.peso_anterior', ['gramos' => '200,00']));

        $this->muestrear(12, 3000)->assertSessionHas('estado', __('mensajes.muestreo.registrado_con_promedio', ['promedio' => '250,00', 'anterior' => '200,00', 'diferencia' => '+50,00']));
        $this->muestrear(10, 2401)->assertSessionHas('estado', __('mensajes.muestreo.registrado_con_promedio', ['promedio' => '240,10', 'anterior' => '250,00', 'diferencia' => '−9,90']));

        $this->assertSame([250.0, 240.1], Muestreo::query()->orderBy('id')->pluck('peso_promedio_g')->map(fn ($g) => (float) $g)->all());
        $siembra = $this->siembra->fresh();
        $this->assertSame(240.1, (float) $siembra->peso_actual_g);
        $this->assertSame(240.1, (float) $siembra->biomasa_kg);   // 1000 peces × 240,1 g
    }

    public function test_el_muestreo_nunca_divide_por_cero(): void
    {
        $this->muestrear(0, 3000)->assertSessionHasErrors('peces_pesados');
        $this->actingAs($this->administrador)->post(route('admin.muestreos.store', $this->siembra), ['fecha' => '2026-10-15', 'peso_total_g' => 3000])->assertSessionHasErrors('peces_pesados');
        $this->assertSame(0, Muestreo::count());
        $this->assertSame(200.0, (float) $this->siembra->fresh()->peso_actual_g);

        // El cálculo en vivo no divide con campos vacíos o en cero
        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('cantidad <= 0 || total <= 0', $js);
    }

    // --- 2. Aviso «listo para pescar» ---

    public function test_el_trabajador_avisa_solo_en_su_turno_y_sin_duplicados(): void
    {
        $this->actingAs($this->trabajador)->get(route('admin.lagos.index'))->assertDontSee(__('lagos.reportar_listo_pescar'));
        $this->avisar()->assertForbidden();

        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $this->actingAs($this->trabajador)->get(route('admin.lagos.index'))->assertSee(__('lagos.reportar_listo_pescar'));
        $this->avisar(['nota' => 'Ya tienen talla'])->assertSessionHasNoErrors();

        $aviso = AvisoPesca::sole();
        $this->assertSame([$this->lago->id, $this->siembra->id, $this->trabajador->id, AvisoPesca::PENDIENTE, 'Ya tienen talla'],
            [$aviso->lago_id, $aviso->siembra_id, $aviso->user_id, $aviso->estado, $aviso->nota]);

        // Segundo aviso para el mismo lago: no se repite
        $this->avisar()->assertSessionHasErrors(['nota' => __('mensajes.aviso_pesca.ya_hay_pendiente')]);
        $this->actingAs($this->trabajador)->get(route('admin.lagos.index'))->assertDontSee(__('lagos.reportar_listo_pescar'))->assertSee(__('lagos.aviso_pesca_listo', ['especie' => 'Mojarra roja']));
        $this->assertSame(1, AvisoPesca::count());

        // La base también lo impide
        $this->expectException(QueryException::class);
        DB::table('avisos_pesca')->insert(['tenant_id' => $this->finca->id, 'lago_id' => $this->lago->id, 'siembra_id' => $this->siembra->id,
            'user_id' => $this->trabajador->id, 'estado' => 'pendiente', 'reportado_at' => now()]);
    }

    public function test_solo_el_trabajador_reporta_y_no_de_siembras_finalizadas_ni_de_otro_lago(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.avisos-pesca.store', $this->lago), ['siembra_id' => $this->siembra->id])->assertForbidden();
        $this->actingAs($this->persona(Rol::PROPIETARIO))->post(route('admin.avisos-pesca.store', $this->lago), ['siembra_id' => $this->siembra->id])->assertForbidden();

        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $this->actingAs($this->trabajador)->post(route('admin.avisos-pesca.store', $otro), ['siembra_id' => $this->siembra->id])->assertSessionHasErrors('siembra_id');
        $this->siembra->forceFill(['finalizada_at' => now()])->save();
        $this->avisar()->assertSessionHasErrors('siembra_id');
        $this->assertSame(0, AvisoPesca::count());
    }

    public function test_el_administrador_lo_ve_y_lo_atiende_a_mano(): void
    {
        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $this->avisar(['nota' => 'Talla comercial']);
        $aviso = AvisoPesca::sole();

        $this->actingAs($this->administrador)->get(route('inicio'))->assertSee(__('inicio.listos_para_pescar', ['cantidad' => 1]))->assertSee('Talla comercial');
        $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertSee(__('lagos.aviso_pesca_listo', ['especie' => 'Mojarra roja']))->assertSee(__('lagos.marcar_atendido'));

        $this->actingAs($this->trabajador)->post(route('admin.avisos-pesca.atender', $aviso))->assertForbidden();
        $this->actingAs($this->administrador)->post(route('admin.avisos-pesca.atender', $aviso))->assertSessionHasNoErrors();

        $aviso->refresh();
        $this->assertSame([AvisoPesca::ATENDIDO, $this->administrador->id], [$aviso->estado, $aviso->atendido_por]);
        $this->assertNotNull($aviso->atendido_at);
        $this->actingAs($this->administrador)->get(route('inicio'))->assertDontSee(__('inicio.listos_para_pescar', ['cantidad' => 1]));
        $this->actingAs($this->administrador)->post(route('admin.avisos-pesca.atender', $aviso))->assertSessionHasErrors('aviso');

        // Atendido, se puede volver a avisar
        $this->avisar()->assertSessionHasNoErrors();
        $this->assertSame(2, AvisoPesca::count());

        // Ambos quedan en la bitácora
        $this->assertSame(['aviso_pesca', 'aviso_atendido', 'aviso_pesca'], Bitacora::query()->orderBy('id')->pluck('accion')->all());
        $this->assertSame([Rol::TRABAJADOR, Rol::ADMINISTRADOR, Rol::TRABAJADOR], Bitacora::query()->orderBy('id')->pluck('rol')->all());
    }

    public function test_la_cosecha_de_esa_siembra_atiende_el_aviso(): void
    {
        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $this->avisar();

        $this->actingAs($this->administrador)->post(route('admin.cosechas.store'), ['siembra_id' => $this->siembra->id, 'destino' => 'Plaza', 'fecha' => '2026-10-15', 'peces' => 100, 'kg' => 25])->assertSessionHasNoErrors();

        $aviso = AvisoPesca::sole();
        $this->assertSame(AvisoPesca::ATENDIDO, $aviso->estado);
        $this->assertSame($this->administrador->id, $aviso->atendido_por);
    }

    public function test_el_propietario_lo_ve_en_solo_lectura_y_en_sus_reportes(): void
    {
        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $this->avisar(['nota' => 'Talla comercial']);
        $dueno = $this->persona(Rol::PROPIETARIO);

        $this->actingAs($dueno)->get(route('admin.lagos.index'))->assertSee(__('lagos.aviso_pesca_listo', ['especie' => 'Mojarra roja']))->assertDontSee(__('lagos.marcar_atendido'));
        $this->actingAs($dueno)->get(route('admin.reportes.mensual', ['mes' => '2026-10']))->assertOk()->assertSee(__('reportes.avisos_pesca'))->assertSee('Talla comercial');
        $this->actingAs($dueno)->post(route('admin.avisos-pesca.atender', AvisoPesca::sole()))->assertForbidden();
        $this->actingAs($dueno)->get(route('inicio'))->assertDontSee(__('lagos.marcar_atendido'));
    }

    // --- 3. Botón «Nueva venta» ---

    public function test_sin_cosecha_disponible_el_boton_esta_desactivado_y_explica_por_que(): void
    {
        $this->actingAs($this->administrador)->get(route('admin.ventas.index'))->assertOk()
            ->assertSee(__('ventas.sin_cosecha_disponible'))
            ->assertSee(route('admin.lagos.index'), false)
            ->assertSee('aria-disabled="true"', false)
            ->assertSee('aria-describedby="motivo-sin-cosecha"', false)
            ->assertSee('id="motivo-sin-cosecha"', false)
            ->assertDontSee('id="modal-venta"', false);
    }

    public function test_con_cosecha_disponible_el_boton_abre_el_formulario_y_la_venta_se_guarda(): void
    {
        $cosecha = Cosecha::create(['siembra_id' => $this->siembra->id, 'lago_id' => $this->lago->id, 'user_id' => $this->administrador->id,
            'fecha' => '2026-10-14', 'peces' => 100, 'kg' => 30, 'peso_promedio_g' => 300]);

        $html = $this->actingAs($this->administrador)->get(route('admin.ventas.index'))->assertOk()
            ->assertDontSee(__('ventas.sin_cosecha_disponible'))
            ->assertDontSee('aria-disabled="true"', false)
            ->getContent();

        // El botón apunta a la ventana del formulario y esa ventana existe con un formulario que guarda la venta
        $this->assertMatchesRegularExpression('/<button[^>]*data-abrir-modal="modal-venta"[^>]*data-nueva-venta/s', $html);
        $this->assertStringNotContainsString('data-nueva-venta disabled', $html);
        $this->assertMatchesRegularExpression('/<dialog id="modal-venta"[^>]*>.*action="'.preg_quote(route('admin.ventas.store'), '/').'"/s', $html);

        $this->actingAs($this->administrador)->post(route('admin.ventas.store'), [
            'cosecha_id' => $cosecha->id, 'cliente' => 'Doña Rosa', 'fecha' => '2026-10-15', 'kg' => 10, 'precio_kg' => 12000, 'forma_pago' => 'contado',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.ventas.index'));
        $this->assertDatabaseHas('ventas', ['cosecha_id' => $cosecha->id, 'cliente' => 'Doña Rosa', 'total' => 120000]);
    }

    public function test_solo_el_administrador_ve_el_boton_de_nueva_venta(): void
    {
        $this->actingAs($this->persona(Rol::PROPIETARIO))->get(route('admin.ventas.index'))->assertOk()->assertDontSee('data-nueva-venta', false);
    }

    // --- Aislamiento ---

    public function test_cada_finca_solo_ve_y_atiende_sus_avisos(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $ajenoTrabajador = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::TRABAJADOR]);
        $lagoAjeno = Lago::create(['nombre' => 'LagoAjeno', 'tipo' => 'tierra']);
        $siembraAjena = Siembra::create(['lago_id' => $lagoAjeno->id, 'especie' => 'mojarra roja', 'fecha' => '2026-09-01', 'cantidad_alevinos' => 10,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 10, 'peso_actual_g' => 10, 'biomasa_kg' => 0.1]);
        $avisoAjeno = AvisoPesca::create(['lago_id' => $lagoAjeno->id, 'siembra_id' => $siembraAjena->id, 'user_id' => $ajenoTrabajador->id,
            'estado' => AvisoPesca::PENDIENTE, 'reportado_at' => now(), 'nota' => 'SecretoAjeno']);
        app(TenantActual::class)->establecer($this->finca->id);

        $this->actingAs($this->administrador)->get(route('inicio'))->assertDontSee('SecretoAjeno');
        $this->actingAs($this->administrador)->post(route('admin.avisos-pesca.atender', $avisoAjeno))->assertNotFound();
        $this->ponerEnTurno($this->trabajador, Rol::TRABAJADOR);
        $this->actingAs($this->trabajador)->post(route('admin.avisos-pesca.store', $lagoAjeno), ['siembra_id' => $siembraAjena->id])->assertNotFound();
    }
}
