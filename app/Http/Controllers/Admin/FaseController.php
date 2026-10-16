<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Concentrado;
use App\Models\Especie;
use App\Models\Fase;
use App\Models\ReglaFase;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Configuración de las fases productivas de los lagos y sus reglas por especie (Ajustes › Fases, solo propietario).
 * Una fase con lagos asignados no se puede eliminar hasta cambiar o quitar la fase de esos lagos.
 */
class FaseController extends Controller
{
    public function index(): View
    {
        $fases = Fase::query()
            ->with('reglas')
            ->withCount('lagos')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        $especies = Especie::query()->orderByDesc('activo')->orderBy('nombre')->get();

        return view('admin.fases.index', [
            'fases' => $fases,
            'especies' => $especies,
            'opcionesConcentrados' => ['' => __('fases.reglas.concentrado_del_tramo')]
                + Concentrado::query()->activos()->orderBy('nombre')->get()->mapWithKeys(fn (Concentrado $c) => [$c->id => $c->etiqueta()])->all(),
            'siguienteOrden' => ((int) $fases->max('orden')) + 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarFase($request);

        $fase = DB::transaction(function () use ($request, $datos) {
            $creada = Fase::create($datos);
            Bitacora::registrar($request->user(), 'fase_creada', null, [Rol::PROPIETARIO], [
                'fase' => $creada->nombre,
            ]);

            return $creada;
        });

        return redirect()->route('admin.fases.index')
            ->with('estado', __('mensajes.fase.creada', ['nombre' => $fase->nombre]));
    }

    public function update(Request $request, Fase $fase): RedirectResponse
    {
        $datos = $this->validarFase($request, $fase);

        DB::transaction(function () use ($request, $fase, $datos) {
            $fase->update($datos);
            Bitacora::registrar($request->user(), 'fase_editada', null, [Rol::PROPIETARIO], [
                'fase' => $fase->nombre,
            ]);
        });

        return redirect()->route('admin.fases.index')
            ->with('estado', __('mensajes.fase.actualizada', ['nombre' => $fase->nombre]));
    }

    public function destroy(Request $request, Fase $fase): RedirectResponse
    {
        $nombre = $fase->nombre;

        DB::transaction(function () use ($request, $fase, $nombre) {
            if ($fase->lagos()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'fase' => __('mensajes.fase.con_lagos', ['nombre' => $nombre]),
                ]);
            }

            $fase->delete();
            Bitacora::registrar($request->user(), 'fase_eliminada', null, [Rol::PROPIETARIO], [
                'fase' => $nombre,
            ]);
        });

        return redirect()->route('admin.fases.index')
            ->with('estado', __('mensajes.fase.eliminada', ['nombre' => $nombre]));
    }

    public function guardarRegla(Request $request, Fase $fase): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        abort_unless((int) $fase->tenant_id === (int) $tenantId, 404);

        $datos = $request->validate([
            'especie_id' => [
                'required', 'integer',
                Rule::exists('especies', 'id')->where('tenant_id', $tenantId),
            ],
            'peso_entrada_g' => ['nullable', 'numeric', 'min:0.1', 'max:50000'],
            'peso_salida_g' => ['nullable', 'numeric', 'min:0.1', 'max:50000'],
            'dias_esperados' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'supervivencia_porcentaje' => ['nullable', 'numeric', 'min:1', 'max:100'],
            'densidad_min_m2' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'densidad_max_m2' => ['nullable', 'numeric', 'min:0.1', 'max:5000'],
            'concentrado_id' => ['nullable', 'integer', Rule::exists('concentrados', 'id')->where('tenant_id', $tenantId)],
        ]);

        foreach ([['peso_entrada_g', 'peso_salida_g'], ['densidad_min_m2', 'densidad_max_m2']] as [$minimo, $maximo]) {
            if (isset($datos[$minimo], $datos[$maximo]) && (float) $datos[$minimo] > (float) $datos[$maximo]) {
                throw ValidationException::withMessages([$minimo => __('mensajes.fase.rango_invertido')]);
            }
        }

        $especie = Especie::query()->where('tenant_id', $tenantId)->findOrFail((int) $datos['especie_id']);

        DB::transaction(function () use ($request, $fase, $especie, $datos) {
            ReglaFase::updateOrCreate(
                ['fase_id' => $fase->id, 'especie_id' => $especie->id],
                [
                    'peso_entrada_g' => $datos['peso_entrada_g'] ?? null,
                    'peso_salida_g' => $datos['peso_salida_g'] ?? null,
                    'dias_esperados' => $datos['dias_esperados'] ?? null,
                    'supervivencia_porcentaje' => $datos['supervivencia_porcentaje'] ?? null,
                    'densidad_min_m2' => $datos['densidad_min_m2'] ?? null,
                    'densidad_max_m2' => $datos['densidad_max_m2'] ?? null,
                    'concentrado_id' => ! empty($datos['concentrado_id']) ? (int) $datos['concentrado_id'] : null,
                ],
            );

            Bitacora::registrar($request->user(), 'regla_fase', null, [Rol::PROPIETARIO], [
                'fase' => $fase->nombre,
                'especie' => $especie->nombre,
            ]);
        });

        return redirect()->route('admin.fases.index')
            ->with('estado', __('mensajes.fase.regla_guardada', ['fase' => $fase->nombre, 'especie' => $especie->nombre]));
    }

    /** @return array{nombre: string, orden: int} */
    private function validarFase(Request $request, ?Fase $fase = null): array
    {
        $tenantId = $request->user()->tenant_id;
        if ($fase !== null) {
            abort_unless((int) $fase->tenant_id === (int) $tenantId, 404);
        }

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('fases', 'nombre')
                    ->where('tenant_id', $tenantId)
                    ->when($fase, fn ($regla) => $regla->ignore($fase->id)),
            ],
            'orden' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'nombre.unique' => __('mensajes.fase.nombre_repetido'),
        ]);

        return [
            'nombre' => trim($datos['nombre']),
            'orden' => (int) $datos['orden'],
        ];
    }
}

