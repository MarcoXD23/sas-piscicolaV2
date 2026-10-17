<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\Tercero;
use App\Models\User;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * F5 (commit 1): proveedores (qué provee: alevinos, alimento, otros), la lista de la siembra, la pantalla de revisión
 * para ligar las siembras viejas con el proveedor escrito a mano (sin adivinar) y el número de lote manual o automático.
 * Datos ficticios.
 */
class ProveedoresAlevinosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private User $administrador;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-02 08:00', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
    }

    private function proveedor(string $nombre, array $provee = [Tercero::PROVEE_ALEVINOS], bool $activo = true): Tercero
    {
        $tercero = new Tercero(['nombres' => $nombre, 'activo' => $activo]);
        $tercero->asignarProvee($provee);
        $tercero->save();

        return $tercero;
    }

    private function siembraVieja(string $proveedorEscrito, string $fecha = '2026-06-01'): Siembra
    {
        return Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => $fecha,
            'proveedor_alevinos' => $proveedorEscrito, 'cantidad_alevinos' => 1000, 'peso_promedio_inicial_g' => 5,
            'peces_vivos' => 1000, 'peso_actual_g' => 5, 'biomasa_kg' => 5]);
    }

    private function sembrar(array $datos = [])
    {
        return $this->actingAs($this->administrador)->post(route('admin.siembras.store', $this->lago), $datos + [
            'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-11-01', 'cantidad_alevinos' => 5000, 'peso_promedio_inicial_g' => 1,
        ]);
    }

    public function test_el_administrador_agrega_un_proveedor_con_lo_que_provee(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.proveedores.store'), [
            'nombre' => '  Acuícola   Ficticia SAS ', 'telefono' => '300 000 0000', 'provee' => ['alimento', 'alevinos'],
        ])->assertRedirect(route('admin.proveedores.index'))->assertSessionHasNoErrors();

        $proveedor = Tercero::query()->proveedores()->sole();
        $this->assertSame('Acuícola Ficticia SAS', $proveedor->nombres);   // sin cambiar mayúsculas, sin espacios de más
        $this->assertSame(['alevinos', 'alimento'], $proveedor->provee);    // en el orden del catálogo
        $this->assertTrue($proveedor->es_proveedor);
        $this->assertFalse($proveedor->es_jornalero);
        $this->assertSame('proveedor_creado', Bitacora::query()->latest('id')->value('accion'));
    }

    public function test_debe_proveer_algo_y_solo_valores_conocidos(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.proveedores.store'), ['nombre' => 'X', 'provee' => []])
            ->assertSessionHasErrors('provee');
        $this->actingAs($this->administrador)->post(route('admin.proveedores.store'), ['nombre' => 'X', 'provee' => ['peces_de_oro']])
            ->assertSessionHasErrors('provee.0');
        $this->assertSame(0, Tercero::query()->count());
    }

    public function test_el_documento_de_un_jornalero_lo_vuelve_tambien_proveedor(): void
    {
        $jornalero = new Tercero(['nombres' => 'Ana', 'apellidos' => 'Prueba', 'es_jornalero' => true]);
        $jornalero->asignarDocumento('1.234.567');
        $jornalero->save();

        $this->actingAs($this->administrador)->post(route('admin.proveedores.store'), [
            'nombre' => 'Otro nombre', 'documento' => '1234567', 'provee' => ['alevinos'],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Tercero::query()->count());
        $jornalero->refresh();
        $this->assertTrue($jornalero->es_jornalero);
        $this->assertTrue($jornalero->es_proveedor);
        $this->assertSame('Ana', $jornalero->nombres);

        // Con el documento de alguien que ya es proveedor: error, no se duplica
        $this->actingAs($this->administrador)->post(route('admin.proveedores.store'), [
            'nombre' => 'Repetido', 'documento' => '1234567', 'provee' => ['alimento'],
        ])->assertSessionHasErrors('documento');
    }

    public function test_el_propietario_consulta_y_no_edita(): void
    {
        $proveedor = $this->proveedor('Alevinos Ficticios');

        $this->actingAs($this->propietario)->get(route('admin.proveedores.index'))->assertOk()
            ->assertSee('Alevinos Ficticios')->assertSee(__('proveedores.solo_lectura'));
        $this->actingAs($this->propietario)->get(route('admin.proveedores.revision'))->assertOk();
        $this->actingAs($this->propietario)->post(route('admin.proveedores.store'), ['nombre' => 'X', 'provee' => ['alevinos']])->assertForbidden();
        $this->actingAs($this->propietario)->get(route('admin.proveedores.edit', $proveedor))->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.proveedores.ligar'), ['texto' => 'x', 'crear' => 1])->assertForbidden();

        $trabajador = User::factory()->conRoles(Rol::TRABAJADOR)->create(['tenant_id' => $this->finca->id]);
        $this->actingAs($trabajador)->get(route('admin.proveedores.index'))->assertForbidden();
    }

    public function test_el_administrador_edita_y_desactiva(): void
    {
        $proveedor = $this->proveedor('Alevinos Ficticios');

        $this->actingAs($this->administrador)->put(route('admin.proveedores.update', $proveedor), [
            'nombre' => 'Alevinos Ficticios Dos', 'provee' => ['alevinos', 'otros'],
        ])->assertSessionHasNoErrors();
        $proveedor->refresh();
        $this->assertSame('Alevinos Ficticios Dos', $proveedor->nombres);
        $this->assertSame(['alevinos', 'otros'], $proveedor->provee);

        $this->actingAs($this->administrador)->post(route('admin.proveedores.activo', $proveedor));
        $this->assertFalse($proveedor->refresh()->activo);

        // Un jornalero que no es proveedor no se abre desde Proveedores
        $jornalero = Tercero::create(['nombres' => 'Luis', 'es_jornalero' => true]);
        $this->actingAs($this->administrador)->get(route('admin.proveedores.edit', $jornalero))->assertNotFound();
    }

    public function test_la_siembra_solo_ofrece_proveedores_activos_de_alevinos(): void
    {
        $alevinos = $this->proveedor('Alevinos Ficticios');
        $alimento = $this->proveedor('Concentrados Ficticios', [Tercero::PROVEE_ALIMENTO]);
        $inactivo = $this->proveedor('Alevinos Cerrados', activo: false);

        $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertOk()
            ->assertSee('Alevinos Ficticios')->assertDontSee('Concentrados Ficticios')->assertDontSee('Alevinos Cerrados');

        $this->sembrar(['proveedor_id' => $alimento->id])->assertSessionHasErrors('proveedor_id');
        $this->sembrar(['proveedor_id' => $inactivo->id])->assertSessionHasErrors('proveedor_id');
        $this->sembrar(['proveedor_id' => $alevinos->id, 'lote_alevinos' => 'A-17'])->assertSessionHasNoErrors();

        $siembra = Siembra::query()->sole();
        $this->assertSame($alevinos->id, $siembra->proveedor_id);
        $this->assertSame('Alevinos Ficticios', $siembra->nombreProveedor());
        $this->assertNull($siembra->proveedor_alevinos);
    }

    public function test_un_proveedor_de_otra_finca_no_se_puede_usar(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajeno = Tercero::withoutGlobalScopes()->forceCreate(['tenant_id' => $otra->id, 'nombres' => 'Ajeno', 'es_proveedor' => true, 'provee' => json_encode(['alevinos'])]);

        $this->sembrar(['proveedor_id' => $ajeno->id])->assertSessionHasErrors('proveedor_id');
        $this->actingAs($this->administrador)->get(route('admin.proveedores.index'))->assertDontSee('Ajeno');
        $this->actingAs($this->administrador)->get(route('admin.proveedores.edit', $ajeno->id))->assertNotFound();
    }

    public function test_las_siembras_viejas_sin_proveedor_del_catalogo_se_siguen_leyendo(): void
    {
        $vieja = $this->siembraVieja('Estación piscícola X');

        $this->assertNull($vieja->proveedor_id);
        $this->assertSame('Estación piscícola X', $vieja->nombreProveedor());
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertOk()->assertSee('Estación piscícola X');
        $this->actingAs($this->administrador)->get(route('admin.reportes.libro-campo', ['lago_id' => $this->lago->id, 'desde' => '2026-06-01', 'hasta' => '2026-11-02']))
            ->assertOk()->assertSee('Estación piscícola X');
    }

    public function test_la_revision_liga_solo_el_texto_exacto_y_conserva_lo_escrito(): void
    {
        $this->siembraVieja('Estación  piscícola X ', '2026-05-01');
        $this->siembraVieja('Estación piscícola X', '2026-06-01');
        $parecida = $this->siembraVieja('Estacion piscicola X', '2026-07-01');   // sin tildes: es otro texto, no se adivina
        $proveedor = $this->proveedor('Estación Piscícola X SAS');

        $this->actingAs($this->administrador)->get(route('admin.proveedores.revision'))->assertOk()
            ->assertSee('«Estación piscícola X»', false)->assertSee('«Estacion piscicola X»', false);

        $this->actingAs($this->administrador)->post(route('admin.proveedores.ligar'), ['texto' => 'Estación piscícola X', 'proveedor_id' => $proveedor->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Siembra::query()->where('proveedor_id', $proveedor->id)->count());
        $this->assertNull($parecida->refresh()->proveedor_id);
        // Lo escrito a mano no se borra
        $this->assertSame(3, Siembra::query()->whereNotNull('proveedor_alevinos')->count());
        $this->assertSame('proveedor_ligado', Bitacora::query()->latest('id')->value('accion'));

        // Ya no aparece como pendiente; la parecida sí
        $this->actingAs($this->administrador)->get(route('admin.proveedores.revision'))
            ->assertDontSee('«Estación piscícola X»</span>', false)->assertSee('«Estacion piscicola X»', false);
    }

    public function test_la_revision_puede_crear_el_proveedor_con_el_nombre_escrito(): void
    {
        $vieja = $this->siembraVieja('Alevinos del Río');

        $this->actingAs($this->administrador)->post(route('admin.proveedores.ligar'), ['texto' => 'Alevinos del Río', 'crear' => 1])
            ->assertSessionHasNoErrors();

        $nuevo = Tercero::query()->proveedores()->sole();
        $this->assertSame('Alevinos del Río', $nuevo->nombres);
        $this->assertSame(['alevinos'], $nuevo->provee);
        $this->assertSame($nuevo->id, $vieja->refresh()->proveedor_id);
    }

    public function test_la_revision_no_liga_sin_elegir_ni_a_un_proveedor_que_no_es_de_alevinos(): void
    {
        $vieja = $this->siembraVieja('Alevinos del Río');
        $alimento = $this->proveedor('Concentrados Ficticios', [Tercero::PROVEE_ALIMENTO]);

        $this->actingAs($this->administrador)->post(route('admin.proveedores.ligar'), ['texto' => 'Alevinos del Río'])->assertSessionHasErrors('proveedor_id');
        $this->actingAs($this->administrador)->post(route('admin.proveedores.ligar'), ['texto' => 'Alevinos del Río', 'proveedor_id' => $alimento->id])->assertSessionHasErrors('proveedor_id');
        $this->assertNull($vieja->refresh()->proveedor_id);
    }

    public function test_numero_de_lote_manual_por_defecto(): void
    {
        $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertSee(__('lagos.numero_lote_opcional'));

        $this->sembrar(['numero_lote' => ' L-2026-15 '])->assertSessionHasNoErrors();
        $this->assertSame('L-2026-15', Siembra::query()->sole()->numero_lote);
    }

    public function test_numero_de_lote_automatico_con_consecutivo_por_anio(): void
    {
        $this->actingAs($this->propietario)->put(route('admin.ajustes.operacion'), $this->ajustes([CatalogoAjustes::LOTES_NUMERACION => CatalogoAjustes::NUMERACION_AUTOMATICA]))
            ->assertSessionHasNoErrors();
        $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertDontSee(__('lagos.numero_lote_opcional'))->assertSee(__('lagos.numero_lote_automatico'));

        // Lo que llegue escrito no cuenta: lo pone la app
        $this->sembrar(['numero_lote' => 'escrito', 'fecha' => '2026-10-01'])->assertSessionHasNoErrors();
        $otro = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $this->actingAs($this->administrador)->post(route('admin.siembras.store', $otro), [
            'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-11-01', 'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 1,
        ])->assertSessionHasNoErrors();
        // Otra finca no cuenta para el consecutivo
        $ajena = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $lagoAjeno = Lago::withoutGlobalScopes()->forceCreate(['tenant_id' => $ajena->id, 'nombre' => 'X', 'tipo' => 'tierra']);
        Siembra::withoutGlobalScopes()->forceCreate(['tenant_id' => $ajena->id, 'lago_id' => $lagoAjeno->id, 'especie_id' => $this->especie('mojarra roja'),
            'fecha' => '2026-01-01', 'numero_lote' => '2026-050', 'cantidad_alevinos' => 1, 'peso_promedio_inicial_g' => 1, 'peces_vivos' => 1, 'peso_actual_g' => 1, 'biomasa_kg' => 0]);
        $tercero = Lago::create(['nombre' => 'L03', 'tipo' => 'tierra']);
        $this->actingAs($this->administrador)->post(route('admin.siembras.store', $tercero), [
            'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2025-12-20', 'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame(['2025-001', '2026-001', '2026-002'], Siembra::query()->orderBy('numero_lote')->pluck('numero_lote')->all());
    }

    /** Todos los ajustes de operación con su valor por defecto, cambiando los indicados. */
    private function ajustes(array $cambios): array
    {
        return collect(CatalogoAjustes::todos())->mapWithKeys(fn (array $definicion, string $clave) => [
            CatalogoAjustes::campo($clave) => $cambios[$clave] ?? $definicion['defecto'],
        ])->all();
    }
}
