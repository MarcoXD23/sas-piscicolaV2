<?php

/*
| Textos de regionalización: nada fijo a un país.
*/

return [
    'registro_sanitario_opcional' => 'Registro sanitario del producto (opcional)',
    'registro_sanitario_detalle' => 'registro sanitario :numero',
    'aviso_trazabilidad' => 'Formato de apoyo para la trazabilidad. Confirma con la autoridad sanitaria o agropecuaria de tu país los datos que exige la norma vigente.',
    'aviso_libro_campo' => 'Formato de apoyo para la trazabilidad del cultivo. Verifica con la autoridad sanitaria o agropecuaria de tu país los datos y la firma que exige la norma vigente.',
    'dinero_sin_decimales' => 'Escribe el valor sin decimales.',

    // Formato de hora (App\Support\Hora): «10:24 p. m.» o «22:24»
    'am' => 'a. m.',
    'pm' => 'p. m.',
    // Nombre corto, para «El de la finca (12 horas)» y «El del país (12 horas)»
    'formatos_hora_corto' => [
        '12' => '12 horas',
        '24' => '24 horas',
    ],
    'formatos_hora' => [
        '12' => '12 horas (10:24 p. m.)',
        '24' => '24 horas (22:24)',
    ],
    'ajustes' => [
        'titulo' => 'Ajustes de la finca',
        'subtitulo' => 'País, zona horaria e idioma. Las fechas y horas se muestran en la zona de la finca.',
        'enlace' => 'Ajustes',
        'nombre' => 'Nombre de la finca',
        'pais' => 'País',
        'pais_ayuda' => 'Define la moneda y el formato de los números.',
        'zona_horaria' => 'Zona horaria',
        'zona_ayuda' => 'Las horas de turnos, mediciones y agenda se interpretan en esta zona.',
        'idioma' => 'Idioma',
        'guardar' => 'Guardar ajustes',
        'guardados' => 'Ajustes de la finca guardados.',
        'formato_hora' => 'Formato de hora',
        'formato_hora_del_pais' => 'El del país (:formato)',
        'formato_hora_ayuda' => 'Cómo se muestran las horas en la finca. Cada persona puede elegir otro en Mi cuenta.',
        'unidad_peso_muestreo' => 'Unidad del peso en el muestreo',
        'unidad_peso_muestreo_ayuda' => 'La que aparece primero al registrar un muestreo; cada persona puede elegir otra y el teléfono la recuerda. Lo guardado siempre queda en gramos.',
        'hora_actual' => 'Hora actual en la finca: :hora',
        'pais_invalido' => 'Elige un país válido.',
        'zona_invalida' => 'Elige una zona horaria válida.',
        'idioma_invalido' => 'Ese idioma aún no está disponible.',
    ],

    'idiomas' => [
        'es' => 'Español',
    ],
];
