<?php

namespace App\Http\Controllers;

use App\Models\AceptacionLegal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Términos de uso y política de privacidad (páginas públicas) y su aceptación por versión.
 */
class LegalController extends Controller
{
    public function terminos(): View
    {
        return view('legal.documento', ['documento' => 'terminos']);
    }

    public function privacidad(): View
    {
        return view('legal.documento', ['documento' => 'privacidad']);
    }

    public function aceptar(Request $request): View|RedirectResponse
    {
        return AceptacionLegal::vigente($request->user()) ? redirect()->route('inicio') : view('legal.aceptar');
    }

    public function guardarAceptacion(Request $request): RedirectResponse
    {
        $request->validate(['acepto' => ['accepted']], ['acepto.accepted' => __('legal.aceptar.marca_la_casilla')]);
        AceptacionLegal::registrar($request->user());

        return redirect()->intended(route('inicio'))->with('estado', __('legal.aceptar.gracias'));
    }

    /** App móvil: acepta la versión vigente (la persona ya los leyó en las páginas públicas). */
    public function aceptarApi(Request $request): JsonResponse
    {
        $request->validate(['acepto' => ['accepted'], 'version' => ['required', 'string', 'in:'.config('legal.version')]]);
        AceptacionLegal::registrar($request->user());

        return response()->json(['mensaje' => __('legal.aceptar.gracias'), 'version' => config('legal.version')]);
    }
}
