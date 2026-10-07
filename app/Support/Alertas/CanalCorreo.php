<?php

namespace App\Support\Alertas;

use App\Models\Alerta;
use App\Models\User;
use App\Notifications\AlertaCritica;

/**
 * Envía la alerta crítica por correo (configuración MAIL_* de la Fase 17).
 */
class CanalCorreo implements CanalNotificacion
{
    public function disponible(): bool
    {
        return true;
    }

    public function enviar(User $destinatario, Alerta $alerta): void
    {
        $destinatario->notify(new AlertaCritica($alerta));
    }
}
