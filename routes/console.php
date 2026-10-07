<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alertas que dependen del paso del tiempo (requiere el cron de Laravel en el servidor: schedule:run cada minuto)
Schedule::command('piscicola:evaluar-alertas')->hourly()->withoutOverlapping();
