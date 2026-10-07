<?php

namespace Tests\Feature;

use App\Models\Lago;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Reportes\ExportadorPdf;
use App\Support\Reportes\InformeMensual;
use App\Support\TenantActual;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class ExportarReportesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $finca;

    private User $propietario;

    private Lago $lago;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00', 'America/Bogota'));

        // Otra finca con datos que nunca deben aparecer
        $otra = Tenant::create(['nombre' => 'Finca Ajena', 'codigo' => 'ajena']);
        app(TenantActual::class)->establecer($otra->id);
        $ajeno = tap(User::factory()->create(['tenant_id' => $otra->id]))->asignarRoles([Rol::PROPIETARIO]);
        $lagoAjeno = Lago::create(['nombre' => 'LagoAjeno', 'tipo' => 'tierra']);
        MovimientoAlimento::create(['user_id' => $ajeno->id, 'lago_id' => $lagoAjeno->id, 'fecha' => '2026-10-05', 'tipo_movimiento' => 'salida', 'tipo_concentrado' => 'ConcentradoAjeno', 'cantidad_kg' => 999]);

        $this->finca = Tenant::create(['nombre' => 'Finca Norte', 'codigo' => 'norte', 'pais' => 'CO']);
        app(TenantActual::class)->establecer($this->finca->id);
        $this->propietario = tap(User::factory()->create(['tenant_id' => $this->finca->id, 'name' => 'Marta']))->asignarRoles([Rol::PROPIETARIO]);
        $this->lago = Lago::create(['nombre' => 'L01', 'tipo' => 'tierra']);
        Siembra::create(['lago_id' => $this->lago->id, 'especie' => 'mojarra roja', 'fecha' => '2026-10-02', 'cantidad_alevinos' => 1500,
            'peso_promedio_inicial_g' => 5, 'peces_vivos' => 1500, 'peso_actual_g' => 5, 'biomasa_kg' => 7.5]);
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'lago_id' => $this->lago->id, 'fecha' => '2026-10-05', 'tipo_movimiento' => 'salida', 'tipo_concentrado' => 'Engorde', 'cantidad_kg' => 12.5]);
        MovimientoAlimento::create(['user_id' => $this->propietario->id, 'lago_id' => null, 'fecha' => '2026-10-03', 'tipo_movimiento' => 'entrada', 'tipo_concentrado' => 'Engorde',
            'cantidad_kg' => 400, 'proveedor' => 'Proveedor Uno', 'lote' => 'LT-9', 'bultos' => 10]);
        // 23:30 del 15 en la finca = 04:30 del 16 en UTC
        Ronda::create(['user_id' => $this->propietario->id, 'registrada_at' => '2026-10-15 23:30', 'tipo' => 'intrusion', 'descripcion' => 'Malla rota', 'lago_id' => $this->lago->id]);
    }

    /** @return array<string, list<list<mixed>>> hoja => filas */
    private function leerExcel(TestResponse $respuesta): array
    {
        $lector = new Reader;
        $lector->open($respuesta->baseResponse->getFile()->getPathname());
        $hojas = [];
        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $hojas[$hoja->getName()][] = $fila->toArray();
            }
        }
        $lector->close();

        return $hojas;
    }

    private function contiene(array $hojas, string $texto): bool
    {
        return str_contains(json_encode($hojas, JSON_UNESCAPED_UNICODE), $texto);
    }

    public function test_el_excel_mensual_trae_las_hojas_y_los_datos_de_la_finca(): void
    {
        $respuesta = $this->actingAs($this->propietario)->get(route('admin.reportes.mensual.excel', ['mes' => '2026-10']))->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('informe-mensual-2026-10-norte.xlsx', $respuesta->headers->get('Content-Disposition'));

        $hojas = $this->leerExcel($respuesta);
        $this->assertSame(['Resumen', 'Lagos', 'Alimento', 'Siembras', 'Cosechas', 'Traslados', 'Sanidad', 'Novedades', 'Entradas a bodega'], array_keys($hojas));

        // Encabezado con la finca y la hora de generación en la zona de la finca
        $this->assertSame('Finca Norte', $hojas['Resumen'][0][0]);
        $this->assertSame('Generado el 20/10/2026 10:00 por Marta.', $hojas['Resumen'][3][0]);

        // Los números van como números (se pueden sumar en Excel)
        $this->assertSame(['L01', 'Mojarra roja', 0, 12.5, 0, 7.5], $hojas['Lagos'][1]);
        // (el lector omite la fila vacía después del encabezado)
        $this->assertSame([__('reportes.exportar.peces_sembrados'), 1500], $hojas['Resumen'][5]);

        // La novedad sale con la hora de la finca, no la de UTC
        $this->assertSame(['15/10/2026 23:30', 'Intrusión o robo', 'L01', 'Malla rota'], $hojas['Novedades'][1]);
        $this->assertSame(['03/10/2026', 'Engorde', 400, 'Proveedor Uno', 'LT-9'], $hojas['Entradas a bodega'][1]);

        $this->assertFalse($this->contiene($hojas, 'LagoAjeno'));
        $this->assertFalse($this->contiene($hojas, 'ConcentradoAjeno'));
    }

    public function test_el_administrador_recibe_ventas_y_bodega_porque_las_opera(): void
    {
        $administrador = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([Rol::ADMINISTRADOR]);

        $hojas = $this->leerExcel($this->actingAs($administrador)->get(route('admin.reportes.mensual.excel', ['mes' => '2026-10']))->assertOk());

        $this->assertArrayHasKey('Entradas a bodega', $hojas);
        $this->assertTrue($this->contiene($hojas, __('reportes.exportar.ventas_total')));
        $this->assertTrue($this->contiene($hojas, 'Proveedor Uno'));
        $this->assertFalse($this->contiene($hojas, 'LagoAjeno'));
    }

    public function test_el_pdf_mensual_se_genera_con_los_datos_de_la_finca(): void
    {
        $respuesta = $this->actingAs($this->propietario)->get(route('admin.reportes.mensual.pdf', ['mes' => '2026-10']))->assertOk();
        $respuesta->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());
        $this->assertStringContainsString('informe-mensual-2026-10-norte.pdf', $respuesta->headers->get('Content-Disposition'));

        // El contenido del PDF sale de la misma plantilla: números con el formato del país e idioma de la finca
        $datos = InformeMensual::datos($this->propietario, CarbonImmutable::parse('2026-10-01', 'America/Bogota'));
        $html = ExportadorPdf::html(InformeMensual::tablas($datos), ['finca' => 'Finca Norte', 'titulo' => __('reportes.informe_mensual'), 'periodo' => 'Octubre de 2026', 'generado' => 'x']);
        $this->assertStringContainsString('<html lang="es-CO">', $html);
        $this->assertStringContainsString('1.500', $html);
        $this->assertStringContainsString('12,5', $html);
        $this->assertStringContainsString('15/10/2026 23:30', $html);
        $this->assertStringNotContainsString('LagoAjeno', $html);
    }

    public function test_libro_de_campo_en_excel_y_pdf_y_nunca_de_un_lago_ajeno(): void
    {
        $parametros = ['lago_id' => $this->lago->id, 'desde' => '2026-10-01', 'hasta' => '2026-10-20'];

        $hojas = $this->leerExcel($this->actingAs($this->propietario)->get(route('admin.reportes.libro-campo.excel', $parametros))->assertOk());
        $this->assertSame(['Siembras', 'Registro'], array_keys($hojas));
        $this->assertSame('Mojarra roja', $hojas['Siembras'][5][0]);
        $this->assertTrue($this->contiene($hojas, '12,5 kg de Engorde'));

        $pdf = $this->actingAs($this->propietario)->get(route('admin.reportes.libro-campo.pdf', $parametros))->assertOk();
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('libro-de-campo-l01-2026-10-01-2026-10-20-norte.pdf', $pdf->headers->get('Content-Disposition'));

        $lagoAjeno = Lago::withoutGlobalScopes()->where('nombre', 'LagoAjeno')->value('id');
        foreach (['admin.reportes.libro-campo.excel', 'admin.reportes.libro-campo.pdf'] as $ruta) {
            $this->actingAs($this->propietario)->get(route($ruta, ['lago_id' => $lagoAjeno]))->assertNotFound();
        }
    }

    public function test_el_personal_de_campo_no_puede_descargar(): void
    {
        foreach ([Rol::TRABAJADOR, Rol::CELADOR] as $rol) {
            $persona = tap(User::factory()->create(['tenant_id' => $this->finca->id]))->asignarRoles([$rol]);
            foreach (['admin.reportes.mensual.excel', 'admin.reportes.mensual.pdf'] as $ruta) {
                $this->actingAs($persona)->get(route($ruta))->assertForbidden();
            }
            $this->actingAs($persona)->get(route('admin.reportes.libro-campo.excel', ['lago_id' => $this->lago->id]))->assertForbidden();
        }
    }

    public function test_los_reportes_en_pantalla_ofrecen_las_descargas(): void
    {
        $this->actingAs($this->propietario)->get(route('admin.reportes.mensual', ['mes' => '2026-10']))->assertOk()
            ->assertSee(route('admin.reportes.mensual.excel', ['mes' => '2026-10']), false)
            ->assertSee(route('admin.reportes.mensual.pdf', ['mes' => '2026-10']), false);
    }
}
