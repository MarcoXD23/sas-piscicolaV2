<?php

namespace App\Support\Asistente;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;

/**
 * Asistente con Gemini (API de Google), llamado desde el servidor con el cliente HTTP de Laravel.
 * La clave viaja en la cabecera x-goog-api-key: nunca en la URL, en los logs ni en el navegador.
 * Los errores pasajeros (500 y 503) se reintentan con espera creciente y, si siguen, se prueba una vez el modelo de respaldo.
 */
class GeminiAsistente implements ClienteAsistente
{
    public const URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    /** Estados HTTP pasajeros: el servicio está saturado o falló por dentro. */
    public const PASAJEROS = [500, 503];

    /** Segundos de espera antes de cada reintento del modelo principal. */
    public const ESPERAS = [1, 3];

    /** Motivos de corte que significan que Gemini se negó a responder. */
    private const BLOQUEOS = ['SAFETY', 'PROHIBITED_CONTENT', 'BLOCKLIST', 'SPII', 'RECITATION', 'IMAGE_SAFETY'];

    public function __construct(
        private readonly ?string $clave,
        private readonly string $modelo,
        private readonly ?string $modeloRespaldo = null,
    ) {}

    public function configurado(): bool
    {
        return filled($this->clave);
    }

    public function responder(string $instrucciones, string $contexto, array $mensajes): string
    {
        if (! $this->configurado()) {
            throw new AsistenteNoDisponible(__('asistente.errores.falta_clave_gemini'));
        }

        $cuerpo = [
            // Mismas instrucciones y datos de la finca que con Claude
            'systemInstruction' => ['parts' => [['text' => $instrucciones], ['text' => $contexto]]],
            'contents' => array_map(fn (array $mensaje) => [
                'role' => $mensaje['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => $mensaje['content']]],
            ], $mensajes),
            'generationConfig' => ['maxOutputTokens' => 8000],
        ];

        // Modelo principal: un reintento por cada espera, solo ante errores pasajeros
        $respuesta = $this->enviar($this->modelo, $cuerpo);
        foreach (self::ESPERAS as $intento => $segundos) {
            if (! $this->pasajero($respuesta)) {
                break;
            }
            $this->registrarPasajero($respuesta, $this->modelo, $intento + 1);
            Sleep::for($segundos)->seconds();
            $respuesta = $this->enviar($this->modelo, $cuerpo);
        }

        // Modelo de respaldo: un solo intento, si el principal siguió saturado
        if ($this->pasajero($respuesta) && filled($this->modeloRespaldo) && $this->modeloRespaldo !== $this->modelo) {
            $this->registrarPasajero($respuesta, $this->modelo, count(self::ESPERAS) + 1);
            $respuesta = $this->enviar($this->modeloRespaldo, $cuerpo);
        }

        if ($respuesta->failed()) {
            $this->fallo($respuesta);
        }

        if ($respuesta->json('promptFeedback.blockReason')) {
            throw new AsistenteNoDisponible(__('asistente.errores.pregunta_bloqueada'));
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
                ? __('asistente.errores.pregunta_bloqueada')
                : __('asistente.errores.sin_respuesta'));
        }

        return trim($texto);
    }

    private function enviar(string $modelo, array $cuerpo): Response
    {
        try {
            return Http::withHeaders(['x-goog-api-key' => $this->clave])
                ->acceptJson()
                ->connectTimeout(10)
                ->timeout(60)
                ->post(sprintf(self::URL, rawurlencode($modelo)), $cuerpo);
        } catch (ConnectionException) {
            throw new AsistenteNoDisponible(__('asistente.errores.sin_conexion'));
        }
    }

    private function pasajero(Response $respuesta): bool
    {
        return in_array($respuesta->status(), self::PASAJEROS, true);
    }

    /** Deja rastro de cada error pasajero (estado, código, modelo e intento). Nunca registra la clave. */
    private function registrarPasajero(Response $respuesta, string $modelo, int $intento): void
    {
        Log::warning('Asistente IA: Gemini saturado, se reintenta', [
            'estado' => $respuesta->status(),
            'error' => (string) $respuesta->json('error.status'),
            'modelo' => $modelo,
            'intento' => $intento,
        ]);
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
            throw new AsistenteNoDisponible(__('asistente.errores.clave_invalida'));
        }

        if ($estado === 429) {
            throw new AsistenteNoDisponible(__('asistente.errores.ocupado'));
        }

        Log::error('Asistente IA: error de la API de Gemini', ['estado' => $estado, 'error' => $error]);

        throw new AsistenteNoDisponible(__(in_array($estado, self::PASAJEROS, true)
            ? 'asistente.errores.saturado'
            : 'asistente.errores.no_pudo_responder'));
    }
}
