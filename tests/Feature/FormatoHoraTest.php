<?php

namespace Tests\Feature;

use App\Models\AdministradorPlataforma;
use App\Models\AvisoPesca;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Hora;
use App\Support\Reportes\ExportadorExcel;
use App\Support\Reportes\ExportadorPdf;
use App\Support\Reportes\Tabla;
use App\Support\TenantActual;
use App\Support\Traductor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/**
 * Formato de hora (12 «10:24 p. m.» o 24 «22:24»): persona → finca → país → 24. Solo cambia cómo se muestra la hora.
 * La tabla de casos (tests/fixtures/horas.json) es la misma de tests/js/hora.test.mjs.
 */
class FormatoHoraTest extends TestCase
{
    use RefreshDatabase;

    private const DURO = "\u{00A0}";

    private Tenant $finca;

    private User $propietario;

    private User $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        // 22:24 en Bogotá
        $this->travelTo(Carbon::parse('2026-10-15 22:24', 'America/Bogota'));
        $this->finca = Tenant::create(['nombre' => 'Finca', 'codigo' => 'F01', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = User::factory()->conRoles(Rol::PROPIETARIO)->create(['tenant_id' => $this->finca->id]);
        $this->administrador = User::factory()->conRoles(Rol::ADMINISTRADOR)->create(['tenant_id' => $this->finca->id]);
    }

    private function doce(string $hora, string $meridiano = 'p. m.'): string
    {
        return $hora.self::DURO.str_replace(' ', self::DURO, $meridiano);
    }

    public function test_cada_hora_de_la_tabla_compartida(): void
    {
        $casos = json_decode((string) file_get_contents(base_path('tests/fixtures/horas.json')), true)['casos'];
        foreach ($casos as $caso) {
            $instante = CarbonImmutable::create(2026, 10, 15, $caso['horas'], $caso['minutos']);
            $this->assertSame($caso['texto'], Hora::formatear($instante, 'H:i', $caso['formato']), "{$caso['horas']}:{$caso['minutos']} en {$caso['formato']}");
        }
        // Los textos del teléfono son los mismos del servidor
        $this->assertSame(__('region.am'), __('js.hora_am'));
        $this->assertSame(__('region.pm'), __('js.hora_pm'));
    }

    public function test_formatos_con_fecha_y_el_macro_de_carbon(): void
    {
        $instante = CarbonImmutable::create(2026, 10, 15, 22, 24, 5, 'America/Bogota');
        $this->assertSame('15/10/2026 '.$this->doce('10:24'), Hora::formatear($instante, 'd/m/Y H:i', '12'));
        $this->assertSame('15/10 '.$this->doce('10:24'), Hora::formatear($instante, 'd/m H:i', '12'));
        $this->assertSame('15/10/2026 '.$this->doce('10:24:05'), Hora::formatear($instante, 'd/m/Y H:i:s', '12'));
        $this->assertSame('15/10/2026 22:24', Hora::formatear($instante, 'd/m/Y H:i', '24'));
        $this->assertSame('15/10/2026', Hora::formatear($instante, 'd/m/Y', '12'));   // sin hora, igual que format()
        // El macro, con el formato de quien está en sesión (la finca de Colombia: 12 horas)
        $this->actingAs($this->propietario);
        foreach ([Carbon::instance($instante), \Carbon\Carbon::instance($instante), $instante] as $fecha) {
            $this->assertSame('15/10 '.$this->doce('10:24'), $fecha->formatoHora('d/m H:i'));
        }
        // La fecha y la zona no cambian: solo la escritura
        $this->assertSame('2026-10-15 22:24:05', $instante->format('Y-m-d H:i:s'));
    }

    public function test_valores_por_defecto_del_pais_y_de_la_finca(): void
    {
        $this->assertSame('12', Hora::delPais('CO'));
        $this->assertSame('12', Hora::delPais('MX'));
        $this->assertSame('24', Hora::delPais('ES'));
        $this->assertSame('24', Hora::delPais(null));   // finca sin país

        $this->assertSame('12', Hora::de($this->propietario));   // Colombia, nadie eligió
        $this->finca->update(['formato_hora' => '24']);
        $this->assertSame('24', Hora::de($this->propietario->fresh()));   // la finca le gana al país
        $this->propietario->forceFill(['formato_hora' => '12'])->save();
        $this->assertSame('12', Hora::de($this->propietario->fresh()));   // la persona le gana a la finca

        $sinPais = Tenant::create(['nombre' => 'Otra', 'codigo' => 'F02']);
        $this->assertSame('24', Hora::deFinca($sinPais));
    }

    public function test_cada_persona_elige_en_mi_cuenta(): void
    {
        $this->actingAs($this->propietario)->get(route('mi-cuenta.index'))->assertOk()
            ->assertSee(__('cuenta.formato_hora.de_la_finca', ['formato' => __('region.formatos_hora_corto.12')]));

        $this->actingAs($this->administrador)->put(route('mi-cuenta.formato-hora'), ['formato_hora' => '24'])->assertSessionHasNoErrors();
        $this->assertSame('24', $this->administrador->fresh()->formato_hora);
        $this->actingAs($this->administrador)->put(route('mi-cuenta.formato-hora'), ['formato_hora' => '7'])->assertSessionHasErrors('formato_hora');
        $this->actingAs($this->administrador)->put(route('mi-cuenta.formato-hora'), ['formato_hora' => ''])->assertSessionHasNoErrors();
        $this->assertNull($this->administrador->fresh()->formato_hora);   // vuelve al de la finca
    }

    public function test_solo_el_propietario_elige_el_de_la_finca_en_ajustes(): void
    {
        $datos = ['nombre' => 'Finca', 'pais' => 'CO', 'zona_horaria' => 'America/Bogota', 'idioma' => 'es'];
        $this->actingAs($this->administrador)->put(route('admin.ajustes.update'), $datos + ['formato_hora' => '24'])->assertForbidden();
        $this->actingAs($this->propietario)->get(route('admin.ajustes.edit'))->assertOk()
            ->assertSee(__('region.ajustes.formato_hora_del_pais', ['formato' => __('region.formatos_hora_corto.12')]));
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), $datos + ['formato_hora' => 'x'])->assertSessionHasErrors('formato_hora');
        $this->actingAs($this->propietario)->put(route('admin.ajustes.update'), $datos + ['formato_hora' => '24'])->assertSessionHasNoErrors();
        $this->assertSame('24', $this->finca->fresh()->formato_hora);
    }

    public function test_las_pantallas_muestran_la_hora_en_el_formato_de_cada_persona(): void
    {
        // La franja «sin conexión» de cada pantalla lleva la hora en que se generó (15/10 22:24 en Bogotá)
        $this->actingAs($this->propietario)->get(route('inicio'))->assertOk()
            ->assertSee('15/10 '.$this->doce('10:24'), false)
            ->assertSee('data-formato-hora="12"', false);
        $this->administrador->forceFill(['formato_hora' => '24'])->save();
        $this->actingAs($this->administrador->fresh())->get(route('inicio'))->assertOk()
            ->assertSee('15/10 22:24', false)
            ->assertDontSee($this->doce('10:24'), false)
            ->assertSee('data-formato-hora="24"', false);
    }

    public function test_el_paquete_sin_conexion_lleva_el_formato_de_la_persona(): void
    {
        $this->assertSame('12', $this->actingAs($this->administrador)->getJson(route('campo.paquete'))->assertOk()->json('formato_hora'));
        $this->administrador->forceFill(['formato_hora' => '24'])->save();
        $this->assertSame('24', $this->actingAs($this->administrador->fresh())->getJson(route('campo.paquete'))->json('formato_hora'));
    }

    public function test_excel_lleva_fechas_con_hora_de_verdad_y_pdf_el_texto(): void
    {
        $tabla = new Tabla('Avisos', [['Momento', null], ['Lago', null]], [[CarbonImmutable::create(2026, 10, 15, 22, 24, 0, 'America/Bogota'), 'L01']]);

        foreach (['12' => 'dd/mm/yyyy h:mm AM/PM', '24' => 'dd/mm/yyyy hh:mm'] as $formato => $codigo) {
            $ruta = tempnam(sys_get_temp_dir(), 'xls');
            Hora::con($formato, fn () => ExportadorExcel::generar([$tabla], ['Finca'], $ruta));
            $zip = new ZipArchive;
            $zip->open($ruta);
            $estilos = (string) $zip->getFromName('xl/styles.xml');
            $hoja = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            @unlink($ruta);

            // Formato de fecha y hora en Excel, y la celda es un número (no texto)
            $this->assertStringContainsString('formatCode="'.$codigo.'"', $estilos, $formato);
            $this->assertStringNotContainsString('15/10/2026', $hoja, $formato.': la hora no va como texto');
            $this->assertMatchesRegularExpression('#<v>46310\.93#', $hoja, $formato);   // 15/10/2026 22:24 como fecha de Excel
        }

        // PDF: el texto en el formato elegido
        $contexto = ['finca' => 'Finca', 'titulo' => 'Informe', 'periodo' => 'Octubre', 'generado' => 'hoy'];
        $this->assertStringContainsString('15/10/2026 '.$this->doce('10:24'), Hora::con('12', fn () => ExportadorPdf::html([$tabla], $contexto)));
        $this->assertStringContainsString('15/10/2026 22:24', Hora::con('24', fn () => ExportadorPdf::html([$tabla], $contexto)));
    }

    public function test_el_informe_mensual_lleva_las_horas_como_valores(): void
    {
        $lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        $siembra = Siembra::create(['lago_id' => $lago->id, 'especie_id' => $this->especie('mojarra roja'), 'fecha' => '2026-09-01',
            'cantidad_alevinos' => 10, 'peso_promedio_inicial_g' => 5, 'peces_vivos' => 10, 'peso_actual_g' => 300, 'biomasa_kg' => 3]);
        AvisoPesca::create(['lago_id' => $lago->id, 'siembra_id' => $siembra->id, 'user_id' => $this->propietario->id, 'estado' => AvisoPesca::PENDIENTE, 'reportado_at' => now()]);

        $archivo = $this->actingAs($this->propietario)->get(route('admin.reportes.mensual.excel', ['mes' => '2026-10']))->assertOk()->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $zip->open($archivo);
        $todo = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $todo .= (string) $zip->getFromIndex($i);
        }
        $zip->close();
        $this->assertStringContainsString('formatCode="dd/mm/yyyy h:mm AM/PM"', $todo);   // finca de Colombia: 12 horas
        // La hora del aviso es un valor de Excel (15/10/2026 22:24); como texto solo queda la línea «Generado…» del encabezado
        $this->assertMatchesRegularExpression('#<v>46310\.93#', $todo);
        $this->assertSame(1, substr_count($todo, '15/10/2026 10:24'));
        $this->assertStringNotContainsString('15/10/2026 22:24', $todo);
    }

    public function test_una_frase_que_termina_en_hora_no_deja_punto_doble(): void
    {
        $doce = Hora::formatear(CarbonImmutable::create(2026, 10, 16, 5, 24), 'H:i', '12');
        $veinticuatro = Hora::formatear(CarbonImmutable::create(2026, 10, 16, 5, 24), 'H:i', '24');
        // En 12 horas el punto de «a. m.» hace de punto final; en 24 horas la frase conserva su punto
        $this->assertSame('Aireador encendido desde las '.$this->doce('5:24', 'a. m.'), __('noche.aireador_encendido_desde', ['hora' => $doce]));
        $this->assertSame('Aireador encendido desde las 05:24.', __('noche.aireador_encendido_desde', ['hora' => $veinticuatro]));
        // También con la hora en negrita
        $this->assertSame('En turno hasta las <strong>'.$this->doce('5:24', 'a. m.').'</strong>', __('noche.turno_hasta', ['hora' => '<strong>'.$doce.'</strong>']));
        // Una abreviatura escrita con espacio normal (no es una hora de la app) no se toca
        $this->assertSame('Llega a las 5 p. m..', Traductor::sinPuntoDoble('Llega a las 5 p. m..'));
    }

    public function test_sin_finca_ni_persona_de_finca_se_usa_24_horas(): void
    {
        AdministradorPlataforma::create(['name' => 'Dueño', 'email' => 'dueno@software.test', 'password' => 'ClaveLarga12345']);
        app(TenantActual::class)->establecer(null);
        $this->assertSame('24', Hora::de());
    }

    public function test_ninguna_pantalla_escribe_la_hora_a_mano(): void
    {
        $permitidos = [
            // Valores de los campos de hora: el selector del aparato necesita H:i
            "\$editar->inicia_at->format('H:i')", "\$editar?->termina_at?->format('H:i')",
        ];
        $archivos = array_merge(glob(resource_path('views/{,*/,*/*/,*/*/*/}*.blade.php'), GLOB_BRACE), glob(app_path('{,*/,*/*/,*/*/*/}*.php'), GLOB_BRACE));
        foreach ($archivos as $archivo) {
            $codigo = str_replace($permitidos, '', (string) file_get_contents($archivo));
            $this->assertDoesNotMatchRegularExpression("/->format\\('(d\\/m\\/Y H:i|d\\/m H:i|H:i|d\\/m\\/Y H:i:s)'\\)/", $codigo, $archivo);
            // También dentro de una condición: ->format($x ? 'd/m H:i' : 'H:i')
            $this->assertDoesNotMatchRegularExpression("/->format\\([^;\\n]{0,80}?'(d\\/m(\\/Y)? )?H:i(:s)?'/", $codigo, $archivo);
        }
    }
}
