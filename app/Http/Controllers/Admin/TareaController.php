<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lago;
use App\Models\Tarea;
use App\Models\User;
use App\Support\Region;
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
            'fecha' => ['required', 'date', 'after_or_equal:'.Region::hoy()->toDateString()],
        ], [
            'asignada_a.required' => __('mensajes.tarea.elige_quien_asigna'),
            'asignada_a.in' => __('mensajes.tarea.solo_asignan_tareas_trabajadores_celadores'),
            'titulo.required' => __('mensajes.tarea.escribe_hay_hacer'),
            'fecha.after_or_equal' => __('mensajes.tarea.fecha_no_puede_ser_pasada'),
        ]);

        $tarea = Tarea::create($datos + ['creada_por' => $request->user()->id]);

        return redirect()->route('admin.tareas.index')
            ->with('estado', __('mensajes.tarea.asignada', ['nombre' => $tarea->asignado->name, 'fecha' => $tarea->fecha->format('d/m/Y')]));
    }

    public function destroy(Tarea $tarea): RedirectResponse
    {
        if ($tarea->estaCompletada()) {
            return back()->withErrors(['tarea' => __('mensajes.tarea.tarea_completada_no_elimina_queda')]);
        }

        $tarea->delete();

        return back()->with('estado', __('mensajes.tarea.tarea_eliminada'));
    }
}
