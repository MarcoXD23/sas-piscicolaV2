<?php

namespace App\Notifications;

use App\Models\Alerta;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Correo al propietario cuando se abre una alerta crítica. Textos en lang/es/alertas.php («correo»).
 */
class AlertaCritica extends Notification
{
    public function __construct(public readonly Alerta $alerta) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $correo = (new MailMessage)
            ->error()
            ->subject(__('alertas.correo.asunto', ['tipo' => __('alertas.tipos.'.$this->alerta->tipo), 'app' => config('app.name')]))
            ->greeting(__('alertas.correo.saludo', ['nombre' => $notifiable->name]))
            ->line($this->alerta->mensaje())
            ->line(__('alertas.correo.momento', ['momento' => $this->alerta->abierta_at->format('d/m/Y H:i')]));

        if ($enlace = $this->alerta->enlace()) {
            $correo->action(__('alertas.correo.boton'), $enlace);
        }

        return $correo
            ->line(__('alertas.correo.una_vez'))
            ->salutation(__('acceso.correo_recuperar.despedida', ['app' => config('app.name')]));
    }
}
