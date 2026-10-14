<?php

/*
 * Ajustes regionales que dependen del país de la finca (ver App\Support\Region y App\Support\Hora).
 */
return [

    // Países donde la hora se acostumbra en 12 horas («10:24 p. m.»). Los demás, y las fincas sin país, en 24 horas.
    // La finca (Ajustes) y cada persona (Mi cuenta) pueden elegir otro formato.
    'formato_hora_12' => ['CO', 'MX', 'US', 'VE', 'PE', 'PH', 'IN', 'CA', 'AU'],

];
