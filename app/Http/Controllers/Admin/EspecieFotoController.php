<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Especie;
use App\Models\EspecieFoto;
use App\Support\Fotos\ProcesadorFoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Fotos reales de una especie: solo el administrador las sube, ordena y borra. Máximo 5 por especie
 * (también en la base) y un tope de espacio por finca. La app nunca genera ni inventa imágenes.
 */
class EspecieFotoController extends Controller
{
    public function store(Request $request, Especie $especie): RedirectResponse
    {
        $datos = $request->validate([
            'foto' => ['required', 'file', 'max:'.(config('fotos.max_mb') * 1024)],
            'autor' => ['nullable', 'string', 'max:150'],
            'licencia' => ['nullable', 'string', 'max:150'],
        ], [
            'foto.required' => __('especies.fotos.elige_foto'),
            'foto.file' => __('especies.fotos.elige_foto'),
            'foto.uploaded' => __('especies.fotos.muy_pesada', ['maximo' => config('fotos.max_mb')]),
            'foto.max' => __('especies.fotos.muy_pesada', ['maximo' => config('fotos.max_mb')]),
        ]);

        $archivo = $request->file('foto');
        $this->verificarCupo($especie, $request->user()->tenant_id, $archivo->getSize());

        // Revisa el contenido y las dimensiones, y guarda original (sin metadatos) y versión ligera
        $guardada = ProcesadorFoto::guardar($archivo, $request->user()->tenant_id, $especie->id);

        try {
            DB::transaction(function () use ($especie, $guardada, $datos, $request) {
                // Bloquea la especie: dos subidas a la vez no pueden pasar de 5 ni repetir el orden
                Especie::whereKey($especie->id)->lockForUpdate()->first();
                $this->verificarCupo($especie, $request->user()->tenant_id, $guardada['bytes_original'] + $guardada['bytes_ligera'], yaGuardada: true);

                $especie->fotos()->create($guardada + [
                    'user_id' => $request->user()->id,
                    'orden' => (int) $especie->fotos()->max('orden') + 1,
                    'autor' => $datos['autor'] ?? null,
                    'licencia' => $datos['licencia'] ?? null,
                ]);
            });
        } catch (\Throwable $error) {
            // Si no quedó registrada, no deja archivos sueltos
            Storage::disk('fotos')->delete([$guardada['ruta_original'], $guardada['ruta_ligera']]);
            throw $error;
        }

        return back()->with('estado', __('especies.fotos.subida'));
    }

    /** Sube o baja una foto un lugar (la primera es la portada). */
    public function ordenar(Request $request, Especie $especie, EspecieFoto $foto): RedirectResponse
    {
        $direccion = $request->validate(['direccion' => ['required', Rule::in(['subir', 'bajar'])]])['direccion'];

        DB::transaction(function () use ($especie, $foto, $direccion) {
            $vecina = $especie->fotos()->where('orden', $direccion === 'subir' ? $foto->orden - 1 : $foto->orden + 1)->lockForUpdate()->first();
            if ($vecina) {
                // El orden único es diferible: el intercambio se revisa al confirmar la transacción
                [$foto->orden, $vecina->orden] = [$vecina->orden, $foto->orden];
                $foto->save();
                $vecina->save();
            }
        });

        return back()->with('estado', __('especies.fotos.ordenada'));
    }

    public function destroy(Especie $especie, EspecieFoto $foto): RedirectResponse
    {
        DB::transaction(function () use ($especie, $foto) {
            $foto->delete();
            // Las que quedan se numeran de nuevo desde 1
            $especie->fotos()->get()->values()->each(fn (EspecieFoto $resto, int $i) => $resto->update(['orden' => $i + 1]));
        });
        Storage::disk('fotos')->delete([$foto->ruta_original, $foto->ruta_ligera]);

        return back()->with('estado', __('especies.fotos.borrada'));
    }

    /** Máximo de fotos por especie y tope de espacio de la finca (originales + versiones ligeras). */
    private function verificarCupo(Especie $especie, int $tenantId, int $bytesNuevos, bool $yaGuardada = false): void
    {
        if ($especie->fotos()->count() >= config('fotos.max_por_especie')) {
            throw ValidationException::withMessages(['foto' => __('especies.fotos.maximo', ['maximo' => config('fotos.max_por_especie')])]);
        }

        // Antes de procesar se suma el peso subido más una versión ligera; ya guardada, el peso real
        $estimado = $yaGuardada ? $bytesNuevos : $bytesNuevos + (int) config('fotos.ligera_bytes_max');
        $usados = (int) EspecieFoto::query()->where('tenant_id', $tenantId)->sum(DB::raw('bytes_original + bytes_ligera'));
        $tope = (int) config('fotos.tope_finca_mb') * 1024 * 1024;
        if ($usados + $estimado > $tope) {
            throw ValidationException::withMessages(['foto' => __('especies.fotos.tope_finca', [
                'usado' => round($usados / 1024 / 1024), 'tope' => config('fotos.tope_finca_mb'),
            ])]);
        }
    }
}
