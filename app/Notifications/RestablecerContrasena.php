<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo con el botón "Restablecer contraseña". Los textos están en lang/es/acceso.php («correo_recuperar»).
 */
class RestablecerContrasena extends Notification
{
    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $minutos = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('acceso.correo_recuperar.asunto', ['app' => config('app.name')]))
            ->greeting(__('acceso.correo_recuperar.saludo', ['nombre' => $notifiable->name]))
            ->line(__('acceso.correo_recuperar.motivo'))
            ->action(__('acceso.correo_recuperar.boton'), $url)
            ->line(__('acceso.correo_recuperar.vence', ['minutos' => $minutos]))
            ->line(__('acceso.correo_recuperar.ignorar'))
            ->salutation(__('acceso.correo_recuperar.despedida', ['app' => config('app.name')]));
    }
}
