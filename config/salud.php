<?php

/*
 * Estado del sistema (/salud, piscicola:estado y piscicola:vigilar).
 * Cada umbral tiene una variable de entorno opcional; vacía = el valor por defecto.
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
    'minutos_programador' => (int) (env('SALUD_MINUTOS_PROGRAMADOR') ?: 10),

    // La copia de seguridad más reciente (en RESPALDOS_CARPETA) no puede tener más de N horas
    'horas_copia' => (int) (env('SALUD_HORAS_COPIA') ?: 36),

    // Sin RESPALDOS_CARPETA: ¿cuenta como falla? Vacío = sí en producción, no en las demás (desarrollo, pruebas)
    'exigir_copia' => in_array(env('SALUD_EXIGIR_COPIA'), [null, ''], true)
        ? env('APP_ENV') === 'production'
        : filter_var(env('SALUD_EXIGIR_COPIA'), FILTER_VALIDATE_BOOL),

    // Espacio libre mínimo en el disco de storage: ambos deben cumplirse (porcentaje del total y GB)
    'disco_porcentaje_minimo' => (float) (env('SALUD_DISCO_PORCENTAJE') ?: 10),
    'disco_gb_minimo' => (float) (env('SALUD_DISCO_GB') ?: 1),

    // Ningún trabajo de la cola puede llevar más de N minutos esperando, y no puede haber trabajos fallidos
    'minutos_cola' => (int) (env('SALUD_MINUTOS_COLA') ?: 15),

    // /salud guarda su resultado N segundos (un monitor externo o muchas consultas no repiten las comprobaciones)
    'segundos_cache' => (int) (env('SALUD_CACHE_SEGUNDOS') ?: 30),

];
