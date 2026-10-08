<?php

return [
    'gramos' => ':valor g',
    'muchas_preguntas' => 'Llegaste al límite de preguntas por ahora. Intenta de nuevo más tarde.',
    'no_se_pudo_enviar' => 'No se pudo enviar la pregunta.',
    'pensando' => 'Pensando…',
    'sin_conexion' => 'Sin conexión. Intenta de nuevo.',
    // Registros sin conexión (resources/js/sin-conexion.js)
    'cola_guardado' => 'Sin señal: quedó guardado en este teléfono. Se enviará solo cuando vuelva la conexión.',
    'cola_enviado_uno' => 'Se envió 1 registro que estaba guardado en el teléfono.',
    'cola_enviados_varios' => 'Se enviaron :cantidad registros que estaban guardados en el teléfono.',
    'cola_hay_rechazados' => 'Hay registros que la finca no aceptó. Revísalos en «Sin enviar».',
    'cola_sesion' => 'Tu sesión venció: vuelve a entrar con tu usuario y lo guardado se enviará solo.',
    'cola_sin_senal' => 'Todavía no hay señal. Se enviarán solos cuando vuelva.',
    'cola_registrado' => 'Registrado el :momento',
    'cola_motivo' => 'Motivo: :motivo',
    'cola_rechazo_generico' => 'La finca no aceptó este registro.',
    'cola_por_vencer' => 'Envíalo pronto: después de :horas horas ya no se acepta.',
    'cola_descartar' => 'Descartar',
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
