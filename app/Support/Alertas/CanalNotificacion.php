<?php

namespace App\Support\Alertas;

use App\Models\Alerta;
use App\Models\User;

/**
 * Canal por el que se avisa una alerta crítica (correo, WhatsApp...). Sin acoplarse a un proveedor:
 * cada canal decide si está configurado y cómo envía.
 */
interface CanalNotificacion
{
    /** ¿Está configurado y puede enviar? */
    public function disponible(): bool;

    public function enviar(User $destinatario, Alerta $alerta): void;
}
