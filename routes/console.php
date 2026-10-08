<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertas que dependen del paso del tiempo (requiere el cron de Laravel en el servidor: schedule:run cada minuto)
Schedule::command('piscicola:evaluar-alertas')->hourly()->withoutOverlapping();

// Copia de seguridad diaria, solo si hay carpeta configurada (RESPALDOS_CARPETA, fuera del proyecto)
Schedule::command('piscicola:respaldar')->dailyAt(config('respaldos.hora'))->withoutOverlapping()
    ->when(fn () => filled(config('respaldos.carpeta')));

// Datos de las fincas: paquetes de exportación vencidos (cada hora) y fincas con el plazo de eliminación vencido (a diario)
Schedule::command('piscicola:limpiar-exportaciones')->hourly()->withoutOverlapping();
Schedule::command('piscicola:eliminar-fincas')->dailyAt('03:30')->withoutOverlapping();

// Estado del sistema: latido del programador (lo mira /salud) y vigilancia con aviso por correo
Schedule::call(fn () => \App\Support\Salud\Comprobaciones::latido())->everyMinute()->name('latido-programador');
Schedule::command('piscicola:vigilar')->everyFiveMinutes()->withoutOverlapping();
