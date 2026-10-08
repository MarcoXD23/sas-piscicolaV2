<?php

/*
 * Exportar y eliminar los datos de una finca (App\Support\Datos).
 */
return [

    // Días entre la solicitud de eliminación y el borrado definitivo (se puede cancelar en ese plazo). Vacío = 14.
    'dias_gracia' => max(1, (int) (env('FINCA_DIAS_GRACIA') ?: 14)),

    // Horas que se puede descargar un paquete de exportación antes de borrarse
    'horas_exportacion' => 24,

    // Minutos mínimos entre dos exportaciones de una finca
    'minutos_entre_exportaciones' => 60,

    // Lista de fincas ya eliminadas (en el disco local privado, FUERA de la base: sobrevive a restaurar una copia vieja)
    'archivo_eliminadas' => 'fincas-eliminadas.json',

];
