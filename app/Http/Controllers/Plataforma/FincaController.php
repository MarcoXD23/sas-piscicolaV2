<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\InvitacionFinca;
use App\Models\Rol;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Datos\EliminadorFinca;
use App\Support\TenantActual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Panel de plataforma: todas las fincas, su personal y su estado. Suspender o reactivar una finca.
 * Aquí se ven todas las fincas a propósito: el personal se cuenta dentro de TenantActual::sinFiltro().
 */
class FincaController extends Controller
{
    public function index(): View
    {
        return view('plataforma.fincas', [
            // Con el propietario (o los propietarios) de cada finca en una sola consulta: solo nombre y correo, para
            // gestionar la cuenta y dar soporte (nada del resto del personal ni de los datos de la finca)
            'fincas' => TenantActual::sinFiltro(fn () => Tenant::query()->withCount('users')
                ->with(['users' => fn ($consulta) => $consulta->select(['id', 'tenant_id', 'name', 'email'])
                    ->whereHas('roles', fn ($rol) => $rol->where('nombre', Rol::PROPIETARIO))->orderBy('name')])
                ->orderBy('nombre')->get()),
            // Invitaciones para crear fincas: las más nuevas primero (las de los últimos 90 días)
            'invitaciones' => InvitacionFinca::query()->with('fincaCreada')->where('created_at', '>=', now()->subDays(90))->latest('id')->get(),
        ]);
    }

    public function suspender(Request $request, Tenant $finca): RedirectResponse
    {
        $datos = $request->validate(['motivo_suspension' => ['nullable', 'string', 'max:255']]);

        DB::transaction(function () use ($finca, $datos) {
            $finca->forceFill(['suspendida_at' => now(), 'motivo_suspension' => $datos['motivo_suspension'] ?? null])->save();

            // Los accesos de la API se cortan de inmediato; las sesiones web se cierran en el siguiente clic
            PersonalAccessToken::query()
                ->where('tokenable_type', (new User)->getMorphClass())
                ->whereIn('tokenable_id', User::withoutGlobalScopes()->where('tenant_id', $finca->id)->select('id'))
                ->delete();
        });

        return back()->with('estado', __('plataforma.fincas.suspendida', ['finca' => $finca->nombre]));
    }

    /** El administrador de plataforma también puede pedir la eliminación: código de la finca y su propia contraseña. */
    public function eliminar(Request $request, Tenant $finca): RedirectResponse
    {
        $request->validate([
            'codigo' => ['required', 'string', function ($atributo, $valor, $falla) use ($finca) {
                if (! $finca->tieneCodigo((string) $valor)) {
                    $falla(__('datos.eliminar.codigo_incorrecto'));
                }
            }],
            'actual' => ['required', 'string', 'current_password:plataforma'],
        ], ['actual.current_password' => __('cuenta.contrasena.actual_incorrecta')]);

        EliminadorFinca::solicitar($finca);

        return back()->with('estado', __('datos.eliminar.programada_plataforma', [
            'finca' => $finca->nombre, 'fecha' => $finca->fresh()->eliminacion_programada_at->format('d/m/Y'),
        ]));
    }

    public function cancelarEliminacion(Tenant $finca): RedirectResponse
    {
        abort_unless($finca->enEliminacion(), 404);
        EliminadorFinca::cancelar($finca);

        return back()->with('estado', __('datos.eliminar.cancelada'));
    }

    public function reactivar(Tenant $finca): RedirectResponse
    {
        // Reactivar una finca en eliminación también cancela su borrado
        if ($finca->enEliminacion()) {
            EliminadorFinca::cancelar($finca);
        }
        $finca->forceFill(['suspendida_at' => null, 'motivo_suspension' => null])->save();

        return back()->with('estado', __('plataforma.fincas.reactivada', ['finca' => $finca->nombre]));
    }
}
