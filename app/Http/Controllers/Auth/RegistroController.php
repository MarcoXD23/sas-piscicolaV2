<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AceptacionLegal;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\NoIgualAlCorreo;
use App\Support\TenantActual;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
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
            'codigo_finca' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8), new NoIgualAlCorreo($request->input('email'))],
            'acepto' => ['accepted'],
        ], [
            'acepto.accepted' => __('legal.aceptar.marca_la_casilla'),
        ]);

        // Un solo mensaje para «código inexistente» y «correo ya registrado»: no revela qué fincas ni qué correos existen
        $tenant = Tenant::query()->where('codigo', $datos['codigo_finca'])->first();
        // El correo es único en todo el sistema: se revisa en todas las fincas
        $correoUsado = TenantActual::sinFiltro(fn () => User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($datos['email'])])->exists());
        if ($tenant === null || $correoUsado) {
            throw ValidationException::withMessages(['registro' => __('mensajes.registro.no_se_pudo')]);
        }

        $usuario = new User([
            'name' => $datos['name'],
            'email' => $datos['email'],
            'password' => $datos['password'],
        ]);
        $usuario->tenant_id = $tenant->id;
        $usuario->aprobado_at = null;
        DB::transaction(function () use ($usuario) {
            $usuario->save();
            // Quién aceptó, qué versión y cuándo (sin la IP)
            AceptacionLegal::registrar($usuario);
        });

        return redirect()->route('login')
            ->with('estado', __('mensajes.registro.solicitud_enviada_propietario_finca_debe'));
    }
}
