<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MedicionAguaController extends Controller
{
    private const PARAMETROS = ['temperatura_c', 'oxigeno_mg_l', 'ph', 'amonio_mg_l', 'nitritos_mg_l', 'transparencia_cm'];

    public function store(Request $request, Lago $lago): RedirectResponse
    {
        // Desde Seguridad & Noche no se envía la hora: se usa la del momento de guardar
        $request->mergeIfMissing(['medido_at' => now()->format('Y-m-d H:i:s')]);
        $alMenosUno = 'required_without_all:'.implode(',', self::PARAMETROS);

        $datos = $request->validate([
            'medido_at' => ['required', 'date', 'before_or_equal:now'],
            'temperatura_c' => ['nullable', 'numeric', 'between:0,45', $alMenosUno],
            'oxigeno_mg_l' => ['nullable', 'numeric', 'between:0,30'],
            'ph' => ['nullable', 'numeric', 'between:0,14'],
            'amonio_mg_l' => ['nullable', 'numeric', 'between:0,100'],
            'nitritos_mg_l' => ['nullable', 'numeric', 'between:0,100'],
            'transparencia_cm' => ['nullable', 'integer', 'between:0,500'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'temperatura_c.required_without_all' => 'Registra al menos un parámetro.',
            'medido_at.before_or_equal' => 'La medición no puede tener fecha futura.',
            'between' => 'El valor está fuera del rango posible.',
        ]);

        $medicion = $lago->mediciones()->create($datos + ['user_id' => $request->user()->id]);
        $alertas = $medicion->alertas($lago->siembrasActivas()->pluck('especie'));

        // Se registra desde el detalle del lago o desde Seguridad & Noche: vuelve a la misma pantalla
        return back()->with('estado', $alertas === []
            ? 'Medición registrada: todos los parámetros están en rango.'
            : 'Medición registrada con '.count($alertas).' alerta(s). Revisa el lago.');
    }
}
