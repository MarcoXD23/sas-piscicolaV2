<?php

namespace App\Http\Controllers\Noche;

use App\Http\Controllers\Controller;
use App\Models\Aireacion;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Ronda;
use App\Models\Turno;
use App\Support\Formato;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Módulo Seguridad & Noche: estado de los lagos (oxígeno y aireadores) y registro de la noche.
 * El celador registra en su turno; propietario y administrador lo consultan en solo lectura,
 * con los reportes de cada turno de celador (rondas, novedades, aireadores y oxígeno).
 */
class NocheController extends Controller
{
    /** Horas hacia atrás que muestra la bitácora. */
    public const HORAS_BITACORA = 14;

    /** Turnos de celador que se pueden consultar en «Reportes por turno». */
    public const TURNOS_REPORTE = 14;

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $soloLectura = ! $usuario->tieneRol(Rol::CELADOR);
        $lagos = Lago::query()->with(['siembrasActivas', 'ultimaMedicion', 'aireacionActiva'])->orderBy('nombre')->get();

        return view('noche.index', [
            'lagos' => $lagos,
            'alertasAgua' => $lagos->mapWithKeys(fn (Lago $lago) => [
                $lago->id => $lago->ultimaMedicion?->alertas($lago->siembrasActivas->pluck('especie')) ?? [],
            ]),
            'bitacora' => $this->bitacora(now()->subHours(self::HORAS_BITACORA), now()),
            'tipos' => Ronda::opciones(),
            'puedeRegistrar' => $usuario->puedeTrabajarComo(Rol::CELADOR),
            'turno' => $usuario->turnoEnCurso(Rol::CELADOR),
            'soloLectura' => $soloLectura,
        ] + ($soloLectura ? $this->reportesPorTurno($request) : []));
    }

    /** Propietario y administrador: lo que registró el celador en un turno (por defecto, el más reciente). */
    private function reportesPorTurno(Request $request): array
    {
        $turnos = Turno::query()->with('user')->where('rol', Rol::CELADOR)->where('inicia_at', '<=', now())
            ->latest('inicia_at')->limit(self::TURNOS_REPORTE)->get();
        $elegido = $turnos->firstWhere('id', (int) $request->query('turno')) ?? $turnos->first();

        return [
            'turnosCelador' => $turnos,
            'turnoElegido' => $elegido,
            'reporteTurno' => $elegido ? $this->bitacora($elegido->inicia_at, $elegido->termina_at->min(now())) : collect(),
        ];
    }

    /** @return Collection<int, array{momento: CarbonInterface, icono: string, titulo: string, detalle: string, tono: string}> */
    private function bitacora(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        $eventos = collect();
        $numero = fn ($valor) => rtrim(rtrim(Formato::numero((float) $valor, 2), '0'), ',.');
        $unir = fn (?string ...$partes) => implode(' · ', array_filter($partes, fn ($parte) => $parte !== null && $parte !== ''));

        Ronda::with(['usuario', 'lago'])->whereBetween('registrada_at', [$desde, $hasta])->get()
            ->each(fn (Ronda $ronda) => $eventos->push([
                'momento' => $ronda->registrada_at,
                'icono' => $ronda->esNovedad() ? 'alerta' : 'ok',
                'titulo' => $unir($ronda->textoTipo(), $ronda->lago?->nombre),
                'detalle' => $unir($ronda->descripcion, $ronda->usuario?->name),
                'tono' => $ronda->esNovedad() ? 'alerta' : 'positivo',
            ]));

        Aireacion::with(['lago', 'usuario', 'apagadoPor'])
            ->where(fn ($consulta) => $consulta->whereBetween('encendido_at', [$desde, $hasta])->orWhereBetween('apagado_at', [$desde, $hasta]))
            ->get()
            ->each(function (Aireacion $aireacion) use ($eventos, $desde, $hasta, $unir) {
                if ($aireacion->encendido_at->between($desde, $hasta)) {
                    $eventos->push(['momento' => $aireacion->encendido_at, 'icono' => 'aireador', 'tono' => 'neutro',
                        'titulo' => __('noche.aireador_encendido_en', ['lago' => $aireacion->lago->nombre]),
                        'detalle' => $unir($aireacion->motivo, $aireacion->usuario?->name)]);
                }
                if ($aireacion->apagado_at?->between($desde, $hasta)) {
                    $eventos->push(['momento' => $aireacion->apagado_at, 'icono' => 'aireador', 'tono' => 'neutro',
                        'titulo' => __('noche.aireador_apagado_en', ['lago' => $aireacion->lago->nombre]),
                        'detalle' => $unir($aireacion->apagadoPor?->name)]);
                }
            });

        MedicionAgua::with(['lago', 'usuario'])->whereBetween('medido_at', [$desde, $hasta])->get()
            ->each(fn (MedicionAgua $medicion) => $eventos->push([
                'momento' => $medicion->medido_at,
                'icono' => 'reloj',
                'titulo' => __('noche.medicion_de', ['lago' => $medicion->lago->nombre]),
                'detalle' => $unir(
                    $medicion->oxigeno_mg_l !== null ? __('noche.oxigeno_valor', ['valor' => $numero($medicion->oxigeno_mg_l)]) : null,
                    $medicion->temperatura_c !== null ? "{$numero($medicion->temperatura_c)} °C" : null,
                    $medicion->usuario?->name,
                ),
                'tono' => 'neutro',
            ]));

        return $eventos->sortByDesc(fn ($evento) => $evento['momento']->timestamp)->values();
    }
}
