<?php

namespace Tests\Feature;

use App\Models\AjusteFinca;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Datos\TablasFinca;
use App\Support\TenantActual;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F1: ajustes de operación por finca (CatalogoAjustes + AjustesFinca). Solo el propietario los cambia, cada finca
 * tiene los suyos y cada cambio queda en la bitácora.
 */
class AjustesFincaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private string $campo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'NORTE']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = $this->persona($this->finca, Rol::PROPIETARIO);
        $this->campo = CatalogoAjustes::campo(CatalogoAjustes::AGUA_QUIEN_MIDE);
    }

    private function persona(Tenant $finca, string $rol): User
    {
        return tap(User::factory()->create(['tenant_id' => $finca->id, 'password' => 'clave-segura-123']))->asignarRoles([$rol]);
    }

    /** Envía el formulario completo, como la pantalla: los ajustes que no se cambian van con su valor de ahora. */
    private function guardar(User $usuario, array $datos)
    {
        $actuales = collect(AjustesFinca::todos())->mapWithKeys(fn ($valor, string $clave) => [CatalogoAjustes::campo($clave) => $valor])->all();

        return $this->actingAs($usuario)->put(route('admin.ajustes.operacion'), $datos + $actuales);
    }

    public function test_sin_cambios_cada_ajuste_vale_su_valor_por_defecto(): void
    {
        // Por defecto «ambos»: como funciona hoy (el administrador desde Lagos y el celador desde Seguridad & Noche)
        $this->assertSame(CatalogoAjustes::QUIEN_MIDE_AMBOS, AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));
        // Plazo del muestreo: 30 días (diseño, tema 15.3)
        // Jornadas de proceso apagadas (ninguna finca cambia); ayuno de 1 día y sin alimentación encendida solo actúan con ellas
        $this->assertSame([CatalogoAjustes::AGUA_QUIEN_MIDE => 'ambos', CatalogoAjustes::MUESTREO_PLAZO_DIAS => 30, CatalogoAjustes::LOTES_NUMERACION => 'manual',
            CatalogoAjustes::PROCESO_JORNADAS => 'apagada', CatalogoAjustes::PROCESO_DIAS_AYUNO => 1, CatalogoAjustes::PROCESO_SIN_ALIMENTACION => 'encendida',
            CatalogoAjustes::SEGURIDAD_DOS_PASOS => 'apagada'], AjustesFinca::todos());
        $this->assertSame(0, AjusteFinca::count());

        $this->actingAs($this->propietario)->get(route('admin.ajustes.edit'))->assertOk()
            ->assertSee(__('ajustes_finca.titulo'))
            ->assertSee(__('ajustes_finca.agua.quien_mide.etiqueta'))
            ->assertSee('<option value="ambos" selected', false)
            ->assertSee(__('ajustes_finca.muestreo.plazo_dias.etiqueta'))
            ->assertSee('name="'.CatalogoAjustes::campo(CatalogoAjustes::MUESTREO_PLAZO_DIAS).'"', false);
    }

    public function test_el_plazo_del_muestreo_es_un_entero_con_rango_y_queda_en_la_bitacora(): void
    {
        $campo = CatalogoAjustes::campo(CatalogoAjustes::MUESTREO_PLAZO_DIAS);

        foreach (['0', '181', 'treinta', '2.5'] as $malo) {
            $this->guardar($this->propietario, [$campo => $malo])->assertSessionHasErrors($campo);
        }
        $this->assertSame(0, AjusteFinca::count());

        $this->guardar($this->propietario, [$campo => '45'])->assertSessionHasNoErrors()->assertSessionHas('estado', __('ajustes_finca.guardados'));
        $this->assertSame(45, AjustesFinca::valor(CatalogoAjustes::MUESTREO_PLAZO_DIAS));
        $this->assertSame('Ajuste «Plazo del muestreo (días)»: 30 días → 45 días',
            Bitacora::query()->where('accion', 'ajustes')->sole()->textoDetalle());

        // Guardar el mismo número no escribe en la bitácora; uno guardado fuera de rango vuelve al de por defecto
        $this->guardar($this->propietario, [$campo => '45'])->assertSessionHas('estado', __('ajustes_finca.sin_cambios'));
        AjusteFinca::query()->where('clave', CatalogoAjustes::MUESTREO_PLAZO_DIAS)->update(['valor' => json_encode(500)]);
        $this->assertSame(30, AjustesFinca::valor(CatalogoAjustes::MUESTREO_PLAZO_DIAS));
    }

    public function test_el_propietario_guarda_y_queda_en_la_bitacora(): void
    {
        $this->guardar($this->propietario, [$this->campo => 'administrador'])
            ->assertRedirect(route('admin.ajustes.edit'))->assertSessionHas('estado', __('ajustes_finca.guardados'));

        $this->assertSame('administrador', AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));
        $ajuste = AjusteFinca::sole();
        $this->assertSame($this->propietario->id, $ajuste->cambiado_por);

        $registro = Bitacora::query()->where('accion', 'ajustes')->sole();
        $this->assertSame('propietario', $registro->rol);
        $this->assertSame('Ajuste «Quién mide el agua»: El celador y el administrador → Solo el administrador', $registro->textoDetalle());

        // Guardar lo mismo no cambia nada ni escribe en la bitácora
        $this->guardar($this->propietario, [$this->campo => 'administrador'])->assertSessionHas('estado', __('ajustes_finca.sin_cambios'));
        $this->assertSame(1, Bitacora::query()->where('accion', 'ajustes')->count());

        // Volver a cambiarlo deja el valor de antes y el de después
        $this->guardar($this->propietario, [$this->campo => 'celador']);
        $this->assertSame('celador', AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));
        $this->assertSame(1, AjusteFinca::count());
        $this->assertSame('Ajuste «Quién mide el agua»: Solo el administrador → Solo el celador',
            Bitacora::query()->where('accion', 'ajustes')->latest('id')->first()->textoDetalle());
    }

    public function test_una_opcion_que_no_existe_o_un_campo_vacio_se_rechazan(): void
    {
        $this->guardar($this->propietario, [$this->campo => 'trabajador'])->assertSessionHasErrors($this->campo);
        $this->actingAs($this->propietario)->put(route('admin.ajustes.operacion'), [])->assertSessionHasErrors($this->campo);
        $this->assertSame(0, AjusteFinca::count());

        // Una clave desconocida no existe en el catálogo
        $this->expectException(\InvalidArgumentException::class);
        AjustesFinca::valor('no.existe');
    }

    public function test_un_valor_guardado_que_ya_no_es_opcion_vuelve_al_valor_por_defecto(): void
    {
        AjusteFinca::create(['clave' => CatalogoAjustes::AGUA_QUIEN_MIDE, 'valor' => 'opcion-retirada']);

        $this->assertSame('ambos', AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));
    }

    public function test_solo_el_propietario_cambia_los_ajustes(): void
    {
        foreach ([Rol::ADMINISTRADOR, Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $this->guardar($this->persona($this->finca, $rol), [$this->campo => 'administrador'])->assertForbidden();
        }
        $this->assertSame(0, AjusteFinca::count());
        $this->assertSame('ambos', AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));
    }

    public function test_el_ajuste_de_una_finca_no_afecta_a_otra(): void
    {
        $this->guardar($this->propietario, [$this->campo => 'administrador']);

        $otra = Tenant::create(['nombre' => 'Finca Sur', 'codigo' => 'SUR']);
        app(TenantActual::class)->establecer($otra->id);
        $this->assertSame('ambos', AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));

        $propietarioOtra = $this->persona($otra, Rol::PROPIETARIO);
        $this->guardar($propietarioOtra, [$this->campo => 'celador']);
        $this->assertSame('celador', AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));

        app(TenantActual::class)->establecer($this->finca->id);
        $this->assertSame('administrador', AjustesFinca::valor(CatalogoAjustes::AGUA_QUIEN_MIDE));
    }

    public function test_la_exportacion_y_el_borrado_de_la_finca_incluyen_los_ajustes(): void
    {
        $this->guardar($this->propietario, [$this->campo => 'administrador']);
        $this->assertContains('ajustes_finca', TablasFinca::todas());
        $this->assertContains('ajustes_finca', TablasFinca::ordenDeBorrado());

        // Borrado definitivo con PHP y la base en la misma hora (ver EliminarFincaTest::vencerPlazo)
        $this->actingAs($this->propietario)->post(route('admin.datos.eliminar'), ['codigo' => 'NORTE', 'actual' => 'clave-segura-123']);
        $this->travelTo(CarbonImmutable::parse(DB::selectOne("SELECT (now() AT TIME ZONE 'UTC') AS ahora")->ahora, 'UTC'));
        DB::table('tenants')->where('id', $this->finca->id)->update(['eliminacion_programada_at' => DB::raw("(now() AT TIME ZONE 'UTC') - interval '1 minute'")]);
        $this->artisan('piscicola:eliminar-fincas')->assertSuccessful();

        $this->assertSame(0, DB::table('ajustes_finca')->where('tenant_id', $this->finca->id)->count());
    }
}
