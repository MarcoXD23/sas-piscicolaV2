<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Models\Tratamiento;
use App\Support\CalculadoraRacion;
use App\Support\CatalogoEspecies;
use App\Support\HistorialLago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LagoController extends Controller
{
    /** Eventos por tipo que se cargan para el historial del lago. */
    private const LIMITE_POR_TIPO = 30;

    public function index(Request $request): View
    {
        $lagos = Lago::query()
            ->with(['siembrasActivas', 'ultimaMedicion'])
            ->withExists('tratamientosEnRetiro as en_retiro')
            ->orderBy('nombre')
            ->get();

        return view('admin.lagos.index', [
            'lagos' => $lagos,
            'raciones' => $lagos->mapWithKeys(fn (Lago $lago) => [$lago->id => CalculadoraRacion::paraLago($lago)]),
            'tipos' => Lago::opciones(),
            'especies' => $this->opcionesEspecies(),
            // El trabajador solo puede alimentar y registrar mortalidad dentro de su turno
            'puedeRegistrar' => $request->user()->puedeTrabajarComo(Rol::TRABAJADOR),
            // El propietario sin rol de administrador solo consulta (sin botones de alimentar ni mortalidad)
            'soloLectura' => ! $request->user()->tieneRol(Rol::ADMINISTRADOR, Rol::TRABAJADOR),
            'stocksDisponibles' => StockAlimento::query()->where('cantidad_kg', '>', 0)->orderBy('tipo_concentrado')->get(),
        ]);
    }

    public function show(Lago $lago): View
    {
        $lago->load(['siembrasActivas', 'ultimaMedicion', 'tratamientosEnRetiro']);

        return view('admin.lagos.show', [
            'lago' => $lago,
            'racion' => CalculadoraRacion::paraLago($lago),
            'alertasAgua' => $lago->ultimaMedicion?->alertas($lago->siembrasActivas->pluck('especie')) ?? [],
            'historial' => HistorialLago::eventos($lago, limitePorTipo: self::LIMITE_POR_TIPO)->take(60),
            'siembrasFinalizadas' => $lago->siembras()->whereNotNull('finalizada_at')->latest('finalizada_at')->get(),
            'otrosLagos' => Lago::query()->whereKeyNot($lago->id)->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'vias' => Tratamiento::opciones(),
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
            ->with('estado', __('mensajes.lago.registrado', ['nombre' => $datos['nombre']]));
    }

    /** @return array<string, string> clave => nombre de cada especie del catálogo */
    private function opcionesEspecies(): array
    {
        return collect(CatalogoEspecies::todas())->map(fn ($ficha) => $ficha['nombre'])->all();
    }
}
