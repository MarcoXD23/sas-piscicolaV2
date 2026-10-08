<?php

namespace App\Http\Controllers\Plataforma;

use App\Http\Controllers\Controller;
use App\Models\AdministradorPlataforma;
use App\Support\DosPasos;
use App\Support\Totp;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Verificación en dos pasos del panel de plataforma (obligatoria):
 * - Al entrar: la contraseña ya se revisó (LoginController), pero no se abre sesión hasta escribir un código válido
 *   de la app o uno de recuperación. El pendiente vence a los 5 minutos.
 * - Activarla: quien entra sin tenerla activa no puede usar el panel hasta activarla (PlataformaConDosPasos).
 * No se desactiva desde el panel: se quita en el servidor con php artisan piscicola:plataforma-quitar-dos-pasos.
 */
class DosPasosController extends Controller
{
    public const SESION = 'plataforma_dos_pasos_pendiente';

    public const MINUTOS = 5;

    /** Intentos por minuto por persona (además del límite por IP de la ruta). */
    public const INTENTOS = 5;

    /** Clave nueva mientras se agrega a la app (todavía no está activa). */
    private const CLAVE_NUEVA = 'plataforma_dos_pasos_clave_nueva';

    /** Códigos de recuperación recién creados: se muestran una sola vez. */
    private const CODIGOS = 'plataforma_dos_pasos_codigos';

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->pendiente($request) === null) {
            return redirect()->route('plataforma.login');
        }

        return view('plataforma.dos-pasos');
    }

    public function store(Request $request): RedirectResponse
    {
        $administrador = $this->pendiente($request);
        if ($administrador === null) {
            return redirect()->route('plataforma.login')->withErrors(['email' => __('cuenta.dos_pasos.vencido')]);
        }

        $codigo = (string) $request->validate(['codigo' => ['required', 'string', 'max:20']])['codigo'];

        $clave = 'plataforma-dos-pasos|'.$administrador->id;
        if (RateLimiter::tooManyAttempts($clave, self::INTENTOS)) {
            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.demasiados', ['segundos' => RateLimiter::availableIn($clave)])]);
        }

        if (! DosPasos::verificar($administrador, $codigo)) {
            RateLimiter::hit($clave);

            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.codigo_invalido')]);
        }

        RateLimiter::clear($clave);
        $request->session()->forget(self::SESION);
        Auth::guard('plataforma')->login($administrador);
        $request->session()->regenerate();

        return redirect()->intended(route('plataforma.fincas.index'));
    }

    /** Paso 1: clave nueva para agregar a la app (con código QR o escrita a mano). */
    public function activar(Request $request): Response|RedirectResponse
    {
        $administrador = $request->user('plataforma');
        if ($administrador->tieneDosPasos()) {
            return redirect()->route('plataforma.fincas.index');
        }

        $clave = $request->session()->get(self::CLAVE_NUEVA) ?? tap(Totp::nuevaClave(), fn ($c) => $request->session()->put(self::CLAVE_NUEVA, $c));
        $uri = Totp::uri($clave, $administrador->email, config('app.name').' · '.__('plataforma.panel'));

        // Muestra la clave secreta: no se guarda en ninguna caché (tampoco en la del teléfono, ver public/sw.js)
        return response()->view('plataforma.activar', [
            'claveAgrupada' => Totp::agrupada($clave),
            'qr' => (new QRCode(new QROptions(['outputBase64' => false, 'svgAddXmlHeader' => false])))->render($uri),
        ])->header('Cache-Control', 'no-store');
    }

    /** Paso 2: con un código válido de la app queda activa y se muestran los códigos de recuperación. */
    public function confirmar(Request $request): RedirectResponse
    {
        $administrador = $request->user('plataforma');
        $codigo = (string) $request->validate(['codigo' => ['required', 'string', 'max:20']])['codigo'];
        $clave = $request->session()->get(self::CLAVE_NUEVA);
        if ($clave === null || $administrador->tieneDosPasos()) {
            return redirect()->route('plataforma.fincas.index');
        }

        $codigos = DosPasos::activar($administrador, $clave, $codigo);
        if ($codigos === null) {
            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.codigo_invalido')]);
        }

        $request->session()->forget(self::CLAVE_NUEVA);
        $request->session()->flash(self::CODIGOS, $codigos);

        return redirect()->route('plataforma.dos-pasos.codigos');
    }

    public function codigos(Request $request): Response|RedirectResponse
    {
        $codigos = $request->session()->get(self::CODIGOS);

        return $codigos
            ? response()->view('plataforma.codigos', ['codigos' => $codigos])->header('Cache-Control', 'no-store')
            : redirect()->route('plataforma.fincas.index');
    }

    /** Administrador con la contraseña ya revisada y el código pendiente; null si no hay o ya venció. */
    private function pendiente(Request $request): ?AdministradorPlataforma
    {
        $pendiente = $request->session()->get(self::SESION);
        if (! is_array($pendiente) || ($pendiente['vence'] ?? 0) < now()->getTimestamp()) {
            $request->session()->forget(self::SESION);

            return null;
        }

        $administrador = AdministradorPlataforma::find($pendiente['administrador']);

        return $administrador?->tieneDosPasos() ? $administrador : null;
    }
}
