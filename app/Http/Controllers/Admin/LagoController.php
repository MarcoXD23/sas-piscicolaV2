<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\MedicionAgua;
use App\Models\Mortalidad;
use App\Models\MovimientoAlimento;
use App\Models\Muestreo;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Models\Traslado;
use App\Models\Tratamiento;
use App\Support\CatalogoEspecies;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LagoController extends Controller
{
    /** Eventos por tipo que se cargan para el historial del lago. */
    private const LIMITE_POR_TIPO = 30;

    public function index(Request $request): View
    {
        $lagos = Lago::query()
            ->with('siembrasActivas')
            ->withExists('tratamientosEnRetiro as en_retiro')
            ->orderBy('nombre')
            ->get();

        return view('admin.lagos.index', [
            'lagos' => $lagos,
            'tipos' => Lago::TIPOS,
            'especies' => $this->opcionesEspecies(),
            // El trabajador solo puede alimentar y registrar mortalidad dentro de su turno
            'puedeRegistrar' => $request->user()->puedeTrabajarComo(Rol::TRABAJADOR),
            'stocksDisponibles' => StockAlimento::query()->where('cantidad_kg', '>', 0)->orderBy('tipo_concentrado')->get(),
        ]);
    }

    public function show(Lago $lago): View
    {
        $lago->load(['siembrasActivas', 'ultimaMedicion', 'tratamientosEnRetiro']);

        return view('admin.lagos.show', [
            'lago' => $lago,
            'alertasAgua' => $lago->ultimaMedicion?->alertas($lago->siembrasActivas->pluck('especie')) ?? [],
            'historial' => $this->historial($lago),
            'siembrasFinalizadas' => $lago->siembras()->whereNotNull('finalizada_at')->latest('finalizada_at')->get(),
            'otrosLagos' => Lago::query()->whereKeyNot($lago->id)->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'vias' => Tratamiento::VIAS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:255',
                // Un lago debe tener nombre único dentro del tenant
                Rule::unique('lagos')->where('tenant_id', $request->user()->tenant_id),
            ],
            'tipo' => ['required', 'string', Rule::in(Lago::TIPOS)],
        ]);

        Lago::create($datos);

        return redirect()->route('admin.lagos.index')
            ->with('estado', "Lago «{$datos['nombre']}» registrado.");
    }

    /** @return array<string, string> clave => nombre de cada especie del catálogo */
    private function opcionesEspecies(): array
    {
        return collect(CatalogoEspecies::todas())->map(fn ($ficha) => $ficha['nombre'])->all();
    }

    /**
     * Línea de tiempo del lago: siembras, muestreos, mortalidad, alimentación, traslados, agua y sanidad.
     *
     * @return Collection<int, array{fecha: \Carbon\CarbonInterface, orden: string, icono: string, titulo: string, detalle: string, tono: string}>
     */
    private function historial(Lago $lago): Collection
    {
        $numero = fn ($valor, $decimales = 0) => number_format((float) $valor, $decimales, ',', '.');
        $siembras = $lago->siembras()->get()->keyBy('id');
        $ids = $siembras->keys();
        $eventos = collect();
        $agregar = function ($fecha, $orden, string $icono, string $titulo, string $detalle = '', string $tono = 'neutro') use ($eventos) {
            $eventos->push(['fecha' => $fecha, 'orden' => (string) $orden, 'icono' => $icono, 'titulo' => $titulo, 'detalle' => $detalle, 'tono' => $tono]);
        };

        foreach ($siembras as $siembra) {
            $agregar($siembra->fecha, $siembra->created_at, 'pez', "Siembra de {$siembra->nombre_especie}",
                "{$numero($siembra->cantidad_alevinos)} peces de {$numero($siembra->peso_promedio_inicial_g, 2)} g", 'positivo');

            if ($siembra->finalizada_at) {
                $agregar($siembra->finalizada_at, $siembra->finalizada_at, 'ok', "Fin de la siembra de {$siembra->nombre_especie}",
                    "{$numero($siembra->peces_vivos)} peces al cierre");
            }
        }

        Muestreo::whereIn('siembra_id', $ids)->with('usuario')->latest('fecha')->limit(self::LIMITE_POR_TIPO)->get()
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'biomasa', 'Muestreo · '.$siembras[$m->siembra_id]->nombre_especie,
                "{$numero($m->peces_pesados)} peces, promedio {$numero($m->peso_promedio_g, 2)} g · {$m->usuario?->name}"));

        Mortalidad::whereIn('siembra_id', $ids)->with('usuario')->latest('fecha')->limit(self::LIMITE_POR_TIPO)->get()
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'mortalidad', 'Mortalidad · '.$siembras[$m->siembra_id]->nombre_especie,
                "{$numero($m->cantidad)} peces · {$m->usuario?->name}", 'alerta'));

        MovimientoAlimento::where('lago_id', $lago->id)->where('tipo_movimiento', 'salida')->with('usuario')
            ->latest('fecha')->limit(self::LIMITE_POR_TIPO)->get()
            ->each(fn ($m) => $agregar($m->fecha, $m->created_at, 'alimentar', 'Alimentación',
                "{$numero($m->cantidad_kg, 1)} kg de {$m->tipo_concentrado} · {$m->usuario?->name}"));

        Traslado::where(fn ($q) => $q->where('lago_origen_id', $lago->id)->orWhere('lago_destino_id', $lago->id))
            ->with(['lagoOrigen', 'lagoDestino', 'siembraOrigen'])->latest('fecha')->limit(self::LIMITE_POR_TIPO)->get()
            ->each(function (Traslado $t) use ($agregar, $lago, $numero) {
                $sale = $t->lago_origen_id === $lago->id;
                $agregar($t->fecha, $t->created_at, 'lagos',
                    $sale ? "Traslado hacia {$t->lagoDestino->nombre}" : "Traslado desde {$t->lagoOrigen->nombre}",
                    "{$numero($t->cantidad)} peces de {$t->siembraOrigen->nombre_especie} ({$numero($t->biomasa_kg, 2)} kg)");
            });

        MedicionAgua::where('lago_id', $lago->id)->latest('medido_at')->limit(self::LIMITE_POR_TIPO)->get()
            ->each(function (MedicionAgua $m) use ($agregar, $numero) {
                $partes = array_filter([
                    $m->temperatura_c !== null ? "{$numero($m->temperatura_c, 1)} °C" : null,
                    $m->oxigeno_mg_l !== null ? "oxígeno {$numero($m->oxigeno_mg_l, 2)} mg/L" : null,
                    $m->ph !== null ? "pH {$numero($m->ph, 2)}" : null,
                    $m->amonio_mg_l !== null ? "amonio {$numero($m->amonio_mg_l, 2)} mg/L" : null,
                    $m->nitritos_mg_l !== null ? "nitritos {$numero($m->nitritos_mg_l, 2)} mg/L" : null,
                    $m->transparencia_cm !== null ? "transparencia {$m->transparencia_cm} cm" : null,
                ]);
                $agregar($m->medido_at, $m->medido_at, 'reloj', 'Calidad del agua · '.$m->medido_at->format('H:i'), implode(' · ', $partes));
            });

        Tratamiento::where('lago_id', $lago->id)->latest('fecha_inicio')->limit(self::LIMITE_POR_TIPO)->get()
            ->each(fn (Tratamiento $t) => $agregar($t->fecha_inicio, $t->created_at, 'alerta', "Tratamiento: {$t->motivo}",
                "{$t->producto} ({$t->dosis}, vía {$t->via}) hasta el {$t->fecha_fin->format('d/m/Y')}"
                .($t->dias_retiro > 0 ? " · retiro hasta el {$t->finRetiro()->format('d/m/Y')}" : ''), 'alerta'));

        return $eventos
            ->sortByDesc(fn ($e) => $e['fecha']->format('Y-m-d').' '.$e['orden'])
            ->values()
            ->take(60);
    }
}
