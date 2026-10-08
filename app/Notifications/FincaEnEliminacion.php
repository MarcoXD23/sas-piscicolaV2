<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso al propietario: la finca se eliminará en la fecha indicada y hasta entonces puede cancelarse.
 * Textos en lang/es/datos.php («correo_eliminacion»).
 */
class FincaEnEliminacion extends Notification
{
    public function __construct(public readonly Tenant $finca) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $fecha = $this->finca->eliminacion_programada_at->setTimezone($this->finca->zona_horaria)->format('d/m/Y H:i');

        return (new MailMessage)
            ->subject(__('datos.correo_eliminacion.asunto', ['finca' => $this->finca->nombre]))
            ->greeting(__('datos.correo_eliminacion.saludo', ['nombre' => $notifiable->name]))
            ->line(__('datos.correo_eliminacion.motivo', ['finca' => $this->finca->nombre, 'fecha' => $fecha]))
            ->action(__('datos.correo_eliminacion.boton'), route('login'))
            ->line(__('datos.correo_eliminacion.copias', ['dias' => config('respaldos.dias')]))
            ->salutation(__('acceso.correo_recuperar.despedida', ['app' => config('app.name')]));
    }
}
