<?php

// Lista de primer uso de una finca nueva (inicio del propietario y del administrador)
return [
    'titulo' => 'Primeros pasos en tu finca',
    'subtitulo' => 'Cada paso se marca solo cuando lo haces. Llevas :hechos de :total.',
    'ocultar' => 'Ocultar esta lista',
    'ocultada' => 'Lista de primeros pasos ocultada. No volverá a aparecer.',
    'hecho' => 'Hecho',
    'ir' => 'Ir',
    'lo_hace_propietario' => 'Lo hace el propietario.',
    'lo_hace_administrador' => 'Lo hace el administrador.',
    'codigo_finca' => 'Código de la finca: :codigo',
    'pasos' => [
        'ajustes' => [
            'titulo' => 'Revisa la zona horaria y el idioma',
            'ayuda' => 'En Ajustes: de ahí salen las horas, el «hoy» de la finca y el formato del dinero. Guarda aunque no cambies nada.',
        ],
        'especies' => [
            'titulo' => 'Revisa o agrega tus especies',
            'ayuda' => 'Ajusta los rangos de agua y la ficha de las especies que cultivas, desactiva las que no uses o agrega las tuyas.',
        ],
        'pesos' => [
            'titulo' => 'Define el peso de la canasta y la canastilla vacías',
            'ayuda' => 'Con ellos se calcula el descuento de cada pesada en la cosecha.',
        ],
        'lago' => [
            'titulo' => 'Crea tu primer lago',
            'ayuda' => 'Con su nombre o código y su tipo; después registras la siembra.',
        ],
        'invitar' => [
            'titulo' => 'Invita a tu personal',
            'ayuda' => 'Comparte el código de la finca: cada persona lo usa en «Solicita acceso» al registrarse.',
        ],
        'aprobar' => [
            'titulo' => 'Aprueba a tu primer trabajador',
            'ayuda' => 'En Personal, aprueba su solicitud y asígnale el rol de trabajador.',
        ],
    ],
];
