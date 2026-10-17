<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Especie;
use App\Models\PrecioEspecie;
use App\Models\Rol;
use App\Support\Formato;
use App\Support\Ventas\Cupo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ajustes › Precios (docs/diseno/dia-de-proceso.md, tema 8): por especie, el precio general y, si entra en el cupo del
 * trabajador, el precio dentro del cupo. Solo el propietario (AGENTS.md: los ajustes de la finca son suyos); cada cambio
 * queda en la bitácora con el antes y el después. Cada venta guarda sus precios: un cambio solo aplica a las nuevas.
 */
class PrecioController extends Controller
{
    public function edit(): View
    {
        return view('admin.ajustes.precios', [
            'especies' => Especie::query()->where('activo', true)->orderBy('nombre')->get(),
            'precios' => PrecioEspecie::query()->get()->keyBy('especie_id'),
            'cupoKg' => Cupo::kg(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'precios' => ['nullable', 'array'],
            'precios.*.general' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'precios.*.entra_en_cupo' => ['nullable', 'boolean'],
            'precios.*.cupo' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ], [
            'precios.*.general.integer' => __('region.dinero_sin_decimales'),
            'precios.*.cupo.integer' => __('region.dinero_sin_decimales'),
            'precios.*.general.min' => __('precios.errores.precio_minimo'),
            'precios.*.cupo.min' => __('precios.errores.precio_minimo'),
        ]);

        $especies = Especie::query()->where('activo', true)->get()->keyBy('id');
        $cambios = DB::transaction(function () use ($datos, $especies, $request) {
            $cambios = 0;
            foreach ($datos['precios'] ?? [] as $especieId => $fila) {
                $especie = $especies->get((int) $especieId);
                $general = isset($fila['general']) && $fila['general'] !== '' ? (int) $fila['general'] : null;
                if ($especie === null || $general === null) {
                    continue;
                }
                $entra = ! empty($fila['entra_en_cupo']);
                $cupo = $entra && isset($fila['cupo']) && $fila['cupo'] !== '' ? (int) $fila['cupo'] : null;
                if ($entra && $cupo === null) {
                    throw ValidationException::withMessages(["precios.{$especieId}.cupo" => __('precios.errores.falta_precio_cupo', ['especie' => $especie->nombre])]);
                }

                $actual = PrecioEspecie::query()->where('especie_id', $especie->id)->first();
                $antes = $actual ? $this->texto($actual->precio_general, $actual->entra_en_cupo, $actual->precio_cupo) : __('precios.sin_precio');
                $despues = $this->texto($general, $entra, $cupo);
                if ($antes === $despues) {
                    continue;
                }

                PrecioEspecie::query()->updateOrCreate(['especie_id' => $especie->id], [
                    'precio_general' => $general, 'entra_en_cupo' => $entra, 'precio_cupo' => $cupo, 'cambiado_por' => $request->user()->id,
                ]);
                Bitacora::registrar($request->user(), 'precio', null, [Rol::PROPIETARIO], [
                    'especie' => $especie->nombre, 'antes' => $antes, 'despues' => $despues,
                ]);
                $cambios++;
            }

            return $cambios;
        });

        return back()->with('estado', $cambios > 0 ? __('precios.guardados', ['cantidad' => $cambios]) : __('precios.sin_cambios'));
    }

    private function texto(int $general, bool $entra, ?int $cupo): string
    {
        return $entra
            ? __('precios.texto_con_cupo', ['general' => Formato::dinero($general), 'cupo' => Formato::dinero((int) $cupo)])
            : __('precios.texto_sin_cupo', ['general' => Formato::dinero($general)]);
    }
}
