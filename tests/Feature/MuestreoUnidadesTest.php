<?php

namespace Tests\Feature;

use App\Http\Middleware\RegistroSinConexion as Middleware;
use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\Muestreo;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Formato;
use App\Support\TenantActual;
use App\Support\UnidadesPeso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Peso total del muestreo en gramos, kilos o libras: el servidor convierte (UnidadesPeso, con enteros igual que el
 * teléfono), valida primero el número y después el rango de 0,1 a 999.999 g, y guarda siempre en gramos. La cola vieja
 * (solo peso_total_g) sigue funcionando. La unidad por defecto de la finca la elige el propietario en Ajustes.
 */
class MuestreoUnidadesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private User $propietario;

    private Siembra $siembra;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-03 10:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
        $this->propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]);
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $this->siembra = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1000, 'peso_actual_g' => 40, 'biomasa_kg' => 40]);
    }

    /** Muestreo desde el modal de Lagos (con conexión). */
    private function muestrear(array $datos, ?User $quien = null): TestResponse
    {
        $this->flushSession();

        return $this->actingAs($quien ?? $this->administrador)->post(route('admin.muestreos.store', $this->siembra->id), $datos + ['fecha' => '2026-11-03', 'peces_pesados' => 10]);
    }

    /** Muestreo enviado por la cola del teléfono (sin conexión). */
    private function desdeLaCola(array $datos): TestResponse
    {
        $this->flushHeaders();
        $iso = fn (Carbon $hora) => $hora->utc()->format('Y-m-d\TH:i:s.v\Z');

        return $this->actingAs($this->administrador)->withHeaders([
            'Accept' => 'application/json',
            Middleware::CABECERA_ID => (string) Str::uuid(),
            Middleware::CABECERA_MOMENTO => $iso(now()->subMinutes(30)),
            Middleware::CABECERA_HORA_TELEFONO => $iso(now()),
            Middleware::CABECERA_ULTIMA_HORA_SERVIDOR => $iso(now()->subMinutes(31)),
        ])->post(route('admin.muestreos.store', $this->siembra->id), $datos + ['fecha' => '2026-11-03', 'peces_pesados' => 10]);
    }

    private function ultimo(): Muestreo
    {
        return Muestreo::query()->latest('id')->firstOrFail();
    }

    public function test_conversiones_y_limites_en_cada_unidad(): void
    {
        foreach ([
            ['1,005', 'kg', 1005.00], ['0.5', 'lb', 226.80], ['1', 'lb', 453.59], ['1,005', 'g', 1.01], ['0.1', 'g', 0.10],
            ['999999', 'g', 999999.00], ['999.999', 'kg', 999999.00], ['2204.620', 'lb', 999998.81],
        ] as [$valor, $unidad, $gramos]) {
            $this->muestrear(['peso_total' => $valor, 'unidad' => $unidad])->assertSessionHasNoErrors();
            $this->assertEquals($gramos, (float) $this->ultimo()->peso_total_g, "{$valor} {$unidad}");
        }

        // Fuera de rango después de convertir: el mensaje dice el máximo en la unidad elegida
        $this->muestrear(['peso_total' => '0.09', 'unidad' => 'g'])->assertSessionHasErrors(['peso_total' => __('mensajes.muestreo.peso_fuera_de_rango')]);
        $this->muestrear(['peso_total' => '1000', 'unidad' => 'kg'])
            ->assertSessionHasErrors(['peso_total' => __('mensajes.muestreo.peso_fuera_de_rango_unidad', ['maximo' => Formato::numero(999.999, 3), 'unidad' => 'kg'])]);
        $this->muestrear(['peso_total' => '2204.622', 'unidad' => 'lb'])
            ->assertSessionHasErrors(['peso_total' => __('mensajes.muestreo.peso_fuera_de_rango_unidad', ['maximo' => Formato::numero(2204.62, 3), 'unidad' => 'lb'])]);
        $this->assertSame(8, Muestreo::count());
    }

    public function test_primero_se_valida_el_numero_y_la_unidad(): void
    {
        foreach (['abc', '1e3', '-1', '1.2345', '1234567', 'Infinity', 'NaN', '1.000,5'] as $valor) {
            $this->muestrear(['peso_total' => $valor, 'unidad' => 'kg'])->assertSessionHasErrors(['peso_total' => __('mensajes.muestreo.peso_no_valido')]);
        }
        $this->muestrear(['peso_total' => '5', 'unidad' => 'onzas'])->assertSessionHasErrors(['unidad' => __('mensajes.muestreo.unidad_invalida')]);
        $this->muestrear(['unidad' => 'kg'])->assertSessionHasErrors(['peso_total' => __('mensajes.muestreo.escribe_peso_total')]);
        $this->assertSame(0, Muestreo::count());
    }

    public function test_el_promedio_y_el_mensaje_van_en_gramos_y_en_la_unidad(): void
    {
        // 2,5 lb = 1.133,98 g; 20 peces → 56,70 g por pez = 0,125 lb
        $this->muestrear(['peso_total' => '2,5', 'unidad' => 'lb', 'peces_pesados' => 20])->assertSessionHasNoErrors()
            ->assertSessionHas('estado', __('mensajes.muestreo.registrado_con_promedio_unidad', [
                'promedio' => UnidadesPeso::texto(5670, 'lb'), 'anterior' => UnidadesPeso::texto(4000, 'lb'), 'diferencia' => '+'.UnidadesPeso::texto(1670, 'lb'),
            ]));
        $muestreo = $this->ultimo();
        $this->assertEquals(1133.98, (float) $muestreo->peso_total_g);
        $this->assertEquals(56.70, (float) $muestreo->peso_promedio_g);
        // Peso actual y biomasa, en gramos
        $this->assertEquals(56.70, (float) $this->siembra->fresh()->peso_actual_g);
        $this->assertEquals(56.70, (float) $this->siembra->fresh()->biomasa_kg);
        $this->assertStringContainsString('56,70 g (0,125 lb)', UnidadesPeso::texto(5670, 'lb'));
        // En gramos, el mensaje de siempre
        $this->muestrear(['peso_total' => '600', 'unidad' => 'g'])->assertSessionHas('estado', __('mensajes.muestreo.registrado_con_promedio', [
            'promedio' => Formato::numero(60, 2), 'anterior' => Formato::numero(56.70, 2), 'diferencia' => '+'.Formato::numero(3.30, 2),
        ]));
    }

    public function test_peso_total_y_peso_total_g_a_la_vez(): void
    {
        // Con unidad manda peso_total; si los dos coinciden en gramos, se acepta
        $this->muestrear(['peso_total' => '1', 'unidad' => 'kg', 'peso_total_g' => '1000'])->assertSessionHasNoErrors();
        $this->assertEquals(1000, (float) $this->ultimo()->peso_total_g);
        // Sin unidad: peso_total se toma en gramos y debe coincidir con peso_total_g
        $this->muestrear(['peso_total' => '500', 'peso_total_g' => '500'])->assertSessionHasNoErrors();
        $this->assertEquals(500, (float) $this->ultimo()->peso_total_g);
        // No coinciden: se rechaza
        $this->muestrear(['peso_total' => '1', 'unidad' => 'kg', 'peso_total_g' => '999'])->assertSessionHasErrors(['peso_total' => __('mensajes.muestreo.pesos_no_coinciden')]);
        $this->muestrear(['peso_total' => '2', 'peso_total_g' => '3'])->assertSessionHasErrors(['peso_total' => __('mensajes.muestreo.pesos_no_coinciden')]);
        $this->assertSame(2, Muestreo::count());
    }

    public function test_la_cola_vieja_sin_unidad_se_toma_en_gramos(): void
    {
        // Lo que guardaba el teléfono antes: solo peso_total_g
        $this->desdeLaCola(['peso_total_g' => '1250.5', 'peces_pesados' => 20])->assertCreated()->assertJson(['estado' => 'guardado']);
        $this->assertEquals(1250.50, (float) $this->ultimo()->peso_total_g);
        $this->assertEquals(62.53, (float) $this->ultimo()->peso_promedio_g);
        // Y un valor viejo fuera de rango se rechaza con su motivo
        $this->desdeLaCola(['peso_total_g' => '0.05'])->assertUnprocessable()->assertJsonPath('errors.peso_total_g.0', __('mensajes.muestreo.peso_fuera_de_rango'));
    }

    public function test_muestreo_sin_conexion_en_libras_y_en_kilos(): void
    {
        $this->desdeLaCola(['peso_total' => '2,5', 'unidad' => 'lb', 'peces_pesados' => 20])->assertCreated()
            ->assertJsonPath('mensaje', __('mensajes.muestreo.registrado_con_promedio_unidad', [
                'promedio' => UnidadesPeso::texto(5670, 'lb'), 'anterior' => UnidadesPeso::texto(4000, 'lb'), 'diferencia' => '+'.UnidadesPeso::texto(1670, 'lb'),
            ]));
        $this->assertEquals(1133.98, (float) $this->ultimo()->peso_total_g);

        $this->desdeLaCola(['peso_total' => '1,2', 'unidad' => 'kg'])->assertCreated();
        $this->assertEquals(1200, (float) $this->ultimo()->peso_total_g);

        // La bitácora guarda los gramos y lo que se escribió, con la nota de sin conexión
        $registros = Bitacora::where('accion', 'muestreo')->orderBy('id')->get();
        $this->assertSame(['2,5 lb', '1,2 kg'], $registros->map(fn ($r) => $r->detalle['escrito'])->all());
        $this->assertStringContainsString('peso total escrito: 2,5 lb', $registros[0]->textoDetalle());
        $this->assertStringContainsString('registrado sin conexión', $registros[0]->textoDetalle());
        $this->assertStringContainsString('promedio 56,7 g', $registros[0]->textoDetalle());
    }

    public function test_unidad_por_defecto_de_la_finca(): void
    {
        $this->assertSame('g', $this->finca->fresh()->unidad_peso_muestreo);   // gramos hasta que el propietario la cambie
        // Cada petición carga a la persona de nuevo (como en la app), con la finca de ese momento
        $paquete = fn () => $this->actingAs($this->administrador->fresh())->getJson(route('campo.paquete'))->assertOk()->json('muestreo');
        $this->assertSame('g', $paquete()['unidad_defecto']);

        $datos = ['nombre' => 'Finca', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'idioma' => 'es'];
        $this->actingAs($this->administrador)->put(route('admin.ajustes.update'), $datos + ['unidad_peso_muestreo' => 'lb'])->assertForbidden();
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), $datos + ['unidad_peso_muestreo' => 'onzas'])->assertSessionHasErrors('unidad_peso_muestreo');
        $this->actingAs($this->propietario)->get(route('admin.ajustes.edit'))->assertOk()->assertSee(__('region.ajustes.unidad_peso_muestreo'));
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), $datos + ['unidad_peso_muestreo' => 'lb'])->assertSessionHasNoErrors();

        $this->assertSame('lb', $this->finca->fresh()->unidad_peso_muestreo);
        $this->assertSame('lb', $paquete()['unidad_defecto']);
        $this->assertSame(UnidadesPeso::FACTORES, $paquete()['factores']);
        $this->actingAs($this->administrador->fresh())->get(route('admin.lagos.index'))->assertOk()->assertSee('data-unidad-defecto="lb"', false);
        // Lo guardado sigue en gramos
        $this->muestrear(['peso_total' => '1', 'unidad' => 'lb']);
        $this->assertEquals(453.59, (float) $this->ultimo()->peso_total_g);
    }

    public function test_aislamiento_por_finca(): void
    {
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), ['nombre' => 'Finca', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'idioma' => 'es', 'unidad_peso_muestreo' => 'kg']);

        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($otra->id);
        $adminOtra = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $otra->id]);
        Siembra::create(['lago_id' => Lago::create(['nombre' => 'X1', 'tipo' => 'tierra'])->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 10, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 5, 'biomasa_kg' => 0.05]);

        // La unidad de una finca no pasa a la otra
        $this->assertSame('g', $otra->fresh()->unidad_peso_muestreo);
        $this->assertSame('g', $this->actingAs($adminOtra)->getJson(route('campo.paquete'))->json('muestreo.unidad_defecto'));
        // Ni se puede muestrear una siembra ajena
        $this->muestrear(['peso_total' => '1', 'unidad' => 'kg'], $adminOtra)->assertNotFound();
        app(TenantActual::class)->establecer($this->finca->id);
        $this->assertSame(0, Muestreo::count());
        $this->assertSame('kg', $this->finca->fresh()->unidad_peso_muestreo);
    }
}
