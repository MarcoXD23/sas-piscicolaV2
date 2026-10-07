<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AsignarRolesRequest;
use App\Models\Bitacora;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Gestión de personal (exclusivo del propietario).
 * Nunca muestra ni permite asignar el rol propietario. Cada aprobación y cada cambio de roles queda en la bitácora.
 */
class PersonalController extends Controller
{
    public function index(Request $request): View
    {
        $personal = $this->personalQuery()
            ->with('roles')
            ->orderByRaw('aprobado_at IS NOT NULL')
            ->orderBy('name')
            ->get();

        return view('admin.personal.index', [
            'personal' => $personal,
            'rolesAsignables' => Rol::ASIGNABLES,
            'soyAdministrador' => $request->user()->tieneRol(Rol::ADMINISTRADOR),
            // Historial de aprobaciones y cambios de roles de cada persona (de la bitácora, solo inserción)
            'historial' => Bitacora::query()->with('usuario')
                ->whereIn('accion', ['aprobacion', 'roles'])
                ->whereIn('persona_id', $personal->pluck('id')->push($request->user()->id))
                ->latest('ocurrido_at')->latest('id')
                ->get()
                ->groupBy('persona_id'),
        ]);
    }

    public function update(AsignarRolesRequest $request, int $usuario): RedirectResponse
    {
        /** @var User $persona */
        $persona = $this->personalQuery()->findOrFail($usuario);

        DB::transaction(function () use ($persona, $request): void {
            $estabaAprobado = $persona->estaAprobado();
            $antes = $persona->roles()->pluck('nombre')->all();

            $persona->asignarRoles($request->validated('roles'));

            if (! $estabaAprobado) {
                $persona->aprobado_at = now();
                $persona->save();
            }

            $this->registrarCambio($request->user(), $persona, $estabaAprobado ? 'roles' : 'aprobacion', $antes);
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

        DB::transaction(function () use ($usuario, $activar): void {
            $antes = $usuario->roles()->pluck('nombre')->all();
            $roles = collect($antes)->reject(fn (string $rol) => $rol === Rol::ADMINISTRADOR)->values()->all();
            $usuario->asignarRoles($activar ? [...$roles, Rol::ADMINISTRADOR] : $roles);

            $this->registrarCambio($usuario, $usuario, 'roles', $antes);
        });

        return redirect()->route('admin.personal.index')
            ->with('estado', __($activar ? 'mensajes.personal.ahora_eres_administrador' : 'mensajes.personal.ya_no_eres_administrador'));
    }

    /**
     * Deja en la bitácora la aprobación o el cambio de roles (con los roles de antes y de después).
     * Si los roles no cambiaron no se registra nada.
     *
     * @param  list<string>  $antes
     */
    private function registrarCambio(User $quien, User $persona, string $accion, array $antes): void
    {
        $despues = $persona->roles()->pluck('nombre')->all();
        if ($accion === 'roles' && $this->ordenar($antes) === $this->ordenar($despues)) {
            return;
        }

        $nombres = fn (array $roles) => collect($this->ordenar($roles))->map(fn ($rol) => __('bitacora.roles.'.$rol))->join(', ') ?: __('personal.sin_roles');

        Bitacora::registrar($quien, $accion, null, [Rol::PROPIETARIO], [
            'persona' => $persona->name,
            'roles' => $nombres($despues),
            'antes' => $nombres($antes),
            'despues' => $nombres($despues),
        ], $persona);
    }

    /** @param  list<string>  $roles  @return list<string> En el orden de los roles (propietario, administrador, trabajador, celador) */
    private function ordenar(array $roles): array
    {
        return array_values(array_filter(Rol::TODOS, fn ($rol) => in_array($rol, $roles, true)));
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
