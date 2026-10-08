<?php

namespace App\Http\Controllers;

use App\Support\Campo\PaqueteTrabajo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Modo de campo (registrar sin conexión sin haber abierto antes las pantallas):
 * - index: la pantalla «Registro rápido» es un cascarón PÚBLICO y SIN DATOS (ni de la finca ni de la sesión): el
 *   service worker la guarda al instalarse y la arma en el teléfono con el paquete guardado en IndexedDB.
 * - paquete: los datos mínimos para registrar, solo de la finca activa y del rol (App\Support\Campo\PaqueteTrabajo).
 * - token: un token CSRF vigente, que la cola pide justo antes de enviar (nunca el de una página guardada).
 * El paquete y el token nunca se guardan en caché (no-store).
 */
class CampoController extends Controller
{
    public function index(): Response
    {
        // no-cache: el teléfono puede usar su copia sin señal, pero con señal pregunta si hay una versión nueva
        return response()->view('campo.index')->header('Cache-Control', 'no-cache');
    }

    public function paquete(Request $request): JsonResponse
    {
        return response()->json(PaqueteTrabajo::para($request->user()))
            ->header('Cache-Control', 'no-store, private');
    }

    public function token(Request $request): JsonResponse
    {
        return response()->json(['token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store, private');
    }
}
