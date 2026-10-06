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
        $lagos = Lago::query()->with('siembraActiva')->orderBy('nombre')->get();

        return view('admin.lagos.index', [
            'lagos' => $lagos,
            'tipos' => Lago::TIPOS,
            'especies' => \App\Models\Siembra::ESPECIES,
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
