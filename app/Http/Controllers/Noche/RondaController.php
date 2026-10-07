<?php

namespace App\Http\Controllers\Noche;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\Ronda;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RondaController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'tipo' => ['required', 'string', Rule::in(Ronda::TIPOS)],
            'lago_id' => ['nullable', 'integer', Rule::exists('lagos', 'id')->where('tenant_id', $request->user()->tenant_id)],
            'descripcion' => ['nullable', 'string', 'max:1000', Rule::requiredIf($request->input('tipo') !== Ronda::SIN_NOVEDAD)],
        ], [
            'tipo.required' => __('mensajes.ronda.elige_tipo_ronda'),
            'tipo.in' => __('mensajes.ronda.elige_tipo_ronda_valido'),
            'lago_id.exists' => __('mensajes.ronda.lago_no_existe'),
            'descripcion.required' => __('mensajes.ronda.describe_novedad'),
        ]);

        $ronda = DB::transaction(function () use ($datos, $request) {
            $ronda = Ronda::create($datos + [
                'user_id' => $request->user()->id,
                'turno_id' => $request->user()->turnoEnCurso(Rol::CELADOR)?->id,
                'registrada_at' => now(),
            ]);

            Bitacora::registrar($request->user(), $ronda->esNovedad() ? 'novedad' : 'ronda', $ronda->lago, [Rol::CELADOR], [
                'tipo' => $ronda->textoTipo(), 'descripcion' => (string) $ronda->descripcion,
            ]);

            return $ronda;
        });

        return redirect()->route('noche.index')->with('estado', $ronda->esNovedad()
            ? __('mensajes.ronda.novedad_registrada_propietario_vera_inicio')
            : __('mensajes.ronda.ronda_sin_novedad_registrada'));
    }
}
