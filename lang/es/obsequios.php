<?php

/*
| Obsequios de la jornada (F6c): docs/diseno/dia-de-proceso.md, tema 9.
*/

return [
    'titulo' => 'Obsequios',
    'subtitulo' => 'Pescado regalado en la jornada. Quien lo registra elige quién lo autorizó; nadie se autoriza a sí mismo.',
    'nuevo' => 'Registrar obsequio',
    'sin_obsequios' => 'No hay obsequios en esta jornada.',
    'especie' => 'Especie',
    'presentacion' => 'Presentación (opcional)',
    'sin_presentacion' => 'Sin presentación',
    'kg' => 'Kg',
    'destinatario' => 'A quién se le dio',
    'nota' => 'Nota (opcional)',
    'autorizo' => 'Quién lo autorizó',
    'sin_autorizadores' => 'No hay nadie más que pueda autorizar obsequios. Lo autoriza el propietario (o el administrador, si Ajustes lo permite), y nunca quien lo registra.',
    'linea' => ':kg kg de :especie a :destinatario · autorizó :autorizo · registró :registro',
    'registrado' => 'Obsequio de :kg kg a :destinatario registrado.',

    // Aviso en el Inicio del propietario (últimos 7 días)
    'aviso_inicio' => 'Obsequios de los últimos 7 días',
    'aviso_linea' => ':fecha · :kg kg de :especie a :destinatario (autorizó :autorizo)',

    'errores' => [
        'escribe_destinatario' => 'Escribe a quién se le dio.',
        'elige_autoriza' => 'Elige quién autorizó el obsequio.',
        'especie_no_esta' => 'Esa especie no está en los lagos de esta jornada.',
        'presentacion_otra_especie' => 'Esa presentación es de otra especie.',
        'no_a_si_mismo' => 'Nadie puede autorizarse a sí mismo un obsequio.',
        'no_puede_autorizar' => ':nombre no puede autorizar obsequios en esta finca.',
    ],
];
