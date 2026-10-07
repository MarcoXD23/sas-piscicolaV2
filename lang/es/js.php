<?php

return [
    'gramos' => ':valor g',
    'muchas_preguntas' => 'Llegaste al límite de preguntas por ahora. Intenta de nuevo más tarde.',
    'no_se_pudo_enviar' => 'No se pudo enviar la pregunta.',
    'pensando' => 'Pensando…',
    'sin_conexion' => 'Sin conexión. Intenta de nuevo.',
    'sin_conexion_guardar' => 'No hay conexión: no se guardó. Lo que escribiste sigue aquí; inténtalo cuando vuelva la señal.',

    // Pesaje de la cosecha (resources/js/pesaje.js). Lo que se guarda lo vuelve a calcular el servidor.
    'pesaje_grupo_canasta_uno' => ':cantidad canasta × :kg kg',
    'pesaje_grupo_canasta_varios' => ':cantidad canastas × :kg kg',
    'pesaje_grupo_canastilla_uno' => ':cantidad canastilla × :kg kg',
    'pesaje_grupo_canastilla_varios' => ':cantidad canastillas × :kg kg',
    'pesaje_linea' => ':grupos = :descuento kg de descuento; :pesado kg − :descuento kg = :limpio kg limpios',
    'pesaje_incompleta' => 'La pesada :numero está incompleta: escribe la cantidad (entero de 1 o más) y el peso pesado.',
    'pesaje_bajo_descuento' => 'La pesada :numero pesa igual o menos que su descuento: revisa la cantidad o el peso.',
    'pesaje_vacias' => 'Escribe el peso de una canasta y de una canastilla vacías (mayor que 0).',
    'meta_llevas' => 'Llevas :limpio kg limpios de :meta kg.',
    'meta_faltan' => 'Faltan :kg kg.',
    'meta_promedio_una' => 'Según tu promedio (:promedio kg limpios por pesada), falta 1 pesada más.',
    'meta_promedio_varias' => 'Según tu promedio (:promedio kg limpios por pesada), faltan unas :pesadas pesadas más.',
    'meta_llegaste' => 'Ya llegaste a :meta kg.',
    'meta_sobran' => 'Ya llegaste a :meta kg: sobran :kg kg.',
];
