<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Solicitud de acceso del personal: queda pendiente y sin roles
 * hasta que el propietario la apruebe en /admin/personal.
 */
class RegistroController extends Controller
{
    public function create(): View
    {
        return view('auth.registro');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'codigo_finca' => ['required', 'string', 'exists:tenants,codigo'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'codigo_finca.exists' => __('mensajes.registro.no_existe_finca_ese_codigo'),
        ]);

        $tenant = Tenant::query()->where('codigo', $datos['codigo_finca'])->firstOrFail();

        $usuario = new User([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);
        $usuario->tenant_id = $tenant->id;
        $usuario->aprobado_at = null;
        $usuario->save();

        return redirect()->route('login')
            ->with('estado', __('mensajes.registro.solicitud_enviada_propietario_finca_debe'));
    }
}
