<?php

namespace Tests\Feature;

use App\Models\Especie;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Asistente\ContextoFinca;
use App\Support\CalculadoraRacion;
use App\Support\RangoAgua;
use App\Support\TenantActual;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Catálogo de especies por finca: especies por defecto, edición con permisos, policultivo, rango común del agua
 * y ración sin tipo de alimentación.
 */
class EspeciesFincaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private User $propietario;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
    }

    private function sembrar(int $especieId, string $fecha = '2026-10-01', int $peces = 1000): Siembra
    {
        return Siembra::create(['lago_id' => $this->lago->id, 'especie_id' => $especieId, 'fecha' => $fecha, 'cantidad_alevinos' => $peces,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => $peces, 'peso_actual_g' => 100, 'biomasa_kg' => $peces * 0.1]);
    }

    private function datosEspecie(array $datos = []): array
    {
        return $datos + ['nombre' => 'Sábalo', 'nombre_cientifico' => 'Prochilodus sp.', 'clima' => 'calido',
            'temp_min' => 24, 'temp_max' => 29, 'oxigeno_min' => 4, 'ph_min' => 6.5, 'ph_max' => 8, 'alimentacion' => 'detritivoro'];
    }

    // --- Especies por defecto ---

    public function test_una_finca_nueva_arranca_con_las_6_especies_por_defecto_y_cada_finca_tiene_las_suyas(): void
    {
        $this->assertSame(['bagre rayado', 'bocachico', 'cachama blanca', 'mojarra negra', 'mojarra roja', 'trucha arcoiris'],
            Especie::query()->orderBy('clave')->pluck('clave')->all());
        $trucha = Especie::deClave('trucha arcoiris');
        $this->assertSame([Especie::FRIO, 12.0, 16.0, 6.5, 'frio', true], [$trucha->clima, $trucha->temp_min, $trucha->temp_max, $trucha->oxigeno_min, $trucha->alimentacion, $trucha->activo]);

        // Otra finca recibe su propio catálogo, editable sin afectar a esta
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        Especie::deClave('trucha arcoiris')->update(['temp_max' => 18]);
        $this->assertSame(6, TenantActual::sinFiltro(fn () => Especie::where('tenant_id', $otra->id)->count()));
        $this->assertSame(16.0, TenantActual::sinFiltro(fn () => Especie::where('tenant_id', $otra->id)->where('clave', 'trucha arcoiris')->value('temp_max')) + 0.0);
    }

    public function test_la_migracion_carga_el_catalogo_en_fincas_existentes_y_pasa_las_siembras_sin_perder_historial(): void
    {
        // Se deshacen también las migraciones posteriores que dependen de la tabla especies
        $migracion = require database_path('migrations/2026_10_24_000001_create_especies_por_finca.php');
        $ficha = require database_path('migrations/2026_10_25_000001_add_ficha_to_especies.php');
        $fotos = require database_path('migrations/2026_10_26_000001_create_especie_fotos_table.php');
        $fotos->down();
        $migracion->down();
        $this->assertTrue(Schema::hasColumn('siembras', 'especie'));

        // Siembras guardadas con el texto de la especie (formato anterior), una con un texto fuera del catálogo
        $fila = fn (string $especie) => ['tenant_id' => $this->finca->id, 'lago_id' => $this->lago->id, 'especie' => $especie, 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 90, 'peso_actual_g' => 50, 'biomasa_kg' => 4.5, 'created_at' => now(), 'updated_at' => now()];
        DB::table('siembras')->insert([$fila('mojarra roja'), $fila('cachama blanca'), $fila('sábalo del río')]);

        $migracion->up();
        $ficha->up();
        $fotos->up();

        $this->assertFalse(Schema::hasColumn('siembras', 'especie'));   // el texto viejo ya no queda en uso
        $this->assertSame(7, Especie::count());                         // 6 por defecto + la que no estaba en el catálogo
        $siembras = Siembra::query()->orderBy('id')->get();
        $this->assertSame(['Mojarra roja', 'Cachama blanca', 'Sábalo del río'], $siembras->pluck('nombre_especie')->all());
        $this->assertSame([90, 90, 90], $siembras->pluck('peces_vivos')->all());
        $propia = Especie::where('nombre', 'Sábalo del río')->sole();
        $this->assertNull($propia->clave);
        $this->assertFalse($propia->tieneRangos());   // sin rangos inventados: queda para revisarla
    }

    // --- Edición y permisos ---

    public function test_el_administrador_agrega_edita_desactiva_y_el_propietario_solo_consulta(): void
    {
        $this->actingAs($this->administrador)->post(route('admin.especies.store'), $this->datosEspecie())->assertSessionHasNoErrors();
        $sabalo = Especie::where('nombre', 'Sábalo')->sole();
        $this->assertTrue($sabalo->activo);
        $this->assertNull($sabalo->clave);

        // Nombre único sin importar mayúsculas; rangos al derecho; tipos validados
        $this->actingAs($this->administrador)->post(route('admin.especies.store'), $this->datosEspecie(['nombre' => 'SÁBALO']))
            ->assertInvalid(['nombre' => 'Ya hay una especie con ese nombre']);
        $this->actingAs($this->administrador)->post(route('admin.especies.store'), $this->datosEspecie(['nombre' => 'X', 'temp_min' => 30, 'temp_max' => 20]))
            ->assertInvalid(['temp_min' => 'El mínimo no puede ser mayor que el máximo.']);
        $this->actingAs($this->administrador)->post(route('admin.especies.store'), $this->datosEspecie(['nombre' => 'Y', 'clima' => 'templado', 'alimentacion' => 'pasto']))
            ->assertInvalid(['clima', 'alimentacion']);

        // Rangos y alimentación son opcionales
        $this->actingAs($this->administrador)->post(route('admin.especies.store'), ['nombre' => 'Arawana', 'clima' => 'calido'])->assertSessionHasNoErrors();
        $this->assertFalse(Especie::where('nombre', 'Arawana')->sole()->tieneRangos());

        $this->actingAs($this->administrador)->put(route('admin.especies.update', $sabalo), $this->datosEspecie(['temp_max' => 31]))->assertSessionHasNoErrors();
        $this->assertSame(31.0, $sabalo->fresh()->temp_max);
        $this->actingAs($this->administrador)->post(route('admin.especies.activo', $sabalo))->assertSessionHasNoErrors();
        $this->assertFalse($sabalo->fresh()->activo);

        // El propietario sin rol de administrador consulta, pero no edita
        $this->actingAs($this->propietario)->get(route('admin.especies.index'))->assertOk()
            ->assertSee('Sábalo')->assertSee(__('especies.catalogo_finca.solo_lectura'))->assertDontSee(route('admin.especies.edit', $sabalo));
        $this->actingAs($this->propietario)->post(route('admin.especies.store'), $this->datosEspecie(['nombre' => 'Z']))->assertForbidden();
        $this->actingAs($this->propietario)->put(route('admin.especies.update', $sabalo), $this->datosEspecie())->assertForbidden();
        $this->actingAs($this->propietario)->post(route('admin.especies.activo', $sabalo))->assertForbidden();
        $this->actingAs($this->propietario)->delete(route('admin.especies.destroy', $sabalo))->assertForbidden();

        $trabajador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::TRABAJADOR]);
        $this->actingAs($trabajador)->get(route('admin.especies.index'))->assertForbidden();
    }

    public function test_una_especie_con_siembras_no_se_borra_y_desactivada_no_se_siembra_pero_conserva_su_historial(): void
    {
        $mojarra = Especie::deClave('mojarra roja');
        $siembra = $this->sembrar($mojarra->id);

        $this->actingAs($this->administrador)->delete(route('admin.especies.destroy', $mojarra))
            ->assertSessionHasErrors(['especie' => __('mensajes.especie.con_siembras', ['nombre' => 'Mojarra roja'])]);
        $this->assertNotNull($mojarra->fresh());
        // La base también lo impide (en un punto de guardado, para que la prueba siga después del error)
        try {
            DB::transaction(fn () => DB::table('especies')->where('id', $mojarra->id)->delete());
            $this->fail('La base debió impedir borrar una especie con siembras');
        } catch (QueryException) {
        }

        // Sin siembras sí se borra
        $sabalo = Especie::create(['nombre' => 'Sábalo', 'clima' => Especie::CALIDO]);
        $this->actingAs($this->administrador)->delete(route('admin.especies.destroy', $sabalo))->assertSessionHasNoErrors();
        $this->assertNull(Especie::find($sabalo->id));

        // Desactivada: no se siembra, pero la siembra que ya tenía sigue y conserva su nombre en el historial
        $mojarra->update(['activo' => false]);
        $this->actingAs($this->administrador)->post(route('admin.siembras.store', $this->lago), [
            'especie_id' => $mojarra->id, 'fecha' => '2026-10-05', 'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 5,
        ])->assertInvalid(['especie_id' => 'Elige una especie activa']);
        $this->assertTrue($siembra->fresh()->estaActiva());
        $this->assertSame('Mojarra roja', $siembra->fresh()->nombre_especie);
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertOk()->assertSee('Mojarra roja');
        $this->actingAs($this->administrador)->get(route('admin.lagos.index'))->assertOk()
            ->assertDontSee('<option value="'.$mojarra->id.'">', false);   // no aparece para sembrar
    }

    public function test_no_se_siembra_una_especie_de_otra_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajena = TenantActual::sinFiltro(fn () => Especie::where('tenant_id', $otra->id)->where('clave', 'mojarra roja')->value('id'));

        $this->actingAs($this->administrador)->post(route('admin.siembras.store', $this->lago), [
            'especie_id' => $ajena, 'fecha' => '2026-10-05', 'cantidad_alevinos' => 100, 'peso_promedio_inicial_g' => 5,
        ])->assertInvalid(['especie_id']);
        $this->actingAs($this->administrador)->get(route('admin.especies.edit', $ajena))->assertNotFound();
        $this->assertSame(0, Siembra::count());
    }

    // --- Policultivo ---

    public function test_varias_especies_y_la_misma_dos_veces_como_lotes_y_el_traslado_se_suma_al_lote_mas_antiguo(): void
    {
        $mojarra = $this->especie('mojarra roja');
        $antiguo = $this->sembrar($mojarra, '2026-08-01', 500);
        $nuevo = $this->sembrar($mojarra, '2026-09-15', 300);
        $this->sembrar($this->especie('cachama blanca'), '2026-09-01', 200);
        $this->assertSame(3, $this->lago->siembrasActivas()->count());

        $origenLago = Lago::create(['nombre' => 'L02', 'tipo' => 'tierra']);
        $origen = Siembra::create(['lago_id' => $origenLago->id, 'especie_id' => $mojarra, 'fecha' => '2026-09-01', 'cantidad_alevinos' => 100,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 100, 'peso_actual_g' => 100, 'biomasa_kg' => 10]);

        $this->actingAs($this->administrador)->post(route('admin.traslados.store', $origen), [
            'lago_destino_id' => $this->lago->id, 'fecha' => now()->toDateString(), 'cantidad' => 40, 'peso_promedio_g' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertSame(540, $antiguo->fresh()->peces_vivos);
        $this->assertSame(300, $nuevo->fresh()->peces_vivos);
    }

    // --- Rango común del agua ---

    public function test_el_rango_comun_toma_lo_mas_exigente_de_cada_especie(): void
    {
        // Mojarra roja: 26-30 °C, O2 4, pH 6,8-8,2 · Cachama blanca: 25-30 °C, O2 3, pH 6,0-8,0
        $especies = [Especie::deClave('mojarra roja'), Especie::deClave('cachama blanca')];
        $rango = RangoAgua::para($especies);

        $this->assertSame([26.0, 30.0], $rango['temperatura']);
        $this->assertSame(4.0, $rango['oxigeno']);
        $this->assertSame([6.8, 8.0], $rango['ph']);
        $this->assertSame([], $rango['avisos']);

        // pH 8,1: dentro del rango de la mojarra, fuera del común; 25,5 °C: bien para la cachama, no para la mojarra
        $alertas = (new MedicionAgua(['temperatura_c' => 25.5, 'ph' => 8.1, 'oxigeno_mg_l' => 5]))->alertas($especies);
        $this->assertCount(2, $alertas);
        $this->assertStringContainsString('Temperatura de 25,5 °C fuera del rango de Mojarra roja y Cachama blanca (26 a 30 °C)', $alertas[0]);
        $this->assertStringContainsString('pH de 8,1 fuera del rango', $alertas[1]);
    }

    public function test_una_especie_sin_rangos_avisa_y_se_calcula_con_las_demas(): void
    {
        $sinRangos = Especie::create(['nombre' => 'Arawana', 'clima' => Especie::CALIDO]);
        $especies = [Especie::deClave('mojarra roja'), $sinRangos];

        $rango = RangoAgua::para($especies);
        $this->assertSame([26.0, 30.0], $rango['temperatura']);   // con la mojarra; no se inventa nada para la arawana
        $this->assertSame(['Arawana no tiene todos sus rangos de agua: se usaron los de las demás especies del lago.'], $rango['avisos']);
        $this->assertCount(1, (new MedicionAgua(['temperatura_c' => 20]))->alertas($especies));

        // Si ninguna tiene rangos: no se alerta y se avisa
        $solo = RangoAgua::para([$sinRangos]);
        $this->assertNull($solo['temperatura']);
        $this->assertContains('Ninguna especie del lago tiene rango de temperatura: ese dato no se alerta.', $solo['avisos']);
        $this->assertSame([], (new MedicionAgua(['temperatura_c' => 5, 'ph' => 3, 'oxigeno_mg_l' => 0.5]))->alertas([$sinRangos]));

        // El detalle del lago muestra los avisos
        $this->sembrar($sinRangos->id);
        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertOk()
            ->assertSee(__('lagos.rango.titulo'))->assertSee('Ninguna especie del lago tiene rango de temperatura');
    }

    public function test_frio_con_calido_no_tiene_rango_comun_de_temperatura(): void
    {
        $rango = RangoAgua::para([Especie::deClave('mojarra roja'), Especie::deClave('trucha arcoiris')]);

        $this->assertNull($rango['temperatura']);
        $this->assertContains('No hay rango común de temperatura entre las especies del lago: ese dato no se alerta.', $rango['avisos']);
        $this->assertSame(6.5, $rango['oxigeno']);   // el oxígeno sí tiene mínimo común (el más exigente)
    }

    // --- Ración ---

    public function test_sin_tipo_de_alimentacion_no_hay_racion_sugerida_y_se_pide_la_manual(): void
    {
        $sinAlimentacion = Especie::create(['nombre' => 'Arawana', 'clima' => Especie::CALIDO, 'temp_min' => 24, 'temp_max' => 30]);
        $this->sembrar($sinAlimentacion->id);
        $this->sembrar($this->especie('mojarra roja'), '2026-09-01', 1000);

        $racion = CalculadoraRacion::paraLago($this->lago->fresh());
        $this->assertCount(1, $racion['filas']);   // solo la mojarra
        $this->assertSame(['Arawana'], $racion['sin_sugerencia']);

        $this->actingAs($this->administrador)->get(route('admin.lagos.show', $this->lago))->assertOk()
            ->assertSee('Sin ración sugerida para Arawana');
    }

    // --- Guía y asistente ---

    public function test_la_guia_y_el_asistente_usan_las_especies_de_la_finca(): void
    {
        Especie::create($this->datosEspecie());
        Especie::deClave('bocachico')->update(['activo' => false]);
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        TenantActual::sinFiltro(fn () => (new Especie(['nombre' => 'Pez de la otra finca', 'clima' => 'calido']))->forceFill(['tenant_id' => $otra->id])->save());

        $instrucciones = ContextoFinca::instrucciones();
        $this->assertStringContainsString('- Sábalo (nombre científico: Prochilodus sp.). Clima: Agua cálida. Temperatura: 24 a 29 °C.', $instrucciones);
        $this->assertStringContainsString('Tiempo a cosecha: sin dato', $instrucciones);   // lo que falta, dicho: no se inventa
        $this->assertStringNotContainsString('Bocachico', $instrucciones);
        $this->assertStringNotContainsString('Pez de la otra finca', $instrucciones);

        $this->actingAs($this->administrador)->get(route('especies.index'))->assertOk()
            ->assertSee('Sábalo')->assertDontSee('Bocachico')->assertDontSee('Pez de la otra finca');
    }

    /** Pestañas de Especies (nav «Secciones») de la página: [ruta => ¿activa?]. */
    private function pestanasDe(string $html): array
    {
        if (! preg_match('#<nav[^<]*?aria-label="'.__('componentes.secciones').'"[^<]*?>(.*?)</nav>#s', $html, $nav)) {
            return [];
        }
        preg_match_all('#<a href="([^"]+)"\s*(aria-current="page")?#', $nav[1], $enlaces, PREG_SET_ORDER);

        return collect($enlaces)->mapWithKeys(fn ($e) => [$e[1] => ! empty($e[2] ?? '')])->all();
    }

    /** Enlaces del menú principal (sin las pestañas) hacia una URL, y cuántos de ellos están activos. */
    private function enMenu(string $html, string $url): array
    {
        $sinPestanas = preg_replace('#<nav[^<]*?aria-label="'.__('componentes.secciones').'"[^<]*?>.*?</nav>#s', '', $html);
        preg_match_all('#<a href="'.preg_quote($url, '#').'"\s*(aria-current="page")?#', $sinPestanas, $enlaces, PREG_SET_ORDER);

        return ['enlaces' => count($enlaces), 'activos' => collect($enlaces)->filter(fn ($e) => ! empty($e[1] ?? ''))->count()];
    }

    public function test_especies_es_una_sola_entrada_con_pestanas_guia_y_catalogo(): void
    {
        $guia = route('especies.index');
        $catalogo = route('admin.especies.index');

        foreach ([$this->propietario, $this->administrador] as $persona) {
            // En la guía: las dos pestañas, la guía activa
            $html = $this->actingAs($persona)->get($guia)->assertOk()->getContent();
            $this->assertSame([$guia => true, $catalogo => false], $this->pestanasDe($html));
            $this->assertStringContainsString(__('especies.pestanas.guia'), $html);
            $this->assertStringContainsString(__('especies.pestanas.catalogo'), $html);
            // En el catálogo: el catálogo activo
            $html = $this->actingAs($persona)->get($catalogo)->assertOk()->getContent();
            $this->assertSame([$guia => false, $catalogo => true], $this->pestanasDe($html));

            // El menú: ningún enlace propio al catálogo; la entrada «Especies» (la guía) activa en las dos pantallas
            foreach ([$guia, $catalogo] as $pantalla) {
                $html = $this->actingAs($persona)->get($pantalla)->getContent();
                $this->assertSame(0, $this->enMenu($html, $catalogo)['enlaces'], $pantalla);
                $menu = $this->enMenu($html, $guia);
                $this->assertGreaterThan(0, $menu['enlaces'], $pantalla);
                $this->assertSame($menu['enlaces'], $menu['activos'], $pantalla.': la entrada «Especies» queda activa');
            }
        }
    }

    public function test_el_trabajador_y_el_celador_van_directo_a_la_guia_sin_pestanas(): void
    {
        foreach ([Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([$rol]);
            $html = $this->actingAs($persona)->get(route('especies.index'))->assertOk()->getContent();
            $this->assertSame([], $this->pestanasDe($html), $rol);
            $this->assertStringNotContainsString(route('admin.especies.index'), $html, $rol);
            $this->assertGreaterThan(0, $this->enMenu($html, route('especies.index'))['activos'], $rol);
            $this->actingAs($persona)->get(route('admin.especies.index'))->assertForbidden();
        }
    }
}
