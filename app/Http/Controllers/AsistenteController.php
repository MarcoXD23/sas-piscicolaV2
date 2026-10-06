<?php

namespace App\Http\Controllers;

use App\Support\Asistente\AsistenteNoDisponible;
use App\Support\Asistente\ClienteAsistente;
use App\Support\Asistente\ContextoFinca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Widget del asistente IA. La conversación vive en la sesión del usuario.
 */
class AsistenteController extends Controller
{
    /** Mensajes de la conversación que se envían en cada pregunta (los más recientes). */
    public const MAX_MENSAJES = 10;

    private const CLAVE_SESION = 'asistente.mensajes';

    public function __construct(private readonly ClienteAsistente $cliente) {}

    public function historial(Request $request): JsonResponse
    {
        return response()->json([
            'configurado' => $this->cliente->configurado(),
            'mensajes' => $request->session()->get(self::CLAVE_SESION, []),
        ]);
    }

    public function enviar(Request $request): JsonResponse
    {
        $datos = $request->validate(['mensaje' => ['required', 'string', 'max:1000']], [
            'mensaje.required' => 'Escribe tu pregunta.',
            'mensaje.max' => 'La pregunta es muy larga (máximo 1.000 caracteres).',
        ]);

        $historial = $request->session()->get(self::CLAVE_SESION, []);
        $mensajes = $this->recortar([...$historial, ['role' => 'user', 'content' => trim($datos['mensaje'])]]);

        try {
            $respuesta = $this->cliente->responder(ContextoFinca::instrucciones(), ContextoFinca::para($request->user()), $mensajes);
        } catch (AsistenteNoDisponible $e) {
            // La pregunta no se guarda: así la conversación sigue alternando usuario/asistente
            return response()->json(['error' => $e->getMessage()], 503);
        }

        $mensajes[] = ['role' => 'assistant', 'content' => $respuesta];
        $request->session()->put(self::CLAVE_SESION, $this->recortar($mensajes));

        return response()->json(['respuesta' => $respuesta]);
    }

    public function reiniciar(Request $request): Response
    {
        $request->session()->forget(self::CLAVE_SESION);

        return response()->noContent();
    }

    /**
     * Deja los mensajes más recientes, empezando siempre por uno del usuario.
     *
     * @param  list<array{role: string, content: string}>  $mensajes
     * @return list<array{role: string, content: string}>
     */
    private function recortar(array $mensajes): array
    {
        $mensajes = array_slice($mensajes, -self::MAX_MENSAJES);
        while ($mensajes !== [] && $mensajes[0]['role'] !== 'user') {
            array_shift($mensajes);
        }

        return array_values($mensajes);
    }
}
