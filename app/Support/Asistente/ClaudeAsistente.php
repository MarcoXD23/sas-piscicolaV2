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
            throw new AsistenteNoDisponible(__('asistente.errores.falta_clave_anthropic'));
        }

        try {
            $cliente = new Client(apiKey: $this->clave, requestOptions: ['timeout' => 60.0, 'maxRetries' => 2]);

            $respuesta = $cliente->beta->messages->create(...[
                'model' => $this->modelo,
                'maxTokens' => 8000,
                // Las instrucciones fijas van primero (prefijo cacheable); los datos de la finca cambian en cada pregunta
                'system' => [
                    ['type' => 'text', 'text' => $instrucciones, 'cacheControl' => ['type' => 'ephemeral']],
                    ['type' => 'text', 'text' => $contexto],
                ],
                'messages' => $mensajes,
                // Conversación sencilla: esfuerzo bajo para respuestas rápidas y económicas
                'outputConfig' => ['effort' => 'low'],
            ] + $this->opcionesRespaldo());
        } catch (AuthenticationException $e) {
            Log::error('Asistente IA: clave de Anthropic inválida', ['error' => $e->getMessage()]);
            throw new AsistenteNoDisponible(__('asistente.errores.clave_invalida'));
        } catch (RateLimitException $e) {
            throw new AsistenteNoDisponible(__('asistente.errores.ocupado'));
        } catch (APIConnectionException $e) {
            throw new AsistenteNoDisponible(__('asistente.errores.sin_conexion'));
        } catch (APIStatusException $e) {
            Log::error('Asistente IA: error de la API', ['estado' => $e->getCode(), 'error' => $e->getMessage()]);
            throw new AsistenteNoDisponible(__('asistente.errores.no_pudo_responder'));
        }

        if ($respuesta->stopReason === 'refusal') {
            throw new AsistenteNoDisponible(__('asistente.errores.pregunta_bloqueada'));
        }

        $texto = collect($respuesta->content)
            ->filter(fn ($bloque) => $bloque->type === 'text')
            ->map(fn ($bloque) => $bloque->text)
            ->implode("\n");

        if (trim($texto) === '') {
            throw new AsistenteNoDisponible(__('asistente.errores.sin_respuesta'));
        }

        return trim($texto);
    }

    /**
     * Si el modelo declina por política, el servidor reintenta con el respaldo por defecto. Solo se envía con los
     * modelos que lo admiten (services.anthropic.modelos_con_respaldo): con otro, la petición se rechazaría.
     *
     * @return array{fallbacks?: string, betas?: list<string>}
     */
    public function opcionesRespaldo(): array
    {
        if (! in_array($this->modelo, config('services.anthropic.modelos_con_respaldo', []), true)) {
            return [];
        }

        return ['fallbacks' => 'default', 'betas' => ['server-side-fallback-2026-07-01']];
    }
}
