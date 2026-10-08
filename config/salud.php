<?php

/*
 * Estado del sistema (/salud, piscicola:estado y piscicola:vigilar).
 */
return [

    // A quién se avisa por correo cuando falla una comprobación. Vacío = a los administradores de plataforma registrados.
    'correo_aviso' => env('SALUD_CORREO_AVISO') ?: null,

    // Conexión de base de datos que se comprueba (la de la app)
    'conexion' => env('DB_CONNECTION', 'pgsql'),

    // Disco y carpeta donde se prueban la escritura y la lectura, y donde quedan el latido del programador y el último estado
    'disco' => 'local',
    'carpeta' => 'salud',

    // El programador debe haber corrido en los últimos N minutos
    'minutos_programador' => 60,

];
