<?php

/*
 * Registros hechos sin conexión en el teléfono y enviados al volver la señal (middleware RegistroSinConexion).
 */
return [

    // Antigüedad máxima (en horas) de un registro sin conexión al llegar. Vacío = 48.
    'horas' => max(1, (int) (env('SIN_CONEXION_HORAS') ?: 48)),

    // Margen (en minutos) para relojes de teléfono un poco adelantados.
    'margen_reloj_minutos' => 2,

    // Un registro no puede decir que ocurrió antes de la última hora que el teléfono recibió del servidor (menos este
    // margen, en minutos). Cierra el truco de atrasar el reloj y volverlo a poner bien antes de enviar.
    'margen_hora_servidor_minutos' => 2,

    // ¿Se rechaza el registro que llega sin esa hora (X-Ultima-Hora-Servidor)? Vacío = sí.
    'exigir_hora_servidor' => in_array(env('SIN_CONEXION_EXIGIR_HORA_SERVIDOR'), [null, ''], true)
        || filter_var(env('SIN_CONEXION_EXIGIR_HORA_SERVIDOR'), FILTER_VALIDATE_BOOL),

    // Modo de campo (Registro rápido): lagos que viajan en el paquete de trabajo, por nombre. Vacío = 60.
    'max_lagos' => max(1, (int) (env('SIN_CONEXION_MAX_LAGOS') ?: 60)),

    // Minutos tras los que el teléfono vuelve a descargar el paquete (con conexión, en segundo plano).
    'minutos_paquete' => 15,

    // Horas tras las que la pantalla avisa que los datos del paquete son viejos.
    'horas_paquete_viejo' => 12,

];
