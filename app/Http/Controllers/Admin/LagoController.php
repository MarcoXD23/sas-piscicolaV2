<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LagoController extends Controller
{
    public function index(): View
    {
        $lagos = Lago::query()->with('siembrasActivas')->orderBy('nombre')->get();

        return view('admin.lagos.index', [
            'lagos' => $lagos,
            'tipos' => Lago::TIPOS,
            'especies' => collect(\App\Support\CatalogoEspecies::todas())->map(fn ($ficha) => $ficha['nombre'])->all(),
            'stocksDisponibles' => \App\Models\StockAlimento::query()
                ->where('cantidad_kg', '>', 0)
                ->orderBy('tipo_concentrado')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:255',
                // Un lago debe tener nombre único dentro del tenant
                Rule::unique('lagos')->where('tenant_id', $request->user()->tenant_id),
            ],
            'tipo' => ['required', 'string', Rule::in(Lago::TIPOS)],
        ]);

        Lago::create($datos);

        return redirect()->route('admin.lagos.index')
            ->with('estado', "Lago «{$datos['nombre']}» registrado.");
    }
}
