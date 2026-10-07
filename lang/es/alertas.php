<?php

/*
| Alertas de la finca: stock de alimento, mortalidad anormal y calidad del agua.
*/

return [
    'titulo' => 'Alertas',
    'subtitulo' => 'Historial de alertas de la finca. Se cierran solas cuando el problema se resuelve.',
    'filtrar' => 'Filtrar alertas',
    'todas' => 'Todas',
    'alerta' => 'Alerta',
    'estado' => 'Estado',
    'abierta' => 'Abierta',
    'cerrada' => 'Cerrada',
    'abierta_estado' => 'Abierta',
    'cerrada_estado' => 'Cerrada',
    'vacio' => 'Todavía no hay alertas.',
    'desde' => 'Desde :momento',
    'notificada' => 'Avisada por correo el :momento',
    'tipos' => [
        'stock' => 'Bodega',
        'mortalidad' => 'Mortalidad',
        'agua' => 'Calidad del agua',
    ],
    'severidades' => [
        'advertencia' => 'Advertencia',
        'critica' => 'Crítica',
    ],
    'mensajes' => [
        'stock_agotado' => ':concentrado está agotado.',
        'stock_bajo' => ':concentrado alcanza para unos :dias días (el mínimo de la finca es :minimo).',
        'mortalidad' => 'Lago :lago: :muertos peces muertos en :dias días (:porcentaje %; el umbral de la finca es :umbral %).',
        'agua' => 'Lago :lago: :detalle',
    ],
    'inicio' => [
        'titulo' => 'Alertas abiertas (:cantidad)',
        'historial' => 'Ver historial',
    ],
    'ajustes' => [
        'titulo' => 'Umbrales de alerta',
        'stock_dias' => 'Stock bajo: días mínimos de alimento',
        'stock_dias_ayuda' => 'Se avisa cuando un concentrado alcanza para menos de estos días, según el consumo de la última semana.',
        'mortalidad_porcentaje' => 'Mortalidad anormal (%)',
        'mortalidad_dias' => 'En los últimos (días)',
        'mortalidad_ayuda' => 'Se avisa cuando en un lago muere este porcentaje de los peces o más en el período. Si llega al doble, la alerta es crítica.',
    ],
    'correo' => [
        'asunto' => 'Alerta crítica: :tipo – :app',
        'saludo' => 'Hola, :nombre:',
        'momento' => 'Detectada el :momento.',
        'boton' => 'Ver en la aplicación',
        'una_vez' => 'Este aviso se envía una sola vez por alerta. La alerta se cierra sola cuando el problema se resuelva.',
    ],
];
