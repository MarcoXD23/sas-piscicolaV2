<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al administrador de plataforma: una comprobación del sistema empezó a fallar, o todo se recuperó.
 * Solo dice qué comprobación falló: nunca datos de las fincas ni de la configuración.
 */
class AvisoSalud extends Notification
{
    /** @param  list<string>  $fallas */
    public function __construct(public readonly array $fallas, public readonly bool $recuperado = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        if ($this->recuperado) {
            return (new MailMessage)
                ->subject(__('salud.correo.asunto_recuperado', ['app' => $app]))
                ->line(__('salud.correo.recuperado', ['app' => $app, 'url' => config('app.url')]));
        }

        $correo = (new MailMessage)->error()
            ->subject(__('salud.correo.asunto_falla', ['app' => $app]))
            ->line(__('salud.correo.falla', ['app' => $app, 'url' => config('app.url')]));
        foreach ($this->fallas as $falla) {
            $correo->line('- '.__('salud.comprobaciones.'.$falla));
        }

        return $correo->line(__('salud.correo.revisar'));
    }
}
