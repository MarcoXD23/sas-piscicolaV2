<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\Tratamiento;
use App\Support\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TratamientoController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date', 'before_or_equal:'.Region::hoy()->toDateString()],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['required', 'string', 'max:255'],
            'producto' => ['required', 'string', 'max:255'],
            'registro_sanitario' => ['nullable', 'string', 'max:100'],
            'dosis' => ['required', 'string', 'max:255'],
            'via' => ['required', 'string', Rule::in(Tratamiento::VIAS)],
            'dias_retiro' => ['required', 'integer', 'between:0,365'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'fecha_inicio.before_or_equal' => __('mensajes.tratamiento.tratamiento_no_puede_empezar_futuro'),
            'fecha_fin.after_or_equal' => __('mensajes.tratamiento.fecha_final_debe_ser_igual'),
            'via.in' => __('mensajes.tratamiento.via_debe_ser_bano_alimento'),
            'required' => __('mensajes.tratamiento.este_dato_es_obligatorio'),
        ]);

        $tratamiento = $lago->tratamientos()->create($datos + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.lagos.show', $lago)->with('estado', $tratamiento->dias_retiro > 0
            ? __('mensajes.tratamiento.registrado_con_retiro', ['fecha' => $tratamiento->finRetiro()->format('d/m/Y')])
            : __('mensajes.tratamiento.tratamiento_registrado'));
    }
}
