<?php

namespace Tests\Feature;

use App\Models\Especie;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Asistente\ContextoFinca;
use App\Support\TenantActual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Ficha de cultivo y de procesamiento de cada especie (más color y aspecto): la edita el administrador,
 * la ven todos los roles y el asistente la usa sin inventar lo que falta.
 */
class EspeciesFichaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $administrador;

    private User $propietario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO]);
    }

    private function datos(Especie $especie, array $ficha): array
    {
        return ['nombre' => $especie->nombre, 'clima' => $especie->clima] + $ficha;
    }

    public function test_el_administrador_edita_la_ficha_y_todos_la_ven(): void
    {
        $especie = Especie::create(['nombre' => 'Sábalo', 'clima' => Especie::CALIDO]);

        $this->actingAs($this->administrador)->put(route('admin.especies.update', $especie), $this->datos($especie, [
            'cultivo' => ['preparacion' => 'Secar y encalar.', 'tipo_estanque' => 'Tierra, 1,5 m de profundidad.'],
            'procesamiento' => ['ayuno' => '24 horas.', 'empaque' => 'Bolsa con fecha.'],
            'color' => 'Plateado', 'aspecto' => 'Cuerpo alargado', 'ficha_orientativa' => '0',
        ]))->assertSessionHasNoErrors();

        $ficha = $especie->fresh()->ficha();
        $this->assertSame('Secar y encalar.', $ficha['cultivo']['preparacion']);
        $this->assertNull($ficha['cultivo']['siembra']);
        $this->assertSame('24 horas.', $ficha['procesamiento']['ayuno']);
        $this->assertSame(['Plateado', 'Cuerpo alargado', false], [$ficha['color'], $ficha['aspecto'], $ficha['orientativa']]);

        $celador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::CELADOR]);
        foreach ([$this->propietario, $this->administrador, $celador] as $persona) {
            $this->actingAs($persona)->get(route('especies.show', $especie))->assertOk()
                ->assertSee('Secar y encalar.')->assertSee('Tierra, 1,5 m de profundidad.')->assertSee('Bolsa con fecha.')
                ->assertSee('Plateado')->assertSee(__('especies.ficha.campos_procesamiento.fileteado'))
                ->assertSee(__('especies.sin_dato'))->assertDontSee(__('especies.ficha.orientativa'));
        }
    }

    public function test_solo_el_administrador_edita_la_ficha(): void
    {
        $especie = Especie::deClave('mojarra roja');
        $antes = $especie->cultivo;

        $this->actingAs($this->propietario)->get(route('admin.especies.edit', $especie))->assertForbidden();
        $this->actingAs($this->propietario)->put(route('admin.especies.update', $especie), $this->datos($especie, ['cultivo' => ['siembra' => 'Cambio']]))
            ->assertForbidden();
        $this->assertSame($antes, $especie->fresh()->cultivo);

        // El propietario que también es administrador sí
        $dueno = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::PROPIETARIO, Rol::ADMINISTRADOR]);
        $this->actingAs($dueno)->get(route('admin.especies.edit', $especie))->assertOk()->assertSee(__('especies.ficha.campos_cultivo.tipo_estanque'));
    }

    public function test_valida_las_partes_de_la_ficha(): void
    {
        $especie = Especie::deClave('mojarra roja');

        $this->actingAs($this->administrador)->put(route('admin.especies.update', $especie), $this->datos($especie, ['cultivo' => ['inventado' => 'x']]))
            ->assertInvalid(['cultivo']);
        $this->actingAs($this->administrador)->put(route('admin.especies.update', $especie), $this->datos($especie, ['procesamiento' => ['ayuno' => str_repeat('a', 2001)]]))
            ->assertInvalid(['procesamiento.ayuno' => 'máximo 2000 caracteres']);
    }

    public function test_una_especie_propia_arranca_con_la_ficha_vacia_y_se_muestra_sin_dato(): void
    {
        $especie = Especie::create(['nombre' => 'Arawana', 'clima' => Especie::CALIDO]);

        $this->assertFalse($especie->tieneFicha());
        $ficha = $especie->ficha();
        $this->assertSame(array_fill_keys(Especie::CAMPOS_CULTIVO, null), $ficha['cultivo']);
        $this->assertSame(array_fill_keys(Especie::CAMPOS_PROCESAMIENTO, null), $ficha['procesamiento']);
        $this->assertFalse($ficha['orientativa']);

        $html = $this->actingAs($this->administrador)->get(route('especies.show', $especie))->assertOk()->getContent();
        // 13 partes de la ficha + color y aspecto + datos de referencia que no tiene: todo «Sin dato»
        $this->assertGreaterThanOrEqual(15, substr_count($html, __('especies.sin_dato')));
    }

    public function test_las_especies_por_defecto_traen_contenido_orientativo_sin_leyes_ni_paises(): void
    {
        $this->actingAs($this->administrador)->get(route('especies.show', Especie::deClave('bocachico')))->assertOk()
            ->assertSee(__('especies.ficha.orientativa'))->assertSee(__('especies.ficha.campos_procesamiento.eviscerado'));

        foreach (Especie::query()->whereNotNull('clave')->get() as $especie) {
            $this->assertTrue($especie->ficha_orientativa);
            $texto = json_encode([$especie->cultivo, $especie->procesamiento, $especie->color, $especie->aspecto], JSON_UNESCAPED_UNICODE);
            $this->assertDoesNotMatchRegularExpression('/\b(ley|leyes|norma|normas|normativa|decreto|resoluci[oó]n|reglamento|invima|ica|senasa|colombia|m[eé]xico|per[uú]|ecuador|venezuela|sudam[eé]rica)\b/iu', $texto, $especie->clave);
        }
    }

    public function test_el_asistente_usa_la_ficha_y_dice_sin_dato_cuando_falta(): void
    {
        Especie::create(['nombre' => 'Arawana', 'clima' => Especie::CALIDO]);

        $instrucciones = ContextoFinca::instrucciones();

        // Especie por defecto: su ficha va completa
        $this->assertStringContainsString('Eviscerado: Abrir el vientre desde el ano hasta las agallas', $instrucciones);
        $this->assertStringContainsString('Color: Rojo, rosado o anaranjado', $instrucciones);
        // Especie propia sin ficha: cada parte va como «sin dato» (no se inventa)
        $linea = collect(explode("\n- ", $instrucciones))->first(fn ($bloque) => str_starts_with($bloque, 'Arawana'));
        $this->assertNotNull($linea);
        $this->assertStringContainsString('Color: sin dato. Aspecto: sin dato.', $linea);
        $this->assertStringContainsString('Cultivo — Preparación del estanque: sin dato; Siembra: sin dato', $linea);
        $this->assertStringContainsString('Procesamiento — Ayuno previo: sin dato', $linea);
        $this->assertStringContainsString(__('asistente.avisos.dato_ausente'), $instrucciones);
    }

    public function test_la_migracion_llena_solo_las_especies_por_defecto(): void
    {
        $propia = Especie::create(['nombre' => 'Arawana', 'clima' => Especie::CALIDO]);
        $migracion = require database_path('migrations/2026_10_25_000001_add_ficha_to_especies.php');
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('especies', 'cultivo'));

        $migracion->up();

        $mojarra = Especie::deClave('mojarra roja');
        $this->assertTrue($mojarra->ficha_orientativa);
        $this->assertSame('24 a 36 horas sin alimento antes de la cosecha, para vaciar el intestino.', $mojarra->procesamiento['ayuno']);
        $this->assertFalse($propia->fresh()->tieneFicha());
        $this->assertFalse($propia->fresh()->ficha_orientativa);
    }

    public function test_aislamiento_no_se_edita_ni_se_ve_la_ficha_de_otra_finca(): void
    {
        $otra = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $ajena = TenantActual::sinFiltro(fn () => Especie::where('tenant_id', $otra->id)->where('clave', 'mojarra roja')->sole());

        $this->actingAs($this->administrador)->put(route('admin.especies.update', $ajena), $this->datos($ajena, ['color' => 'Azul']))->assertNotFound();
        $this->actingAs($this->administrador)->get(route('especies.show', $ajena))->assertNotFound();
        $this->assertNotSame('Azul', TenantActual::sinFiltro(fn () => $ajena->fresh()->color));
    }
}
