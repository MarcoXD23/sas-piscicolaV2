<?php

namespace App\Support\Asistente;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\RateLimitException;
use Illuminate\Support\Facades\Log;

/**
 * Asistente con Claude (API de Anthropic).
 */
class ClaudeAsistente implements ClienteAsistente
{
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
            throw new AsistenteNoDisponible('El asistente no está configurado. Pide al propietario que agregue la clave de Anthropic.');
        }

        try {
            $cliente = new Client(apiKey: $this->clave, requestOptions: ['timeout' => 60.0, 'maxRetries' => 2]);

            $respuesta = $cliente->beta->messages->create(
                model: $this->modelo,
                maxTokens: 8000,
                // Las instrucciones fijas van primero (prefijo cacheable); los datos de la finca cambian en cada pregunta
                system: [
                    ['type' => 'text', 'text' => $instrucciones, 'cacheControl' => ['type' => 'ephemeral']],
                    ['type' => 'text', 'text' => $contexto],
                ],
                messages: $mensajes,
                // Conversación sencilla: esfuerzo bajo para respuestas rápidas y económicas
                outputConfig: ['effort' => 'low'],
                // Si el modelo declina por política, el servidor reintenta con el respaldo por defecto
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException $e) {
            Log::error('Asistente IA: clave de Anthropic inválida', ['error' => $e->getMessage()]);
            throw new AsistenteNoDisponible('La clave del asistente no es válida. Avísale al propietario.');
        } catch (RateLimitException $e) {
            throw new AsistenteNoDisponible('El asistente está muy ocupado. Intenta de nuevo en un minuto.');
        } catch (APIConnectionException $e) {
            throw new AsistenteNoDisponible('No hay conexión con el asistente. Revisa el internet e intenta de nuevo.');
        } catch (APIStatusException $e) {
            Log::error('Asistente IA: error de la API', ['estado' => $e->getCode(), 'error' => $e->getMessage()]);
            throw new AsistenteNoDisponible('El asistente no pudo responder. Intenta de nuevo más tarde.');
        }

        if ($respuesta->stopReason === 'refusal') {
            throw new AsistenteNoDisponible('El asistente no puede responder esa pregunta.');
        }

        $texto = collect($respuesta->content)
            ->filter(fn ($bloque) => $bloque->type === 'text')
            ->map(fn ($bloque) => $bloque->text)
            ->implode("\n");

        if (trim($texto) === '') {
            throw new AsistenteNoDisponible('El asistente no devolvió una respuesta. Intenta de nuevo.');
        }

        return trim($texto);
    }
}
