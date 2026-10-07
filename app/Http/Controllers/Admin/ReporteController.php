<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Support\Region;
use App\Support\Reportes\ExportadorExcel;
use App\Support\Reportes\ExportadorPdf;
use App\Support\Reportes\InformeMensual;
use App\Support\Reportes\LibroCampo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Reportes imprimibles (propietario y administrador): informe mensual y libro de campo por lago.
 * Se ven en pantalla (imprimibles) y se descargan en Excel y PDF con los mismos datos (App\Support\Reportes).
 */
class ReporteController extends Controller
{
    public function index(): View
    {
        return view('admin.reportes.index', [
            'lagos' => Lago::query()->orderBy('nombre')->pluck('nombre', 'id')->all(),
        ]);
    }

    public function mensual(Request $request): View
    {
        return view('admin.reportes.mensual', InformeMensual::datos($request->user(), $this->mes($request->query('mes'))));
    }

    public function mensualExcel(Request $request): BinaryFileResponse
    {
        $datos = InformeMensual::datos($request->user(), $this->mes($request->query('mes')));

        return ExportadorExcel::descargar(InformeMensual::tablas($datos), $this->lineasEncabezado($request, $this->contextoMensual($request, $datos)),
            $this->nombreArchivo($request, 'informe-mensual-'.$datos['mes']->format('Y-m'), 'xlsx'));
    }

    public function mensualPdf(Request $request): Response
    {
        $datos = InformeMensual::datos($request->user(), $this->mes($request->query('mes')));

        return ExportadorPdf::descargar(InformeMensual::tablas($datos), $this->contextoMensual($request, $datos),
            $this->nombreArchivo($request, 'informe-mensual-'.$datos['mes']->format('Y-m'), 'pdf'));
    }

    public function libroCampo(Request $request): View
    {
        return view('admin.reportes.libro-campo', $this->datosLibroCampo($request));
    }

    public function libroCampoExcel(Request $request): BinaryFileResponse
    {
        $datos = $this->datosLibroCampo($request);

        return ExportadorExcel::descargar(LibroCampo::tablas($datos), $this->lineasEncabezado($request, $this->contextoLibroCampo($request, $datos)),
            $this->nombreArchivo($request, 'libro-de-campo-'.Str::slug($datos['lago']->nombre).'-'.$datos['desde']->format('Y-m-d').'-'.$datos['hasta']->format('Y-m-d'), 'xlsx'));
    }

    public function libroCampoPdf(Request $request): Response
    {
        $datos = $this->datosLibroCampo($request);

        return ExportadorPdf::descargar(LibroCampo::tablas($datos), $this->contextoLibroCampo($request, $datos),
            $this->nombreArchivo($request, 'libro-de-campo-'.Str::slug($datos['lago']->nombre).'-'.$datos['desde']->format('Y-m-d').'-'.$datos['hasta']->format('Y-m-d'), 'pdf'),
            'landscape');
    }

    /** @return array<string, mixed> */
    private function datosLibroCampo(Request $request): array
    {
        $datos = $request->validate([
            'lago_id' => ['required', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        // El global scope de la finca hace que un lago de otra finca no se encuentre (404)
        $lago = Lago::query()->findOrFail($datos['lago_id']);
        $hasta = CarbonImmutable::parse($datos['hasta'] ?? Region::hoy()->toDateString(), Region::zona());
        $desde = CarbonImmutable::parse($datos['desde'] ?? $hasta->subMonths(3)->toDateString(), Region::zona());

        return LibroCampo::datos($lago, $desde, $hasta);
    }

    /** @return array{finca: string, titulo: string, periodo: string, generado: string, notas: list<string>} */
    private function contextoMensual(Request $request, array $datos): array
    {
        return [
            'finca' => $request->user()->tenant->nombre,
            'titulo' => __('reportes.informe_mensual'),
            'periodo' => ucfirst($datos['mes']->translatedFormat(__('comun.formatos.mes_anio'))),
            'generado' => $this->generado($request),
            'notas' => [__('region.aviso_trazabilidad')],
        ];
    }

    /** @return array{finca: string, titulo: string, periodo: string, generado: string, notas: list<string>} */
    private function contextoLibroCampo(Request $request, array $datos): array
    {
        return [
            'finca' => $request->user()->tenant->nombre,
            'titulo' => __('reportes.libro_campo_lago', ['lago' => $datos['lago']->nombre]),
            'periodo' => __('reportes.periodo_libro', ['desde' => $datos['desde']->format('d/m/Y'), 'hasta' => $datos['hasta']->format('d/m/Y'), 'tipo' => __('lagos.tipos.'.$datos['lago']->tipo)]),
            'generado' => $this->generado($request),
            'notas' => [__('region.aviso_libro_campo')],
        ];
    }

    private function generado(Request $request): string
    {
        return __('componentes.generado_el', ['momento' => Region::ahora()->format('d/m/Y H:i'), 'nombre' => $request->user()->name]);
    }

    /** @return list<string> */
    private function lineasEncabezado(Request $request, array $contexto): array
    {
        return [$contexto['finca'], $contexto['titulo'], $contexto['periodo'], $contexto['generado']];
    }

    private function nombreArchivo(Request $request, string $base, string $extension): string
    {
        return $base.'-'.Str::slug($request->user()->tenant->codigo).'.'.$extension;
    }

    private function mes(?string $valor): CarbonImmutable
    {
        if ($valor !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return CarbonImmutable::createFromFormat('!Y-m', $valor, Region::zona())->startOfMonth();
        }

        return Region::hoy()->startOfMonth();
    }
}
