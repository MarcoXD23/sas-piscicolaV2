<?php

/*
| Conversión alimenticia (FCA = kg de alimento / kg de biomasa ganada).
*/

return [
    'titulo' => 'Conversión alimenticia',
    'subtitulo' => 'Kilos de alimento por cada kilo de pescado ganado. Menos es mejor.',
    'por_lago' => 'Por lago (ciclo actual)',
    'por_especie' => 'Por especie (todas las siembras)',
    'lago_siembra' => 'Lago / siembra',
    'especie' => 'Especie',
    'siembras' => 'Siembras',
    'alimento' => 'Alimento',
    'ganancia' => 'Biomasa ganada',
    'fca' => 'Conversión (FCA)',
    'semana' => 'Semana',
    'semana_del' => 'Semana del :fecha',
    'periodo' => 'Del :desde al :hasta (último peso)',
    'estimado_policultivo' => 'alimento estimado en policultivo',
    'tendencia' => 'Tendencia de las últimas :semanas semanas · :lago',
    'elegir_lago' => 'Elegir lago',
    'sin_siembras' => 'Todavía no hay siembras.',
    'motivos' => [
        'sin_peso' => 'Falta un muestreo para calcularla',
        'sin_ganancia' => 'Sin ganancia de biomasa',
        'sin_datos' => 'Sin datos',
    ],
    'nota' => 'La biomasa se estima con el peso de los muestreos, cosechas y traslados (interpolado entre fechas) y no incluye los peces muertos. En lagos con varias especies el alimento se reparte según la biomasa de cada una.',
];
