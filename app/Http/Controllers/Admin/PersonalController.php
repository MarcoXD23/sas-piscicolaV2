<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsignarRolesRequest;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestión de personal (exclusivo del propietario).
 * Nunca muestra ni permite asignar el rol propietario.
 */
class PersonalController extends Controller
{
    public function index(): View
    {
        $personal = $this->personalQuery()
            ->with('roles')
            ->orderByRaw('aprobado_at IS NOT NULL')
            ->orderBy('name')
            ->get();

        return view('admin.personal.index', [
            'personal' => $personal,
            'rolesAsignables' => Rol::ASIGNABLES,
            'soyAdministrador' => request()->user()->tieneRol(Rol::ADMINISTRADOR),
        ]);
    }

    public function update(AsignarRolesRequest $request, int $usuario): RedirectResponse
    {
        /** @var User $persona */
        $persona = $this->personalQuery()->findOrFail($usuario);

        DB::transaction(function () use ($persona, $request): void {
            $persona->asignarRoles($request->validated('roles'));

            if (! $persona->estaAprobado()) {
                $persona->aprobado_at = now();
                $persona->save();
            }
        });

        return redirect()->route('admin.personal.index')
            ->with('estado', __('mensajes.personal.roles_guardados', ['nombre' => $persona->name]));
    }

    /**
     * Finca pequeña: el propietario también puede ser el administrador. Solo agrega o quita el rol administrador
     * de quien inició sesión; nunca toca el rol propietario ni ofrece otros roles.
     */
    public function miRolAdministrador(Request $request): RedirectResponse
    {
        $activar = $request->validate(['administrador' => ['required', 'boolean']])['administrador'];
        $usuario = $request->user();

        $roles = $usuario->roles()->pluck('nombre')->reject(fn (string $rol) => $rol === Rol::ADMINISTRADOR)->values()->all();
        $usuario->asignarRoles($activar ? [...$roles, Rol::ADMINISTRADOR] : $roles);

        return redirect()->route('admin.personal.index')
            ->with('estado', __($activar ? 'mensajes.personal.ahora_eres_administrador' : 'mensajes.personal.ya_no_eres_administrador'));
    }

    /**
     * Usuarios del tenant actual que no son propietarios.
     */
    private function personalQuery(): Builder
    {
        return User::query()->whereDoesntHave(
            'roles',
            fn (Builder $q) => $q->where('nombre', Rol::PROPIETARIO)
        );
    }
}
