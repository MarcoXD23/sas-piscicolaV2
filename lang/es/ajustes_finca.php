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
        'lotes' => 'Lotes',
        'proceso' => 'Día de proceso',
        'seguridad' => 'Seguridad',
    ],

    'proceso' => [
        'jornadas' => [
            'etiqueta' => 'Jornadas de proceso',
            'ayuda' => 'Enciéndela si la finca procesa por jornadas: un día en que se pescan uno o varios lagos, se pesa el entero por lago y especie y, al cerrar, el sistema crea las cosechas. Apagada, la finca registra sus cosechas una por una, como siempre.',
            'opciones' => [
                'apagada' => 'Apagada (cosecha una por una)',
                'encendida' => 'Encendida',
            ],
        ],
        'dias_ayuno' => [
            'etiqueta' => 'Días de ayuno antes de la pesca',
            'ayuda' => 'Desde cuántos días antes de la jornada no se alimenta el lago que se va a pescar. Ese lago sale «en ayuno» en Inicio y en Registro rápido; si alguien le registra ración, la app avisa pero no la bloquea. Con 0, el ayuno es solo el día de la pesca. Solo cuenta con las jornadas encendidas.',
            'valor' => ':valor días',
        ],
        'reproduccion' => [
            'etiqueta' => '«Reproducción» en la jornada',
            'ayuda' => 'Si la finca separa los peces demasiado pequeños antes del proceso, enciéndela: en la jornada se registran sus kg por lago y especie. Esos peces vuelven al lago, no cuentan como cosechados y se restan del entero antes de calcular el rendimiento.',
            'opciones' => [
                'apagada' => 'Apagada',
                'encendida' => 'Encendida',
            ],
        ],
        'umbral_rendimiento' => [
            'etiqueta' => 'Aviso de rendimiento bajo (puntos)',
            'ayuda' => 'Si el rendimiento de una especie (limpio ÷ entero) sale estos puntos o más por debajo de su referencia, la jornada avisa y se puede anotar el motivo, por ejemplo «lote en reproducción».',
            'valor' => ':valor puntos',
        ],
        'jornadas_promedio' => [
            'etiqueta' => 'Jornadas para el promedio real de rendimiento',
            'ayuda' => 'La referencia de rendimiento de cada especie es la que la finca escribe en el catálogo de especies hasta que haya estas jornadas cerradas con limpio. Desde ahí se usa el promedio real de las últimas, ponderado por el entero.',
            'valor' => ':valor jornadas',
        ],
        'sin_alimentacion' => [
            'etiqueta' => 'No se alimenta el día de proceso',
            'ayuda' => 'El día de la jornada no se alimenta en la finca porque el personal ayuda en el proceso. Inicio y Registro rápido lo recuerdan. Solo cuenta con las jornadas encendidas.',
            'opciones' => [
                'apagada' => 'Apagada (se alimenta como cualquier día)',
                'encendida' => 'Encendida',
            ],
        ],
    ],

    'lotes' => [
        'numeracion' => [
            'etiqueta' => 'Número de lote',
            'ayuda' => 'Manual: al sembrar, el administrador escribe el número de lote que usa la finca (o lo deja vacío). Automático: la app lo pone sola, con el año de la siembra y un consecutivo (por ejemplo 2026-001). El lote que nace de un traslado conserva el número del lote de origen.',
            'opciones' => [
                'manual' => 'Manual (lo escribe la finca)',
                'automatica' => 'Automático (lo genera la app)',
            ],
        ],
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

    'seguridad' => [
        'dos_pasos_obligatoria' => [
            'etiqueta' => 'Verificación en dos pasos obligatoria para propietario y administrador',
            'ayuda' => 'Si la enciendes, el propietario y el administrador que no la tengan deben activarla al entrar, antes de seguir. Para el trabajador y el celador sigue siendo opcional.',
            'opciones' => [
                'apagada' => 'Apagada (opcional para todos)',
                'encendida' => 'Encendida',
            ],
        ],
    ],
];
