<?php

namespace App\Support\Alertas;

use App\Models\Alerta;
use App\Models\User;

/**
 * Canal sin proveedor (por ejemplo WhatsApp mientras no se elija uno): no envía nada.
 */
class CanalNulo implements CanalNotificacion
{
    public function disponible(): bool
    {
        return false;
    }

    public function enviar(User $destinatario, Alerta $alerta): void {}
}
