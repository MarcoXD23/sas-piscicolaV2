<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tareas que el propietario o el administrador asignan al personal de campo.
 */
class TareaController extends Controller
{
    public function index(Request $request): View
    {
        $verCompletadas = $request->query('ver') === 'completadas';

        $tareas = Tarea::query()
            ->with(['asignado', 'lago', 'creador'])
            ->when($verCompletadas,
                fn ($consulta) => $consulta->whereNotNull('completada_at')->latest('completada_at'),
                fn ($consulta) => $consulta->pendientes()->orderBy('fecha'))
            ->paginate(30)
            ->withQueryString();

        return view('admin.tareas.index', [
            'tareas' => $tareas,
            'verCompletadas' => $verCompletadas,
            'personal' => User::query()->deCampo()->orderBy('name')->pluck('name', 'id')->all(),
            'lagos' => Lago::query()->orderBy('nombre')->pluck('nombre', 'id')->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant_id;

        $datos = $request->validate([
            'asignada_a' => ['required', 'integer', Rule::in(User::query()->deCampo()->pluck('id')->all())],
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'lago_id' => ['nullable', 'integer', Rule::exists('lagos', 'id')->where('tenant_id', $tenant)],
            'fecha' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'asignada_a.required' => 'Elige a quién se asigna.',
            'asignada_a.in' => 'Solo se asignan tareas a trabajadores o celadores aprobados.',
            'titulo.required' => 'Escribe qué hay que hacer.',
            'fecha.after_or_equal' => 'La fecha no puede ser pasada.',
        ]);

        $tarea = Tarea::create($datos + ['creada_por' => $request->user()->id]);

        return redirect()->route('admin.tareas.index')
            ->with('estado', "Tarea asignada a {$tarea->asignado->name} para el {$tarea->fecha->format('d/m/Y')}.");
    }

    public function destroy(Tarea $tarea): RedirectResponse
    {
        if ($tarea->estaCompletada()) {
            return back()->withErrors(['tarea' => 'Una tarea completada no se elimina: queda en el historial.']);
        }

        $tarea->delete();

        return back()->with('estado', 'Tarea eliminada.');
    }
}
