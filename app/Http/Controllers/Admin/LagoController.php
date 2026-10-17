<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\Fase;
use App\Models\Lago;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Models\Tercero;
use App\Models\Tratamiento;
use App\Support\CalculadoraRacion;
use App\Support\EstadoFase;
use App\Support\HistorialLago;
use App\Support\NumeroLote;
use App\Support\RangoAgua;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LagoController extends Controller
{
    /** Eventos por tipo que se cargan para el historial del lago. */
    private const LIMITE_POR_TIPO = 30;

    public function index(Request $request): View
    {
        $lagos = Lago::query()
            // Con el origen de cada lote y los traslados que recibió (para agrupar por especie sin consultas de más)
            ->with(['fase', 'siembrasActivas.lagoOrigen', 'siembrasActivas.trasladosRecibidos.lagoOrigen', 'ultimaMedicion', 'avisoPescaPendiente.siembra', 'avisoPescaPendiente.usuario'])
            ->withExists('tratamientosEnRetiro as en_retiro')
            ->orderBy('nombre')
            ->get();

        return view('admin.lagos.index', [
            'lagos' => $lagos,
            // Ración de todos los lagos con las mismas consultas (no una por lago)
            'raciones' => CalculadoraRacion::paraLagos($lagos),
            'tipos' => Lago::opciones(),
            'fases' => $this->opcionesFases(),
            'especies' => $this->opcionesEspecies(),
            // Para la siembra: proveedores activos que proveen alevinos, y si el número de lote lo pone la app
            'proveedores' => Tercero::query()->queProveen(Tercero::PROVEE_ALEVINOS)->activos()->orderBy('nombres')->get()
                ->mapWithKeys(fn (Tercero $proveedor) => [$proveedor->id => $proveedor->nombreCompleto()])->all(),
            'numeracionAutomatica' => NumeroLote::esAutomatica(),
            // El trabajador solo puede alimentar y registrar mortalidad dentro de su turno
            'puedeRegistrar' => $request->user()->puedeTrabajarComo(Rol::TRABAJADOR),
            // El propietario sin rol de administrador solo consulta (sin botones de alimentar ni mortalidad)
            'soloLectura' => ! $request->user()->tieneRol(Rol::ADMINISTRADOR, Rol::TRABAJADOR),
            // «Listo para pescar»: solo el trabajador, dentro de su turno
            'puedeAvisarPesca' => $request->user()->tieneRol(Rol::TRABAJADOR) && $request->user()->turnoEnCurso(Rol::TRABAJADOR) !== null,
            'stocksDisponibles' => StockAlimento::query()->with('concentrado')->where('cantidad_kg', '>', 0)->orderBy('tipo_concentrado')->get(),
        ]);
    }

    public function show(Lago $lago): View
    {
        $lago->load(['fase.reglas', 'siembrasActivas.lagoOrigen', 'siembrasActivas.proveedor', 'siembrasActivas.trasladosRecibidos.lagoOrigen', 'ultimaMedicion', 'tratamientosEnRetiro']);

        return view('admin.lagos.show', [
            'lago' => $lago,
            'tipos' => Lago::opciones(),
            'fases' => $this->opcionesFases(),
            'densidadActual' => EstadoFase::densidadLago($lago),
            'racion' => CalculadoraRacion::paraLago($lago),
            'alertasAgua' => $lago->ultimaMedicion?->alertas($lago->siembrasActivas->pluck('especie')) ?? [],
            'avisosRango' => RangoAgua::para($lago->siembrasActivas->pluck('especie'))['avisos'],
            'historial' => HistorialLago::eventos($lago, limitePorTipo: self::LIMITE_POR_TIPO)->take(60),
            'siembrasFinalizadas' => $lago->siembras()->whereNotNull('finalizada_at')->latest('finalizada_at')->get(),
            'otrosLagos' => Lago::query()->whereKeyNot($lago->id)->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'vias' => Tratamiento::opciones(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        Lago::create($datos);

        return redirect()->route('admin.lagos.index')
            ->with('estado', __('mensajes.lago.registrado', ['nombre' => $datos['nombre']]));
    }

    public function update(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $this->validar($request, $lago);

        DB::transaction(function () use ($request, $lago, $datos) {
            $lago->update($datos);
            $faseNombre = $lago->fase()->value('nombre') ?? __('lagos.sin_fase');
            Bitacora::registrar($request->user(), 'lago_editado', $lago, [Rol::ADMINISTRADOR], [
                'detalle' => $faseNombre.($lago->area_m2 !== null ? ' · '.__('lagos.area_m2', ['area' => $lago->area_m2]) : ''),
            ]);
        });

        return redirect()->route('admin.lagos.show', $lago)
            ->with('estado', __('mensajes.lago.actualizado', ['nombre' => $lago->nombre]));
    }

    /** @return array{nombre: string, tipo: string, fase_id: ?int, area_m2: ?float} */
    private function validar(Request $request, ?Lago $lago = null): array
    {
        $tenantId = $request->user()->tenant_id;

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:255',
                // Un lago debe tener nombre único dentro del tenant
                Rule::unique('lagos')
                    ->where('tenant_id', $tenantId)
                    ->when($lago, fn ($regla) => $regla->ignore($lago->id)),
            ],
            'tipo' => ['required', 'string', Rule::in(Lago::TIPOS)],
            'fase_id' => [
                'nullable', 'integer',
                Rule::exists('fases', 'id')->where('tenant_id', $tenantId),
            ],
            'area_m2' => ['nullable', 'numeric', 'min:0.1', 'max:1000000'],
        ]);

        return [
            'nombre' => trim($datos['nombre']),
            'tipo' => $datos['tipo'],
            'fase_id' => ! empty($datos['fase_id']) ? (int) $datos['fase_id'] : null,
            'area_m2' => isset($datos['area_m2']) && $datos['area_m2'] !== '' ? (float) $datos['area_m2'] : null,
        ];
    }

    /** @return array<int|string, string> */
    private function opcionesFases(): array
    {
        return ['' => __('lagos.sin_fase')]
            + Fase::query()->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /** @return array<int, string> id => nombre de las especies activas del catálogo de la finca (las que se pueden sembrar) */
    private function opcionesEspecies(): array
    {
        return Especie::query()->activas()->orderBy('nombre')->pluck('nombre', 'id')->all();
    }
}
