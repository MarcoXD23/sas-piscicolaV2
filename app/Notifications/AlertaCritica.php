<?php

namespace App\Notifications;

use App\Models\Alerta;
use App\Models\User;
use App\Support\Hora;
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
            // En el formato de hora de quien lo recibe (12 o 24 horas)
            ->line(__('alertas.correo.momento', ['momento' => Hora::formatear($this->alerta->abierta_at, 'd/m/Y H:i', $notifiable instanceof User ? Hora::de($notifiable) : null)]));

        if ($enlace = $this->alerta->enlace()) {
            $correo->action(__('alertas.correo.boton'), $enlace);
        }

        return $correo
            ->line(__('alertas.correo.una_vez'))
            ->salutation(__('acceso.correo_recuperar.despedida', ['app' => config('app.name')]));
    }
}
