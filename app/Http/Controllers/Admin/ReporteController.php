<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Abono;
use App\Models\Cosecha;
use App\Models\HoraExtra;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Siembra;
use App\Models\Tarea;
use App\Models\Traslado;
use App\Models\Tratamiento;
use App\Models\Venta;
use App\Support\HistorialLago;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reportes imprimibles (propietario y administrador): informe mensual y libro de campo por lago.
 * Se guardan como PDF desde el diálogo de impresión del navegador.
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
        $mes = $this->mes($request->query('mes'));
        $desde = $mes->toDateString();
        $hasta = $mes->endOfMonth()->toDateString();
        $enMes = fn ($consulta, string $columna = 'fecha') => $consulta->whereBetween($columna, [$desde, $hasta]);
        $enMesMomento = fn ($consulta, string $columna) => $consulta->whereBetween($columna, [$mes, $mes->endOfMonth()]);

        $mortalidadPorLago = $enMes(Mortalidad::query()->join('siembras', 'siembras.id', '=', 'mortalidades.siembra_id'), 'mortalidades.fecha')
            ->groupBy('siembras.lago_id')->selectRaw('siembras.lago_id, SUM(mortalidades.cantidad) AS total')->pluck('total', 'lago_id');
        $alimentoPorLago = $enMes(MovimientoAlimento::where('tipo_movimiento', 'salida'))
            ->groupBy('lago_id')->selectRaw('lago_id, SUM(cantidad_kg) AS total')->pluck('total', 'lago_id');
        $muestreosPorLago = $enMes(Muestreo::query()->join('siembras', 'siembras.id', '=', 'muestreos.siembra_id'), 'muestreos.fecha')
            ->groupBy('siembras.lago_id')->selectRaw('siembras.lago_id, COUNT(*) AS total')->pluck('total', 'lago_id');

        $lagos = Lago::query()->with('siembrasActivas')->orderBy('nombre')->get()->map(fn (Lago $lago) => [
            'lago' => $lago,
            'mortalidad' => (int) ($mortalidadPorLago[$lago->id] ?? 0),
            'alimento_kg' => (float) ($alimentoPorLago[$lago->id] ?? 0),
            'muestreos' => (int) ($muestreosPorLago[$lago->id] ?? 0),
        ]);

        $mediciones = $enMesMomento(MedicionAgua::query()->with(['lago.siembrasActivas']), 'medido_at')->get();
        $siembrasNuevas = $enMes(Siembra::query()->with('lago'))->orderBy('fecha')->get();

        return view('admin.reportes.mensual', [
            'mes' => $mes,
            'lagos' => $lagos,
            'siembrasNuevas' => $siembrasNuevas,
            'alimentoPorConcentrado' => $enMes(MovimientoAlimento::where('tipo_movimiento', 'salida'))
                ->groupBy('tipo_concentrado')->selectRaw('tipo_concentrado, SUM(cantidad_kg) AS total')->orderBy('tipo_concentrado')
                ->pluck('total', 'tipo_concentrado'),
            // Las entradas a bodega son información del propietario
            'entradasBodega' => $request->user()->tieneRol(Rol::PROPIETARIO)
                ? $enMes(MovimientoAlimento::where('tipo_movimiento', 'entrada'))->orderBy('fecha')->get()
                : null,
            'cosechas' => $enMes(Cosecha::query()->with(['lago', 'siembra']))->orderBy('fecha')->get(),
            // Las ventas (dinero) son información del propietario
            'ventas' => $request->user()->tieneRol(Rol::PROPIETARIO)
                ? [
                    'total' => (int) $enMes(Venta::query())->sum('total'),
                    'kg' => (float) $enMes(Venta::query())->sum('kg'),
                    'a_credito' => (int) $enMes(Venta::where('forma_pago', 'credito'))->sum('total'),
                    'abonos' => (int) $enMes(Abono::query())->sum('valor'),
                ]
                : null,
            'traslados' => $enMes(Traslado::query()->with(['lagoOrigen', 'lagoDestino', 'siembraOrigen']))->orderBy('fecha')->get(),
            'tratamientos' => $enMes(Tratamiento::query()->with('lago'), 'fecha_inicio')->orderBy('fecha_inicio')->get(),
            'mediciones' => $mediciones->count(),
            'medicionesConAlerta' => $mediciones->filter(fn (MedicionAgua $m) => $m->alertas($m->lago->siembrasActivas->pluck('especie')) !== [])->count(),
            'novedades' => $enMesMomento(Ronda::novedades()->with('lago'), 'registrada_at')->orderBy('registrada_at')->get(),
            'tareasCompletadas' => $enMesMomento(Tarea::query(), 'completada_at')->count(),
            'horasExtraAprobadas' => (float) $enMes(HoraExtra::query()->aprobadas())->sum('horas'),
        ]);
    }

    public function libroCampo(Request $request): View
    {
        $datos = $request->validate([
            'lago_id' => ['required', 'integer'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $lago = Lago::query()->findOrFail($datos['lago_id']);
        $hasta = CarbonImmutable::parse($datos['hasta'] ?? today());
        $desde = CarbonImmutable::parse($datos['desde'] ?? $hasta->subMonths(3));

        return view('admin.reportes.libro-campo', [
            'lago' => $lago,
            'desde' => $desde,
            'hasta' => $hasta,
            'siembras' => $lago->siembras()
                ->where('fecha', '<=', $hasta->toDateString())
                ->where(fn ($q) => $q->whereNull('finalizada_at')->orWhere('finalizada_at', '>=', $desde))
                ->orderBy('fecha')->get(),
            // El libro va en orden cronológico (del más antiguo al más reciente)
            'registros' => HistorialLago::eventos($lago, $desde, $hasta)->reverse()->values(),
        ]);
    }

    private function mes(?string $valor): CarbonImmutable
    {
        if ($valor !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $valor)) {
            return CarbonImmutable::createFromFormat('!Y-m', $valor)->startOfMonth();
        }

        return CarbonImmutable::today()->startOfMonth();
    }
}
