<?php

namespace App\Http\Controllers\Noche;

use App\Http\Controllers\Controller;
use App\Models\Aireacion;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Rol;
use App\Models\Ronda;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Módulo Seguridad & Noche: estado de los lagos (oxígeno y aireadores) y bitácora de la noche.
 */
class NocheController extends Controller
{
    /** Horas hacia atrás que muestra la bitácora. */
    public const HORAS_BITACORA = 14;

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $lagos = Lago::query()->with(['siembrasActivas', 'ultimaMedicion', 'aireacionActiva'])->orderBy('nombre')->get();

        return view('noche.index', [
            'lagos' => $lagos,
            'alertasAgua' => $lagos->mapWithKeys(fn (Lago $lago) => [
                $lago->id => $lago->ultimaMedicion?->alertas($lago->siembrasActivas->pluck('especie')) ?? [],
            ]),
            'bitacora' => $this->bitacora(),
            'tipos' => Ronda::TIPOS,
            'puedeRegistrar' => $usuario->puedeTrabajarComo(Rol::CELADOR),
            'turno' => $usuario->turnoEnCurso(Rol::CELADOR),
        ]);
    }

    /** @return Collection<int, array{momento: \Carbon\CarbonInterface, icono: string, titulo: string, detalle: string, tono: string}> */
    private function bitacora(): Collection
    {
        $desde = now()->subHours(self::HORAS_BITACORA);
        $eventos = collect();
        $numero = fn ($valor) => str_replace('.', ',', (string) round((float) $valor, 2));
        $unir = fn (?string ...$partes) => implode(' · ', array_filter($partes, fn ($parte) => $parte !== null && $parte !== ''));

        Ronda::with(['usuario', 'lago'])->where('registrada_at', '>=', $desde)->get()
            ->each(fn (Ronda $ronda) => $eventos->push([
                'momento' => $ronda->registrada_at,
                'icono' => $ronda->esNovedad() ? 'alerta' : 'ok',
                'titulo' => $unir($ronda->textoTipo(), $ronda->lago?->nombre),
                'detalle' => $unir($ronda->descripcion, $ronda->usuario?->name),
                'tono' => $ronda->esNovedad() ? 'alerta' : 'positivo',
            ]));

        Aireacion::with(['lago', 'usuario', 'apagadoPor'])
            ->where(fn ($consulta) => $consulta->where('encendido_at', '>=', $desde)->orWhere('apagado_at', '>=', $desde))
            ->get()
            ->each(function (Aireacion $aireacion) use ($eventos, $desde, $unir) {
                if ($aireacion->encendido_at->gte($desde)) {
                    $eventos->push(['momento' => $aireacion->encendido_at, 'icono' => 'aireador', 'tono' => 'neutro',
                        'titulo' => "Aireador encendido · {$aireacion->lago->nombre}",
                        'detalle' => $unir($aireacion->motivo, $aireacion->usuario?->name)]);
                }
                if ($aireacion->apagado_at?->gte($desde)) {
                    $eventos->push(['momento' => $aireacion->apagado_at, 'icono' => 'aireador', 'tono' => 'neutro',
                        'titulo' => "Aireador apagado · {$aireacion->lago->nombre}",
                        'detalle' => $unir($aireacion->apagadoPor?->name)]);
                }
            });

        MedicionAgua::with(['lago', 'usuario'])->where('medido_at', '>=', $desde)->get()
            ->each(fn (MedicionAgua $medicion) => $eventos->push([
                'momento' => $medicion->medido_at,
                'icono' => 'reloj',
                'titulo' => "Medición · {$medicion->lago->nombre}",
                'detalle' => $unir(
                    $medicion->oxigeno_mg_l !== null ? "oxígeno {$numero($medicion->oxigeno_mg_l)} mg/L" : null,
                    $medicion->temperatura_c !== null ? "{$numero($medicion->temperatura_c)} °C" : null,
                    $medicion->usuario?->name,
                ),
                'tono' => 'neutro',
            ]));

        return $eventos->sortByDesc(fn ($evento) => $evento['momento']->timestamp)->values();
    }
}
