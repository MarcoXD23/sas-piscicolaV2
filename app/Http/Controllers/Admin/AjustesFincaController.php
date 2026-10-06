<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * País, zona horaria e idioma de la finca (exclusivo del propietario).
 */
class AjustesFincaController extends Controller
{
    public function edit(Request $request): View
    {
        $finca = $request->user()->tenant;

        return view('admin.ajustes.index', [
            'finca' => $finca,
            'paises' => Region::paises(),
            // Primero las zonas del país de la finca; después todas
            'zonas' => array_values(array_unique([
                ...($finca->pais ? Region::zonasDelPais($finca->pais) : []),
                ...Region::zonasValidas(),
            ])),
            'idiomas' => collect(Region::IDIOMAS)->mapWithKeys(fn ($idioma) => [$idioma => __("region.idiomas.{$idioma}")])->all(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'pais' => ['required', 'string', function ($atributo, $valor, $falla) {
                if (! Region::paisValido($valor)) {
                    $falla(__('region.ajustes.pais_invalido'));
                }
            }],
            'zona_horaria' => ['required', 'string', Rule::in(Region::zonasValidas())],
            'idioma' => ['required', 'string', Rule::in(Region::IDIOMAS)],
        ], [
            'zona_horaria.in' => __('region.ajustes.zona_invalida'),
            'idioma.in' => __('region.ajustes.idioma_invalido'),
        ]);

        $request->user()->tenant->update($datos);

        return redirect()->route('admin.ajustes.edit')->with('estado', __('region.ajustes.guardados'));
    }
}
