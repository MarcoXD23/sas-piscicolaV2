<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Especie;
use App\Models\EspecieFoto;
use App\Models\Rol;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Catálogo de especies de la finca. El administrador (o el propietario que también es administrador) agrega, edita,
 * activa y desactiva especies; el propietario lo consulta. Una especie con siembras no se borra: se desactiva.
 */
class EspecieController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.especies.index', [
            'especies' => Especie::query()->withCount('siembras')->orderByDesc('activo')->orderBy('nombre')->get(),
            'puedeEditar' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
            'climas' => $this->climas(),
            'alimentaciones' => $this->alimentaciones(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $especie = Especie::create($this->validar($request) + ['activo' => true]);

        return redirect()->route('admin.especies.index')->with('estado', __('mensajes.especie.creada', ['nombre' => $especie->nombre]));
    }

    public function edit(Especie $especie): View
    {
        return view('admin.especies.edit', [
            'especie' => $especie,
            'climas' => $this->climas(),
            'alimentaciones' => $this->alimentaciones(),
        ]);
    }

    public function update(Request $request, Especie $especie): RedirectResponse
    {
        $especie->update($this->validar($request, $especie));

        return redirect()->route('admin.especies.index')->with('estado', __('mensajes.especie.actualizada', ['nombre' => $especie->nombre]));
    }

    /** Desactivada: no se siembra, pero sus siembras siguen y conserva el historial. */
    public function cambiarActivo(Especie $especie): RedirectResponse
    {
        $especie->update(['activo' => ! $especie->activo]);

        return back()->with('estado', __($especie->activo ? 'mensajes.especie.activada' : 'mensajes.especie.desactivada', ['nombre' => $especie->nombre]));
    }

    public function destroy(Especie $especie): RedirectResponse
    {
        $archivos = DB::transaction(function () use ($especie) {
            // Con siembras (activas o históricas) no se borra; la clave foránea también lo impide
            if ($especie->siembras()->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['especie' => __('mensajes.especie.con_siembras', ['nombre' => $especie->nombre])]);
            }
            // Sus fotos se van con ella: primero las filas y, ya confirmado, los dos archivos de cada una
            $fotos = $especie->fotos()->get();
            $especie->fotos()->delete();
            $especie->delete();

            return $fotos->flatMap(fn (EspecieFoto $foto) => [$foto->ruta_original, $foto->ruta_ligera])->all();
        });
        Storage::disk('fotos')->delete($archivos);

        return redirect()->route('admin.especies.index')->with('estado', __('mensajes.especie.eliminada', ['nombre' => $especie->nombre]));
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?Especie $especie = null): array
    {
        $tenantId = $request->user()->tenant_id;
        $rango = fn (float $min, float $max) => ['nullable', 'numeric', 'between:'.$min.','.$max];

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:80', function ($atributo, $valor, $falla) use ($tenantId, $especie) {
                // Nombre único en la finca sin importar mayúsculas (igual que el índice de la base)
                $repetido = Especie::query()->where('tenant_id', $tenantId)->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($valor))])
                    ->when($especie, fn ($q) => $q->whereKeyNot($especie->id))->exists();
                if ($repetido) {
                    $falla(__('mensajes.especie.nombre_repetido'));
                }
            }],
            'nombre_cientifico' => ['nullable', 'string', 'max:120'],
            'clima' => ['required', 'string', Rule::in(Especie::CLIMAS)],
            'temp_min' => [...$rango(0, 45), 'required_with:temp_max'],
            'temp_max' => [...$rango(0, 45), 'required_with:temp_min'],
            'oxigeno_min' => $rango(0, 20),
            'ph_min' => [...$rango(0, 14), 'required_with:ph_max'],
            'ph_max' => [...$rango(0, 14), 'required_with:ph_min'],
            'alimentacion' => ['nullable', 'string', Rule::in(Especie::ALIMENTACIONES)],
            // Ficha de cultivo y de procesamiento: texto libre opcional, solo las partes conocidas
            'cultivo' => ['nullable', 'array:'.implode(',', Especie::CAMPOS_CULTIVO)],
            'cultivo.*' => ['nullable', 'string', 'max:2000'],
            'procesamiento' => ['nullable', 'array:'.implode(',', Especie::CAMPOS_PROCESAMIENTO)],
            'procesamiento.*' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'string', 'max:200'],
            'aspecto' => ['nullable', 'string', 'max:500'],
            'ficha_orientativa' => ['nullable', 'boolean'],
        ], [
            'cultivo.*.max' => __('mensajes.especie.texto_largo', ['maximo' => 2000]),
            'procesamiento.*.max' => __('mensajes.especie.texto_largo', ['maximo' => 2000]),
        ]);
        $datos['ficha_orientativa'] = $request->boolean('ficha_orientativa');

        foreach ([['temp_min', 'temp_max'], ['ph_min', 'ph_max']] as [$minimo, $maximo]) {
            if (isset($datos[$minimo], $datos[$maximo]) && (float) $datos[$minimo] > (float) $datos[$maximo]) {
                throw ValidationException::withMessages([$minimo => __('mensajes.especie.rango_invertido')]);
            }
        }

        return ['nombre' => trim($datos['nombre'])] + $datos;
    }

    /** @return array<string, string> */
    private function climas(): array
    {
        return [Especie::CALIDO => __('especies.agua_calida'), Especie::FRIO => __('especies.agua_fria')];
    }

    /** @return array<string, string> */
    private function alimentaciones(): array
    {
        return ['' => __('especies.catalogo_finca.sin_alimentacion')]
            + collect(Especie::ALIMENTACIONES)->mapWithKeys(fn ($tipo) => [$tipo => __('especies.catalogo_finca.alimentaciones.'.$tipo)])->all();
    }
}
