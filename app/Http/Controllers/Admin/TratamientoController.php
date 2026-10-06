<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\Tratamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TratamientoController extends Controller
{
    public function store(Request $request, Lago $lago): RedirectResponse
    {
        $datos = $request->validate([
            'fecha_inicio' => ['required', 'date', 'before_or_equal:today'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'motivo' => ['required', 'string', 'max:255'],
            'producto' => ['required', 'string', 'max:255'],
            'dosis' => ['required', 'string', 'max:255'],
            'via' => ['required', 'string', Rule::in(Tratamiento::VIAS)],
            'dias_retiro' => ['required', 'integer', 'between:0,365'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ], [
            'fecha_inicio.before_or_equal' => 'El tratamiento no puede empezar en el futuro.',
            'fecha_fin.after_or_equal' => 'La fecha final debe ser igual o posterior a la de inicio.',
            'via.in' => 'La vía debe ser baño, alimento o agua.',
            'required' => 'Este dato es obligatorio.',
        ]);

        $tratamiento = $lago->tratamientos()->create($datos + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.lagos.show', $lago)->with('estado', $tratamiento->dias_retiro > 0
            ? 'Tratamiento registrado. Lago en retiro sanitario hasta el '.$tratamiento->finRetiro()->format('d/m/Y').'.'
            : 'Tratamiento registrado.');
    }
}
