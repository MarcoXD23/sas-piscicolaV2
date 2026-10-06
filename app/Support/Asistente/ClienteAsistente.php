<?php

namespace App\Support\Asistente;

/**
 * Proveedor del asistente IA. La aplicación usa ClaudeAsistente; las pruebas usan un doble que no llama a la API.
 */
interface ClienteAsistente
{
    public function configurado(): bool;

    /**
     * @param  string  $instrucciones  Instrucciones fijas (cacheables)
     * @param  string  $contexto  Datos actuales de la finca, filtrados por el rol de quien pregunta
     * @param  list<array{role: 'user'|'assistant', content: string}>  $mensajes  Conversación, terminando en el mensaje del usuario
     *
     * @throws AsistenteNoDisponible si el servicio falla o rechaza la pregunta
     */
    public function responder(string $instrucciones, string $contexto, array $mensajes): string;
}
