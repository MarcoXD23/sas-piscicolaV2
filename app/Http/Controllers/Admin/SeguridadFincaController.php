<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use App\Models\Ingreso;
use App\Models\Rol;
use App\Models\User;
use App\Support\Ajustes\AjustesFinca;
use App\Support\Ajustes\CatalogoAjustes;
use App\Support\Seguridad\Sesiones;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ajustes › Seguridad de la finca (solo propietario): verificación en dos pasos obligatoria para propietario y
 * administrador, cerrar la sesión de todo el personal e ingresos del personal.
 */
class SeguridadFincaController extends Controller
{
    /** El ajuste «Verificación en dos pasos obligatoria» (queda en la bitácora como los demás ajustes). */
    public function guardar(Request $request): RedirectResponse
    {
        $campo = CatalogoAjustes::campo(CatalogoAjustes::SEGURIDAD_DOS_PASOS);
        $datos = $request->validate([$campo => ['required', 'string', Rule::in(CatalogoAjustes::definicion(CatalogoAjustes::SEGURIDAD_DOS_PASOS)['opciones'])]]);
        $cambios = AjustesFinca::guardar($request->user(), $datos);

        return redirect()->to(route('admin.ajustes.edit').'#seguridad')
            ->with('estado', __($cambios > 0 ? 'ajustes_finca.guardados' : 'ajustes_finca.sin_cambios'));
    }

    /** Cierra la sesión de todo el personal menos la propia, con la contraseña; revoca sus tokens y queda en la bitácora. */
    public function cerrarSesiones(Request $request): RedirectResponse
    {
        $request->validate(['actual' => ['required', 'string', 'current_password:web']], [
            'actual.current_password' => __('cuenta.contrasena.actual_incorrecta'),
        ]);

        $personas = Sesiones::cerrarPersonal($request->user());
        Bitacora::registrar($request->user(), 'sesiones_cerradas', null, [Rol::PROPIETARIO], ['personas' => $personas]);

        return redirect()->to(route('admin.ajustes.edit').'#seguridad')
            ->with('estado', trans_choice('seguridad.personal.cerradas', $personas, ['cantidad' => $personas]));
    }

    /** Ingresos e intentos fallidos de la finca (los 90 días que se guardan), filtrables por persona. */
    public function ingresos(Request $request): View
    {
        $personal = User::query()->orderBy('name')->get(['id', 'name']);
        $persona = $personal->firstWhere('id', (int) $request->query('persona'));

        return view('admin.seguridad.ingresos', [
            'personal' => $personal,
            'persona' => $persona,
            'ingresos' => Ingreso::query()->with('usuario:id,name')
                ->when($persona, fn ($consulta) => $consulta->where('user_id', $persona->id))
                ->latest('id')->paginate(50)->withQueryString(),
        ]);
    }
}
