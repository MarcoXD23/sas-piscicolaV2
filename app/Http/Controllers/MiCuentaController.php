<?php

namespace App\Http\Controllers;

use App\Rules\NoIgualAlCorreo;
use App\Support\DosPasos;
use App\Support\Hora;
use App\Support\Totp;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mi cuenta: cambiar la contraseña (pidiendo la actual) y activar o desactivar la verificación en dos pasos.
 * Todo actúa solo sobre la persona con sesión.
 */
class MiCuentaController extends Controller
{
    /** Clave nueva mientras la persona la agrega a su app (todavía no está activa). */
    private const CLAVE_NUEVA = 'dos_pasos_clave_nueva';

    /** Códigos de recuperación recién creados: se muestran una sola vez. */
    private const CODIGOS = 'dos_pasos_codigos';

    public function index(Request $request): View
    {
        $usuario = $request->user();

        return view('mi-cuenta.index', [
            'usuario' => $usuario,
            // «El de la finca» (vacío) dice cuál es, para elegir sabiendo
            'formatosHora' => ['' => __('cuenta.formato_hora.de_la_finca', ['formato' => __('region.formatos_hora_corto.'.Hora::deFinca($usuario->tenant))])]
                + collect(Hora::FORMATOS)->mapWithKeys(fn (string $f) => [$f => __('region.formatos_hora.'.$f)])->all(),
        ]);
    }

    /** Formato de hora de esta persona (12 o 24 horas); vacío = el de la finca. Solo cambia cómo se muestran las horas. */
    public function formatoHora(Request $request): RedirectResponse
    {
        $datos = $request->validate(['formato_hora' => ['nullable', Rule::in(Hora::FORMATOS)]]);
        $request->user()->forceFill(['formato_hora' => $datos['formato_hora'] ?? null])->save();

        return back()->with('estado', __('cuenta.formato_hora.guardado'));
    }

    /** Al cambiarla se cierran las demás sesiones, se invalida «recordarme» y se revocan los tokens de la API. */
    public function cambiarContrasena(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $datos = $request->validate([
            'actual' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults(), new NoIgualAlCorreo($usuario->email)],
        ], [
            'actual.current_password' => __('cuenta.contrasena.actual_incorrecta'),
        ]);

        DB::transaction(function () use ($usuario, $datos, $request) {
            $usuario->forceFill(['password' => $datos['password'], 'remember_token' => Str::random(60)])->save();
            $usuario->tokens()->delete();
            // Las demás sesiones de esta persona (en otros equipos) se cierran; esta sigue abierta
            DB::table(config('session.table', 'sessions'))->where('user_id', $usuario->id)->where('id', '!=', $request->session()->getId())->delete();
        });
        $request->session()->regenerate();

        return back()->with('estado', __('cuenta.contrasena.cambiada'));
    }

    /** Paso 1: clave nueva para agregar a la app (con código QR o escrita a mano). */
    public function activar(Request $request): Response|RedirectResponse
    {
        $usuario = $request->user();
        if ($usuario->tieneDosPasos()) {
            return redirect()->route('mi-cuenta.index');
        }

        $clave = $request->session()->get(self::CLAVE_NUEVA) ?? tap(Totp::nuevaClave(), fn ($c) => $request->session()->put(self::CLAVE_NUEVA, $c));
        $uri = Totp::uri($clave, $usuario->email, config('app.name'));

        // Muestra la clave secreta: no se guarda en ninguna caché (tampoco en la del teléfono, ver public/sw.js)
        return response()->view('mi-cuenta.activar', [
            'claveAgrupada' => Totp::agrupada($clave),
            // SVG generado en el servidor (sin servicios externos)
            'qr' => (new QRCode(new QROptions(['outputBase64' => false, 'svgAddXmlHeader' => false])))->render($uri),
        ])->header('Cache-Control', 'no-store');
    }

    /** Paso 2: con un código válido de la app queda activa y se muestran los códigos de recuperación. */
    public function confirmar(Request $request): RedirectResponse
    {
        $codigo = (string) $request->validate(['codigo' => ['required', 'string', 'max:20']])['codigo'];
        $clave = $request->session()->get(self::CLAVE_NUEVA);
        if ($clave === null || $request->user()->tieneDosPasos()) {
            return redirect()->route('mi-cuenta.index');
        }

        $codigos = DosPasos::activar($request->user(), $clave, $codigo);
        if ($codigos === null) {
            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.codigo_invalido')]);
        }

        $request->session()->forget(self::CLAVE_NUEVA);
        $request->session()->flash(self::CODIGOS, $codigos);

        return redirect()->route('mi-cuenta.dos-pasos.codigos');
    }

    public function codigos(Request $request): Response|RedirectResponse
    {
        $codigos = $request->session()->get(self::CODIGOS);

        return $codigos
            ? response()->view('mi-cuenta.codigos', ['codigos' => $codigos])->header('Cache-Control', 'no-store')
            : redirect()->route('mi-cuenta.index');
    }

    /** Desactivar pide la contraseña y un código válido (de la app o de recuperación). */
    public function desactivar(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        $datos = $request->validate([
            'actual' => ['required', 'string', 'current_password:web'],
            'codigo' => ['required', 'string', 'max:20'],
        ], [
            'actual.current_password' => __('cuenta.contrasena.actual_incorrecta'),
        ]);

        if (! $usuario->tieneDosPasos() || ! DosPasos::verificar($usuario, $datos['codigo'])) {
            throw ValidationException::withMessages(['codigo' => __('cuenta.dos_pasos.codigo_invalido')]);
        }

        DosPasos::desactivar($usuario);

        return redirect()->route('mi-cuenta.index')->with('estado', __('cuenta.dos_pasos.desactivada'));
    }
}
