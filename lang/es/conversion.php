<?php

/*
| Conversión alimenticia (FCA = kg de alimento / kg de biomasa ganada).
*/

return [
    'titulo' => 'Conversión alimenticia',
    'subtitulo' => 'Kilos de alimento por cada kilo de pescado ganado. Menos es mejor.',
    'por_lago' => 'Por lago (ciclo actual)',
    'por_especie' => 'Por especie (todas las siembras)',
    'por_proveedor' => 'Por proveedor de alevinos (todas las siembras)',
    'por_proveedor_ayuda' => 'Crecimiento: gramos ganados por día de vida. Supervivencia: (vivos + cosechados + trasladados a otros lotes) ÷ (sembrados + recibidos); lo que quedó al cerrar un lote sin cosechar cuenta como pérdida. Un lote creado por traslado cuenta para el proveedor de su origen.',
    'proveedor' => 'Proveedor',
    'proveedor_sin_ligar' => 'Escrito a mano, sin ligar',
    'proveedor_ninguno' => 'Sin proveedor',
    'alevinos' => 'Alevinos sembrados',
    'crecimiento' => 'Crecimiento',
    'supervivencia' => 'Supervivencia',
    'gramos_dia' => ':valor g/día',
    'porcentaje' => ':valor %',
    'lotes' => '{1} 1 lote|[2,*] :lotes lotes',
    'mezcla' => 'incluye peces recibidos de lotes de otro proveedor',
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
