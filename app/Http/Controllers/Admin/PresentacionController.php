<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\Presentacion;
use App\Models\Proceso;
use App\Models\Rol;
use App\Models\Tamano;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Catálogo de presentaciones de la finca (docs/diseno/dia-de-proceso.md, tema 6), en la pestaña «Presentaciones» de
 * Especies: tamaños por especie, procesos de la finca y, por especie, la tabla tamaño × proceso donde se marcan las
 * presentaciones (con su color de cabuya, texto libre). El administrador edita; el propietario consulta.
 * Arranca vacío: ningún nombre de la finca piloto en el código. Lo que ya se usó en un pesaje no se borra: se desactiva.
 */
class PresentacionController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.especies.presentaciones', [
            'especies' => Especie::query()->where('activo', true)->with(['tamanos' => fn ($q) => $q->orderBy('orden')->orderBy('nombre')])->orderBy('nombre')->get(),
            'procesos' => Proceso::query()->orderBy('orden')->orderBy('nombre')->get(),
            'presentaciones' => Presentacion::query()->get()->keyBy(fn (Presentacion $p) => $p->tamano_id.'-'.$p->proceso_id),
            'puedeEditar' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
        ]);
    }

    public function guardarProceso(Request $request): RedirectResponse
    {
        $datos = $this->validarNombre($request, Proceso::class, 'procesos');
        $proceso = Proceso::create($datos + ['orden' => (int) Proceso::query()->max('orden') + 1, 'activo' => true]);
        $this->bitacora($request, __('presentaciones.bitacora.proceso_creado', ['nombre' => $proceso->nombre]));

        return back()->with('estado', __('presentaciones.proceso_creado', ['nombre' => $proceso->nombre]));
    }

    public function actualizarProceso(Request $request, Proceso $proceso): RedirectResponse
    {
        $datos = $this->validarNombre($request, Proceso::class, 'procesos', $proceso) + $request->validate(['activo' => ['required', 'boolean']]);
        $proceso->update(['nombre' => $datos['nombre'], 'activo' => (bool) $datos['activo']]);
        $this->bitacora($request, __('presentaciones.bitacora.proceso_editado', ['nombre' => $proceso->nombre]));

        return back()->with('estado', __('presentaciones.guardado', ['nombre' => $proceso->nombre]));
    }

    public function eliminarProceso(Request $request, Proceso $proceso): RedirectResponse
    {
        $this->eliminar($request, $proceso);

        return back()->with('estado', __('presentaciones.eliminado', ['nombre' => $proceso->nombre]));
    }

    public function guardarTamano(Request $request): RedirectResponse
    {
        $tenantId = $request->user()->tenant_id;
        $especie = $request->validate([
            'especie_id' => ['required', 'integer', Rule::exists('especies', 'id')->where('tenant_id', $tenantId)],
        ], ['especie_id.required' => __('presentaciones.errores.elige_especie')])['especie_id'];
        $datos = $this->validarTamano($request, (int) $especie);
        $tamano = Tamano::create($datos + [
            'especie_id' => (int) $especie, 'activo' => true,
            'orden' => (int) Tamano::query()->where('especie_id', $especie)->max('orden') + 1,
        ]);
        $this->bitacora($request, __('presentaciones.bitacora.tamano_creado', ['nombre' => $tamano->nombre, 'especie' => $tamano->especie->nombre]));

        return back()->with('estado', __('presentaciones.tamano_creado', ['nombre' => $tamano->nombre]));
    }

    public function actualizarTamano(Request $request, Tamano $tamano): RedirectResponse
    {
        $datos = $this->validarTamano($request, $tamano->especie_id, $tamano) + $request->validate(['activo' => ['required', 'boolean']]);
        $tamano->update($datos);
        $this->bitacora($request, __('presentaciones.bitacora.tamano_editado', ['nombre' => $tamano->nombre, 'especie' => $tamano->especie->nombre]));

        return back()->with('estado', __('presentaciones.guardado', ['nombre' => $tamano->nombre]));
    }

    public function eliminarTamano(Request $request, Tamano $tamano): RedirectResponse
    {
        $this->eliminar($request, $tamano);

        return back()->with('estado', __('presentaciones.eliminado', ['nombre' => $tamano->nombre]));
    }

    /**
     * La tabla de una especie: cada casilla marcada (tamaño activo × proceso activo) es una presentación activa, con su
     * color de cabuya. Una desmarcada se desactiva (nunca se borra: puede estar en pesajes).
     */
    public function guardarTabla(Request $request, Especie $especie): RedirectResponse
    {
        $datos = $request->validate([
            'marcadas' => ['nullable', 'array'],
            'marcadas.*' => ['array'],
            'cabuya' => ['nullable', 'array'],
            'cabuya.*' => ['array'],
            'cabuya.*.*' => ['nullable', 'string', 'max:40'],
        ], ['cabuya.*.*.max' => __('presentaciones.errores.cabuya_larga', ['maximo' => 40])]);

        $activas = DB::transaction(function () use ($datos, $especie) {
            $tamanos = Tamano::query()->where('especie_id', $especie->id)->activos()->get();
            $procesos = Proceso::query()->activos()->get();
            $activas = 0;
            foreach ($tamanos as $tamano) {
                foreach ($procesos as $proceso) {
                    $marcada = ! empty($datos['marcadas'][$tamano->id][$proceso->id]);
                    $cabuya = trim((string) ($datos['cabuya'][$tamano->id][$proceso->id] ?? '')) ?: null;
                    $existente = Presentacion::query()->where('tamano_id', $tamano->id)->where('proceso_id', $proceso->id)->first();
                    if ($marcada) {
                        Presentacion::query()->updateOrCreate(
                            ['tamano_id' => $tamano->id, 'proceso_id' => $proceso->id],
                            ['especie_id' => $especie->id, 'color_cabuya' => $cabuya, 'activo' => true],
                        );
                        $activas++;
                    } elseif ($existente?->activo) {
                        $existente->update(['activo' => false]);
                    }
                }
            }

            return $activas;
        });
        $this->bitacora($request, __('presentaciones.bitacora.tabla', ['especie' => $especie->nombre, 'cantidad' => $activas]));

        return back()->with('estado', __('presentaciones.tabla_guardada', ['especie' => $especie->nombre, 'cantidad' => $activas]));
    }

    /** Un tamaño o proceso sin pesajes se borra con sus presentaciones; si ya se pesó, hay que desactivarlo. */
    private function eliminar(Request $request, Tamano|Proceso $registro): void
    {
        DB::transaction(function () use ($request, $registro) {
            $bloqueado = $registro::query()->whereKey($registro->id)->lockForUpdate()->firstOrFail();
            if ($bloqueado->enUso()) {
                throw ValidationException::withMessages(['catalogo' => __('presentaciones.errores.en_uso', ['nombre' => $bloqueado->nombre])]);
            }
            $bloqueado->presentaciones()->delete();
            $bloqueado->delete();
            $this->bitacora($request, __('presentaciones.bitacora.eliminado', ['nombre' => $bloqueado->nombre]));
        });
    }

    /**
     * Nombre único sin importar mayúsculas (igual que el índice de la base), dentro de la finca o de la especie.
     *
     * @param  class-string<Model>  $modelo
     */
    private function validarNombre(Request $request, string $modelo, string $tabla, ?Model $actual = null, ?int $especieId = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:60', function ($atributo, $valor, $falla) use ($modelo, $actual, $especieId) {
                $repetido = $modelo::query()->whereRaw('LOWER(nombre) = ?', [mb_strtolower(trim($valor))])
                    ->when($especieId !== null, fn ($q) => $q->where('especie_id', $especieId))
                    ->when($actual, fn ($q) => $q->whereKeyNot($actual->getKey()))->exists();
                if ($repetido) {
                    $falla(__('presentaciones.errores.nombre_repetido'));
                }
            }],
        ], ['nombre.required' => __('presentaciones.errores.escribe_nombre')]);

        return ['nombre' => trim($datos['nombre'])];
    }

    /** Nombre (único en la especie) y rango de peso opcional, solo informativo. */
    private function validarTamano(Request $request, int $especieId, ?Tamano $actual = null): array
    {
        $nombre = $this->validarNombre($request, Tamano::class, 'tamanos', $actual, $especieId);
        $datos = $request->validate([
            'peso_min_g' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'peso_max_g' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ]);
        if (isset($datos['peso_min_g'], $datos['peso_max_g']) && (float) $datos['peso_min_g'] > (float) $datos['peso_max_g']) {
            throw ValidationException::withMessages(['peso_min_g' => __('presentaciones.errores.rango_invertido')]);
        }

        return $nombre + ['peso_min_g' => $datos['peso_min_g'] ?? null, 'peso_max_g' => $datos['peso_max_g'] ?? null];
    }

    private function bitacora(Request $request, string $cambio): void
    {
        Bitacora::registrar($request->user(), 'presentaciones', null, [Rol::ADMINISTRADOR], ['cambio' => $cambio]);
    }
}
