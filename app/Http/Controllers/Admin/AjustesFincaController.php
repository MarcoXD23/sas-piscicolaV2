<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\PrimerUso;
use App\Support\Alertas\EvaluadorAlertas;
use App\Support\Hora;
use App\Support\Region;
use App\Support\UnidadesPeso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * País, zona horaria, idioma y umbrales de alerta de la finca, y los ajustes de operación (CatalogoAjustes).
 * Exclusivo del propietario.
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
            'unidadesPeso' => UnidadesPeso::opciones(),
            'formatosHora' => ['' => __('region.ajustes.formato_hora_del_pais', ['formato' => __('region.formatos_hora_corto.'.Hora::delPais($finca->pais))])]
                + collect(Hora::FORMATOS)->mapWithKeys(fn (string $f) => [$f => __('region.formatos_hora.'.$f)])->all(),
            'idiomas' => collect(Region::IDIOMAS)->mapWithKeys(fn ($idioma) => [$idioma => __("region.idiomas.{$idioma}")])->all(),
            // Operación de la finca: cada ajuste del catálogo con su valor actual (o el de por defecto)
            'ajustesOperacion' => AjustesFinca::todos(),
            'seccionesOperacion' => CatalogoAjustes::porSeccion(),
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
            'alerta_stock_dias' => ['sometimes', 'required', 'integer', 'min:1', 'max:90'],
            'alerta_mortalidad_porcentaje' => ['sometimes', 'required', 'numeric', 'min:0.1', 'max:100'],
            'alerta_mortalidad_dias' => ['sometimes', 'required', 'integer', 'min:1', 'max:60'],
            'alerta_despacho_dias' => ['sometimes', 'required', 'integer', 'min:1', 'max:30'],
            // Unidad que sale primero en el muestreo (lo guardado sigue en gramos)
            'unidad_peso_muestreo' => ['sometimes', 'required', Rule::in(UnidadesPeso::UNIDADES)],
            // Formato de hora de la finca; vacío = el del país
            'formato_hora' => ['sometimes', 'nullable', Rule::in(Hora::FORMATOS)],
        ], [
            'zona_horaria.in' => __('region.ajustes.zona_invalida'),
            'idioma.in' => __('region.ajustes.idioma_invalido'),
        ]);

        $finca = $request->user()->tenant;
        $finca->update($datos);
        // Primer uso: la zona horaria y el idioma ya se revisaron
        PrimerUso::marcar($finca, 'ajustes_revisados_at');

        // Con los umbrales nuevos las alertas abiertas pueden cerrarse o abrirse otras
        EvaluadorAlertas::paraFinca($finca->id);

        return redirect()->route('admin.ajustes.edit')->with('estado', __('region.ajustes.guardados'));
    }

    /** Ajustes de operación: guarda los que cambiaron y deja cada cambio en la bitácora. */
    public function guardarOperacion(Request $request): RedirectResponse
    {
        $datos = $request->validate(AjustesFinca::reglas());
        $cambios = AjustesFinca::guardar($request->user(), $datos);

        return redirect()->route('admin.ajustes.edit')
            ->with('estado', __($cambios > 0 ? 'ajustes_finca.guardados' : 'ajustes_finca.sin_cambios'));
    }
}
