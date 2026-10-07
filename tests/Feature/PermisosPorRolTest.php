<?php

namespace Tests\Feature;

use App\Models\Cosecha;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\StockAlimento;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Venta;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Permisos por rol de la Etapa A: el administrador opera, el propietario consulta (salvo lo exclusivo suyo)
 * y el propietario que también es administrador opera con los permisos de ambos.
 */
class PermisosPorRolTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private Lago $lago;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 09:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01', 'cantidad_alevinos' => 1000,
            'peso_promedio_inicial_g' => 10, 'peces_vivos' => 1000, 'peso_actual_g' => 200, 'biomasa_kg' => 200]);
        StockAlimento::create(['tipo_concentrado' => 'Engorde', 'cantidad_kg' => 500]);
    }

    private function persona(string ...$roles): User
    {
        return tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles($roles);
    }

    private function cosecha(): Cosecha
    {
        return Cosecha::create(['siembra_id' => $this->siembra->id, 'lago_id' => $this->lago->id, 'user_id' => $this->persona(Rol::ADMINISTRADOR)->id,
            'fecha' => '2026-10-14', 'peces' => 100, 'kg' => 30, 'peso_promedio_g' => 300]);
    }

    /** @return array<string, array{0: string, 1: string, 2: array<string, mixed>}> nombre => [método, url, datos válidos] */
    private function operaciones(): array
    {
        $cosecha = $this->cosecha();
        $venta = Venta::create(['cosecha_id' => $cosecha->id, 'user_id' => $cosecha->user_id, 'cliente' => 'Cliente', 'fecha' => '2026-10-14',
            'kg' => 5, 'precio_kg' => 1000, 'total' => 5000, 'forma_pago' => 'credito', 'saldo' => 5000]);
        $trabajador = $this->persona(Rol::TRABAJADOR);

        return [
            'crear lago' => ['post', route('admin.lagos.store'), ['nombre' => 'L02', 'tipo' => 'tierra']],
            'sembrar' => ['post', route('admin.siembras.store', $this->lago), ['especie_id' => $this->especie('cachama blanca'), 'fecha' => '2026-10-15', 'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 5]],
            'muestreo' => ['post', route('admin.muestreos.store', $this->siembra), ['fecha' => '2026-10-15', 'peces_pesados' => 10, 'peso_total_g' => 2500]],
            'mortalidad' => ['post', route('admin.mortalidades.store', $this->siembra), ['fecha' => '2026-10-15', 'cantidad' => 3]],
            'alimentar' => ['post', route('admin.raciones.store', $this->lago), ['fecha' => '2026-10-15', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 10]],
            'tareas' => ['get', route('admin.tareas.index'), []],
            'asignar tarea' => ['post', route('admin.tareas.store'), ['asignada_a' => $trabajador->id, 'titulo' => 'Revisar malla', 'fecha' => '2026-10-15']],
            'recepcion' => ['post', route('admin.bodega.recepciones.store'), ['fecha' => '2026-10-15', 'proveedor' => 'P', 'tipo_concentrado' => 'Engorde', 'lote' => 'L', 'bultos' => 1, 'peso_bulto_kg' => 40]],
            'venta' => ['post', route('admin.ventas.store'), ['cosecha_id' => $cosecha->id, 'cliente' => 'Otro', 'fecha' => '2026-10-15', 'kg' => 5, 'precio_kg' => 1000, 'forma_pago' => 'contado']],
            'abono' => ['post', route('admin.ventas.abonar', $venta), ['valor' => 1000, 'fecha' => '2026-10-15', 'medio' => 'efectivo']],
        ];
    }

    public function test_el_propietario_sin_rol_de_administrador_no_opera(): void
    {
        $dueno = $this->persona(Rol::PROPIETARIO);

        foreach ($this->operaciones() as $nombre => [$metodo, $url, $datos]) {
            $this->actingAs($dueno)->{$metodo}($url, $datos)->assertForbidden();
        }
        $this->assertSame(1000, $this->siembra->fresh()->peces_vivos);
        $this->assertSame(1, Lago::count());
    }

    public function test_el_propietario_ve_los_reportes_en_solo_lectura(): void
    {
        $dueno = $this->persona(Rol::PROPIETARIO);

        foreach (['inicio', 'admin.lagos.index', 'admin.ventas.index', 'admin.bodega.index', 'admin.reportes.index', 'admin.conversion.index', 'admin.alertas.index', 'noche.index'] as $ruta) {
            $this->actingAs($dueno)->get(route($ruta))->assertOk();
        }
        $this->actingAs($dueno)->get(route('admin.lagos.show', $this->lago))->assertOk()->assertSee('1.000');
        $this->actingAs($dueno)->get(route('admin.reportes.mensual'))->assertOk();

        // Sin botones de operación
        $this->actingAs($dueno)->get(route('admin.lagos.index'))
            ->assertSee(__('lagos.solo_lectura'))
            ->assertDontSee(__('lagos.nuevo_lago'))
            ->assertDontSee('modal-racion-', false)
            ->assertDontSee('modal-mortalidad-', false);
        $this->actingAs($dueno)->get(route('admin.ventas.index'))->assertDontSee(__('ventas.nueva_venta'));
        $this->actingAs($dueno)->get(route('admin.bodega.index'))->assertSee(__('bodega.registrar_despacho'))->assertDontSee(__('bodega.recibir_alimento'));

        // Tareas salen del menú del propietario
        $this->actingAs($dueno)->get(route('inicio'))->assertDontSee(route('admin.tareas.index'), false);
    }

    public function test_el_administrador_opera_todo_lo_asignado_sin_turno(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);

        foreach ($this->operaciones() as $nombre => [$metodo, $url, $datos]) {
            $respuesta = $this->actingAs($administrador)->{$metodo}($url, $datos);
            $this->assertNotSame(403, $respuesta->status(), "El administrador no pudo: {$nombre}");
            $respuesta->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Lago::count());
        $this->assertSame(997, $this->siembra->fresh()->peces_vivos);
        $this->actingAs($administrador)->get(route('admin.lagos.index'))->assertSee(__('lagos.nuevo_lago'));
        $this->actingAs($administrador)->get(route('admin.bodega.index'))->assertSee(__('bodega.recibir_alimento'))->assertDontSee(__('bodega.registrar_despacho'));
    }

    public function test_el_administrador_no_entra_a_lo_exclusivo_del_propietario(): void
    {
        $administrador = $this->persona(Rol::ADMINISTRADOR);

        foreach (['admin.personal.index', 'admin.turnos.index', 'admin.solicitudes.index', 'admin.nomina.index', 'admin.agenda.index', 'admin.ajustes.edit', 'admin.bitacora.index'] as $ruta) {
            $this->actingAs($administrador)->get(route($ruta))->assertForbidden();
        }
        $this->actingAs($administrador)->post(route('admin.bodega.despachos.store'), [
            'fecha' => '2026-10-15', 'proveedor' => 'P', 'tipo_concentrado' => 'Engorde', 'lote' => 'L', 'bultos' => 1, 'peso_bulto_kg' => 40,
        ])->assertForbidden();
    }

    public function test_el_propietario_que_tambien_es_administrador_opera_con_ambos_permisos(): void
    {
        $dueno = $this->persona(Rol::PROPIETARIO, Rol::ADMINISTRADOR);

        foreach ($this->operaciones() as $nombre => [$metodo, $url, $datos]) {
            $this->assertNotSame(403, $this->actingAs($dueno)->{$metodo}($url, $datos)->status(), "No pudo: {$nombre}");
        }
        $this->actingAs($dueno)->get(route('admin.personal.index'))->assertOk();
        $this->actingAs($dueno)->get(route('admin.bitacora.index'))->assertOk()->assertSee(__('bitacora.roles.administrador'));
    }

    public function test_el_propietario_puede_asignarse_el_rol_de_administrador_y_quitarselo(): void
    {
        $dueno = $this->persona(Rol::PROPIETARIO);

        $this->actingAs($dueno)->get(route('admin.personal.index'))->assertOk()
            ->assertSee(__('personal.tambien_administrador'))
            ->assertDontSee('value="propietario"', false);

        $this->actingAs($dueno)->put(route('admin.personal.mi-rol-administrador'), ['administrador' => '1'])->assertRedirect();
        $this->assertTrue($dueno->fresh()->tieneRol(Rol::PROPIETARIO));
        $this->assertTrue($dueno->fresh()->tieneRol(Rol::ADMINISTRADOR));
        $this->actingAs($dueno->fresh())->post(route('admin.lagos.store'), ['nombre' => 'L02', 'tipo' => 'tierra'])->assertSessionHasNoErrors();

        $this->actingAs($dueno)->put(route('admin.personal.mi-rol-administrador'), ['administrador' => '0'])->assertRedirect();
        $this->assertTrue($dueno->fresh()->tieneRol(Rol::PROPIETARIO));
        $this->assertFalse($dueno->fresh()->tieneRol(Rol::ADMINISTRADOR));

        // Solo el propietario puede usarlo
        $this->actingAs($this->persona(Rol::ADMINISTRADOR))->put(route('admin.personal.mi-rol-administrador'), ['administrador' => '1'])->assertForbidden();
    }

    public function test_trabajador_y_celador_siguen_igual(): void
    {
        $trabajador = $this->persona(Rol::TRABAJADOR);
        $alimentar = fn () => $this->actingAs($trabajador)->post(route('admin.raciones.store', $this->lago), ['fecha' => '2026-10-15', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 5]);
        $alimentar()->assertForbidden();                        // fuera de turno
        $this->ponerEnTurno($trabajador, Rol::TRABAJADOR);
        $alimentar()->assertSessionHasNoErrors();               // en turno
        $this->actingAs($trabajador)->post(route('admin.mortalidades.store', $this->siembra), ['fecha' => '2026-10-15', 'cantidad' => 1])->assertSessionHasNoErrors();
        $this->actingAs($trabajador)->post(route('admin.muestreos.store', $this->siembra), ['fecha' => '2026-10-15', 'peces_pesados' => 1, 'peso_total_g' => 200])->assertForbidden();

        $celador = $this->persona(Rol::CELADOR);
        $ronda = fn (User $quien) => $this->actingAs($quien)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD]);
        $ronda($celador)->assertForbidden();
        $this->ponerEnTurno($celador, Rol::CELADOR);
        $ronda($celador)->assertSessionHasNoErrors();
        $this->actingAs($celador)->get(route('admin.lagos.index'))->assertForbidden();
    }

    public function test_propietario_y_administrador_ven_noche_en_solo_lectura_con_reportes_por_turno(): void
    {
        $celador = $this->persona(Rol::CELADOR);
        $turno = $this->ponerEnTurno($celador, Rol::CELADOR);
        Ronda::create(['user_id' => $celador->id, 'turno_id' => $turno->id, 'registrada_at' => now(), 'tipo' => 'intrusion', 'descripcion' => 'Malla rota']);

        foreach ([$this->persona(Rol::PROPIETARIO), $this->persona(Rol::ADMINISTRADOR)] as $consulta) {
            $this->actingAs($consulta)->get(route('noche.index'))->assertOk()
                ->assertSee(__('noche.solo_lectura'))
                ->assertSee(__('noche.reportes_por_turno'))
                ->assertSee('Malla rota')
                ->assertDontSee('data-abrir-modal="modal-ronda"', false);
            $this->actingAs($consulta)->post(route('noche.rondas.store'), ['tipo' => Ronda::SIN_NOVEDAD])->assertForbidden();
            $this->actingAs($consulta)->post(route('noche.aireador.encender', $this->lago))->assertForbidden();
        }
    }

    public function test_cada_finca_solo_ve_y_opera_lo_suyo(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        app(TenantActual::class)->establecer($otra->id);
        $lagoAjeno = Lago::create(['nombre' => 'Ajeno', 'tipo' => 'tierra']);
        app(TenantActual::class)->establecer($this->finca->id);

        $administrador = $this->persona(Rol::ADMINISTRADOR);
        $this->actingAs($administrador)->get(route('admin.lagos.show', $lagoAjeno))->assertNotFound();
        $this->actingAs($administrador)->post(route('admin.raciones.store', $lagoAjeno), ['fecha' => '2026-10-15', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 1])->assertNotFound();
        $this->actingAs($administrador)->get(route('admin.lagos.index'))->assertDontSee('Ajeno');
    }
}
