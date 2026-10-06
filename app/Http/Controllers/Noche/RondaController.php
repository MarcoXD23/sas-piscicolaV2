<?php

namespace App\Http\Controllers\Noche;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Ronda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RondaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'string', Rule::in(array_keys(Ronda::TIPOS))],
            'lago_id' => ['nullable', 'integer', Rule::exists('lagos', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'descripcion' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('tipo') !== Ronda::SIN_NOVEDAD)],
        ], [
            'tipo.required' => 'Elige el tipo de ronda.',
            'tipo.in' => 'Elige un tipo de ronda válido.',
            'lago_id.exists' => 'El lago no existe.',
            'descripcion.required' => 'Describe la novedad.',
        ]);

        $ronda = Ronda::create($datos + [
            'user_id' => $request->user()->id,
            'turno_id' => $request->user()->turnoEnCurso(Rol::CELADOR)?->id,
            'registrada_at' => now(),
        ]);

        return redirect()->route('noche.index')->with('estado', $ronda->esNovedad()
            ? 'Novedad registrada. El propietario la verá en su inicio.'
            : 'Ronda sin novedad registrada.');
    }
}
