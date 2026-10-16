<?php

/*
| Ajustes de operación por finca (App\Support\Ajustes\CatalogoAjustes): sección «Operación de la finca» en Ajustes.
| Cada ajuste: etiqueta, ayuda y el texto de cada opción (o «valor», el texto del número, si es un entero).
*/

return [
    'titulo' => 'Operación de la finca',
    'subtitulo' => 'Cómo trabaja tu finca. Cada cambio queda en la bitácora.',
    'guardar' => 'Guardar operación',
    'guardados' => 'Ajustes de operación guardados.',
    'sin_cambios' => 'No cambiaste ningún ajuste.',

    'secciones' => [
        'agua' => 'Calidad del agua',
        'muestreo' => 'Muestreos',
    ],

    'agua' => [
        'quien_mide' => [
            'etiqueta' => 'Quién mide el agua',
            'ayuda' => 'Quién registra el oxígeno, la temperatura y los demás valores del agua: el celador desde Seguridad & Noche y el administrador desde el detalle del lago, los dos también sin conexión desde Registro rápido. Al otro rol no le aparece el botón de medir.',
            'opciones' => [
                'ambos' => 'El celador y el administrador',
                'celador' => 'Solo el celador',
                'administrador' => 'Solo el administrador',
            ],
        ],
    ],

    'muestreo' => [
        'plazo_dias' => [
            'etiqueta' => 'Plazo del muestreo (días)',
            'ayuda' => 'Si un lago con peces pasa más de estos días sin muestreo, Alertas avisa «muestreo vencido». Si el lote no tiene ningún muestreo, se cuenta desde la siembra.',
            'valor' => ':valor días',
        ],
    ],
];
