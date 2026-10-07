<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Panel de plataforma: todas las fincas, su personal y su estado. Suspender o reactivar una finca.
 * Sin finca activa, el global scope de tenant no filtra: aquí se ven todas a propósito.
 */
class FincaController extends Controller
{
    public function index(): View
    {
        return view('plataforma.fincas', [
            'fincas' => Tenant::query()->withCount('users')->orderBy('nombre')->get(),
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

    public function reactivar(Tenant $finca): RedirectResponse
    {
        $finca->forceFill(['suspendida_at' => null, 'motivo_suspension' => null])->save();

        return back()->with('estado', __('plataforma.fincas.reactivada', ['finca' => $finca->nombre]));
    }
}
