<?php

/*
 * Copias de seguridad de PostgreSQL (php artisan piscicola:respaldar y piscicola:restaurar).
 * La carpeta debe quedar FUERA del proyecto: una copia nunca debe llegar a git ni a la carpeta pública.
 */
return [

    // Carpeta de las copias, por ejemplo /var/respaldos/sas-piscicola o C:\respaldos. Vacía = respaldos desactivados.
    'carpeta' => env('RESPALDOS_CARPETA') ?: null,

    // Días que se guarda cada copia; las más viejas se borran después de cada respaldo exitoso.
    'dias' => max(1, (int) (env('RESPALDOS_DIAS') ?: 14)),

    // Carpeta de pg_dump y pg_restore. Vacía = se buscan en el PATH del sistema.
    'pg_bin' => env('PG_BIN') ?: null,

    // Hora (del servidor) del respaldo diario programado.
    'hora' => env('RESPALDOS_HORA') ?: '02:00',

];
