<?php

namespace App\Support\Asistente;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asistente con Gemini (API de Google), llamado desde el servidor con el cliente HTTP de Laravel.
 * La clave viaja en la cabecera x-goog-api-key: nunca en la URL, en los logs ni en el navegador.
 */
class GeminiAsistente implements ClienteAsistente
{
    public const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Motivos de corte que significan que Gemini se negó a responder. */
    private const BLOQUEOS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    public function __construct(
        private readonly ?string $clave,
        private readonly string $modelo,
    ) {}

    public function configurado(): bool
    {
        return filled($this->clave);
    }

    public function responder(string $instrucciones, string $contexto, array $mensajes): string
    {
        if (! $this->configurado()) {
            throw new AsistenteNoDisponible('El asistente no está configurado. Pide al propietario que agregue la clave de Gemini.');
        }

        try {
            $respuesta = Http::withHeaders(['x-goog-api-key' => $this->clave])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(60)
                ->post(sprintf(self::URL, rawurlencode($this->modelo)), [
                    // Mismas instrucciones y datos de la finca que con Claude
                    'systemInstruction' => ['parts' => [['text' => $instrucciones], ['text' => $contexto]]],
                    'contents' => array_map(fn (array $mensaje) => [
                        'role' => $mensaje['role'] === 'assistant' ? 'model' : 'user',
                        'parts' => [['text' => $mensaje['content']]],
                    ], $mensajes),
                    'generationConfig' => ['maxOutputTokens' => 8000],
                ]);
        } catch (ConnectionException) {
            throw new AsistenteNoDisponible('No hay conexión con el asistente. Revisa el internet e intenta de nuevo.');
        }

        if ($respuesta->failed()) {
            $this->fallo($respuesta);
        }

        if ($respuesta->json('promptFeedback.blockReason')) {
            throw new AsistenteNoDisponible('El asistente no puede responder esa pregunta.');
        }

        $candidato = $respuesta->json('candidates.0');
        // Las partes marcadas como "thought" son razonamiento interno del modelo: no se muestran
        $texto = collect($candidato['content']['parts'] ?? [])
            ->reject(fn ($parte) => ($parte['thought'] ?? false) === true)
            ->pluck('text')
            ->filter()
            ->implode("\n");

        if (trim($texto) === '') {
            throw new AsistenteNoDisponible(in_array($candidato['finishReason'] ?? null, self::BLOQUEOS, true)
                ? 'El asistente no puede responder esa pregunta.'
                : 'El asistente no devolvió una respuesta. Intenta de nuevo.');
        }

        return trim($texto);
    }

    /** Traduce los errores HTTP de Gemini a mensajes para el usuario. Nunca registra la clave. */
    private function fallo(Response $respuesta): never
    {
        $estado = $respuesta->status();
        $error = (string) $respuesta->json('error.status');
        $claveInvalida = in_array($estado, [401, 403], true)
            || ($estado === 400 && str_contains((string) $respuesta->json('error.message'), 'API key'));

        if ($claveInvalida) {
            Log::error('Asistente IA: clave de Gemini inválida', ['estado' => $estado, 'error' => $error]);
            throw new AsistenteNoDisponible('La clave del asistente no es válida. Avísale al propietario.');
        }

        if ($estado === 429) {
            throw new AsistenteNoDisponible('El asistente está muy ocupado. Intenta de nuevo en un minuto.');
        }

        Log::error('Asistente IA: error de la API de Gemini', ['estado' => $estado, 'error' => $error]);
        throw new AsistenteNoDisponible('El asistente no pudo responder. Intenta de nuevo más tarde.');
    }
}
