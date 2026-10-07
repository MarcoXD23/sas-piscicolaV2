<?php

/*
 * Registros hechos sin conexión en el teléfono y enviados al volver la señal (middleware RegistroSinConexion).
 */
return [

    // Antigüedad máxima (en horas) de un registro sin conexión al llegar. Vacío = 48.
    'horas' => max(1, (int) (env('SIN_CONEXION_HORAS') ?: 48)),

    // Margen (en minutos) para relojes de teléfono un poco adelantados.
    'margen_reloj_minutos' => 2,

];
