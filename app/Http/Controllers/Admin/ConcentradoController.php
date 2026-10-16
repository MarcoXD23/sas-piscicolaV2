<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Concentrado;
use App\Models\DespachoAlimento;
use App\Models\Especie;
use App\Models\MovimientoAlimento;
use App\Models\Rol;
use App\Models\StockAlimento;
use App\Models\TablaRacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Catálogo de concentrados de la finca y tabla de ración configurable por especie (Ajustes › Concentrados y ración, solo propietario).
 */
class ConcentradoController extends Controller
{
    public function index(): View
    {
        $concentrados = Concentrado::query()
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        $especies = Especie::query()
            ->with(['tablaRacion.concentrado'])
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return view('admin.concentrados.index', [
            'concentrados' => $concentrados,
            'especies' => $especies,
            'opcionesConcentrados' => ['' => __('concentrados.sin_concentrado_fijo')]
                + $concentrados->where('activo', true)->mapWithKeys(fn (Concentrado $c) => [$c->id => $c->etiqueta()])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarConcentrado($request);

        $concentrado = DB::transaction(function () use ($request, $datos) {
            $creado = Concentrado::create($datos);

            // Si ya había registros en bodega con ese nombre en la finca, quedan ligados al catálogo (su texto no cambia)
            foreach ([StockAlimento::class, MovimientoAlimento::class, DespachoAlimento::class] as $modelo) {
                $modelo::query()
                    ->whereNull('concentrado_id')
                    ->whereRaw('LOWER(tipo_concentrado) = ?', [mb_strtolower($creado->nombre)])
                    ->update(['concentrado_id' => $creado->id]);
            }

            Bitacora::registrar($request->user(), 'concentrado_creado', null, [Rol::PROPIETARIO], [
                'concentrado' => $creado->etiqueta(),
            ]);

            return $creado;
        });

        return redirect()->route('admin.concentrados.index')
            ->with('estado', __('mensajes.concentrado.creado', ['nombre' => $concentrado->nombre]));
    }

    public function update(Request $request, Concentrado $concentrado): RedirectResponse
    {
        $datos = $this->validarConcentrado($request, $concentrado);

        // Renombrar no cambia la historia: la bodega, los movimientos y los despachos guardados conservan su texto y
        // siguen ligados por concentrado_id (Concentrado::resolver encuentra también el nombre viejo)
        DB::transaction(function () use ($request, $concentrado, $datos) {
            $concentrado->update($datos);

            Bitacora::registrar($request->user(), 'concentrado_editado', null, [Rol::PROPIETARIO], [
                'concentrado' => $concentrado->etiqueta(),
            ]);
        });

        return redirect()->route('admin.concentrados.index')
            ->with('estado', __('mensajes.concentrado.actualizado', ['nombre' => $concentrado->nombre]));
    }

    public function guardarTramo(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;

        $datos = $request->validate([
            'especie_id' => [
                'required', 'integer',
                Rule::exists('especies', 'id')->where('tenant_id', $tenantId),
            ],
            'peso_desde_g' => ['required', 'numeric', 'min:0.1', 'max:99999'],
            'peso_hasta_g' => ['required', 'numeric', 'min:0.1', 'max:99999'],
            'porcentaje_biomasa' => ['required', 'numeric', 'min:0.1', 'max:30'],
            'tomas_dia' => ['required', 'integer', 'min:1', 'max:12'],
            'proteina_porcentaje' => ['nullable', 'numeric', 'min:10', 'max:70'],
            'pellet' => ['nullable', 'string', 'max:40'],
            'concentrado_id' => [
                'nullable', 'integer',
                Rule::exists('concentrados', 'id')->where('tenant_id', $tenantId),
            ],
        ]);

        if ((float) $datos['peso_desde_g'] > (float) $datos['peso_hasta_g']) {
            throw ValidationException::withMessages([
                'peso_desde_g' => __('mensajes.concentrado.rango_peso_invertido'),
            ]);
        }

        $especie = Especie::query()->where('tenant_id', $tenantId)->findOrFail((int) $datos['especie_id']);

        DB::transaction(function () use ($request, $especie, $datos) {
            TablaRacion::updateOrCreate(
                [
                    'especie_id' => $especie->id,
                    'peso_hasta_g' => (float) $datos['peso_hasta_g'],
                ],
                [
                    'peso_desde_g' => (float) $datos['peso_desde_g'],
                    'porcentaje_biomasa' => (float) $datos['porcentaje_biomasa'],
                    'tomas_dia' => (int) $datos['tomas_dia'],
                    'proteina_porcentaje' => isset($datos['proteina_porcentaje']) && $datos['proteina_porcentaje'] !== '' ? (float) $datos['proteina_porcentaje'] : null,
                    'pellet' => isset($datos['pellet']) && trim($datos['pellet']) !== '' ? trim($datos['pellet']) : null,
                    'concentrado_id' => ! empty($datos['concentrado_id']) ? (int) $datos['concentrado_id'] : null,
                ],
            );

            Bitacora::registrar($request->user(), 'tabla_racion', null, [Rol::PROPIETARIO], [
                'especie' => $especie->nombre,
            ]);
        });

        return redirect()->route('admin.concentrados.index')
            ->with('estado', __('mensajes.concentrado.tramo_guardado', ['especie' => $especie->nombre]));
    }

    public function eliminarTramo(Request $request, TablaRacion $tramo): RedirectResponse
    {
        abort_unless((int) $tramo->tenant_id === (int) $request->user()->tenant_id, 404);
        $especieNombre = $tramo->especie?->nombre ?? '';

        DB::transaction(function () use ($request, $tramo, $especieNombre) {
            $tramo->delete();
            Bitacora::registrar($request->user(), 'tabla_racion', null, [Rol::PROPIETARIO], [
                'especie' => $especieNombre,
            ]);
        });

        return redirect()->route('admin.concentrados.index')
            ->with('estado', __('mensajes.concentrado.tramo_eliminado', ['especie' => $especieNombre]));
    }

    public function restaurarEspecie(Request $request, Especie $especie): RedirectResponse
    {
        abort_unless((int) $especie->tenant_id === (int) $request->user()->tenant_id, 404);

        DB::transaction(function () use ($request, $especie) {
            TablaRacion::cargarParaEspecie($especie, reemplazar: true);
            Bitacora::registrar($request->user(), 'tabla_racion', null, [Rol::PROPIETARIO], [
                'especie' => $especie->nombre,
            ]);
        });

        return redirect()->route('admin.concentrados.index')
            ->with('estado', __('mensajes.concentrado.tabla_restaurada', ['especie' => $especie->nombre]));
    }

    /** @return array<string, mixed> */
    private function validarConcentrado(Request $request, ?Concentrado $concentrado = null): array
    {
        $tenantId = $request->user()->tenant_id;
        if ($concentrado !== null) {
            abort_unless((int) $concentrado->tenant_id === (int) $tenantId, 404);
        }

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:120',
                Rule::unique('concentrados', 'nombre')
                    ->where('tenant_id', $tenantId)
                    ->when($concentrado, fn ($regla) => $regla->ignore($concentrado->id)),
            ],
            'nombre_corto' => ['nullable', 'string', 'max:60'],
            'proteina_porcentaje' => ['nullable', 'numeric', 'min:10', 'max:70'],
            'pellet_mm' => ['nullable', 'string', 'max:40'],
            'peso_bulto_kg' => ['required', 'numeric', 'min:1', 'max:1000'],
            'activo' => ['nullable', 'boolean'],
        ], [
            'nombre.unique' => __('mensajes.concentrado.nombre_repetido'),
        ]);

        return [
            'nombre' => trim($datos['nombre']),
            'nombre_corto' => isset($datos['nombre_corto']) && trim($datos['nombre_corto']) !== '' ? trim($datos['nombre_corto']) : null,
            'proteina_porcentaje' => isset($datos['proteina_porcentaje']) && $datos['proteina_porcentaje'] !== '' ? (float) $datos['proteina_porcentaje'] : null,
            'pellet_mm' => isset($datos['pellet_mm']) && trim($datos['pellet_mm']) !== '' ? trim($datos['pellet_mm']) : null,
            'peso_bulto_kg' => (float) $datos['peso_bulto_kg'],
            'activo' => array_key_exists('activo', $datos) ? (bool) $datos['activo'] : true,
        ];
    }
}

