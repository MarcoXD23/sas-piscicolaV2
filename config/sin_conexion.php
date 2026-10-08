<?php

/*
 * Registros hechos sin conexión en el teléfono y enviados al volver la señal (middleware RegistroSinConexion).
 */
return [

    // Antigüedad máxima (en horas) de un registro sin conexión al llegar. Vacío = 48.
    'horas' => max(1, (int) (env('SIN_CONEXION_HORAS') ?: 48)),

    // Margen (en minutos) para relojes de teléfono un poco adelantados.
    'margen_reloj_minutos' => 2,

    // Modo de campo (Registro rápido): lagos que viajan en el paquete de trabajo, por nombre. Vacío = 60.
    'max_lagos' => max(1, (int) (env('SIN_CONEXION_MAX_LAGOS') ?: 60)),

    // Minutos tras los que el teléfono vuelve a descargar el paquete (con conexión, en segundo plano).
    'minutos_paquete' => 15,

    // Horas tras las que la pantalla avisa que los datos del paquete son viejos.
    'horas_paquete_viejo' => 12,

];
