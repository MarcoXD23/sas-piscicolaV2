<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Historial de alertas (abiertas y cerradas). Propietario y administrador; el administrador no ve las de bodega.
 */
class AlertaController extends Controller
{
    public function index(Request $request): View
    {
        $tipos = Alerta::tiposVisiblesPara($request->user());
        $filtro = $request->validate(['tipo' => ['nullable', Rule::in($tipos)]])['tipo'] ?? null;

        return view('admin.alertas.index', [
            'tipos' => $tipos,
            'filtro' => $filtro,
            'alertas' => Alerta::query()
                ->whereIn('tipo', $filtro ? [$filtro] : $tipos)
                ->orderByRaw('cerrada_at IS NOT NULL')
                ->latest('abierta_at')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }
}
