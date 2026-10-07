<?php

/*
| Bitácora de la finca: registro de solo inserción de las acciones de campo.
*/

return [
    'titulo' => 'Bitácora',
    'subtitulo' => 'Quién hizo qué, en qué lago y cuándo. Los registros no se pueden editar ni borrar.',
    'persona' => 'Persona',
    'rol' => 'Rol',
    'lago' => 'Lago',
    'momento' => 'Fecha y hora',
    'accion' => 'Acción',
    'detalle' => 'Detalle',
    'todas' => 'Todas',
    'todos' => 'Todos',
    'filtrar' => 'Filtrar',
    'limpiar' => 'Quitar filtros',
    'vacio' => 'No hay registros con estos filtros.',
    'nota' => 'La hora se muestra en la zona horaria que tenía la finca cuando ocurrió cada acción.',
    'solo_insercion' => 'La bitácora es de solo inserción: sus registros no se editan ni se borran.',
    'roles' => [
        'propietario' => 'Propietario',
        'administrador' => 'Administrador',
        'trabajador' => 'Trabajador',
        'celador' => 'Celador',
    ],
    'acciones' => [
        'racion' => 'Ración',
        'mortalidad' => 'Mortalidad',
        'traslado' => 'Traslado',
        'muestreo' => 'Muestreo',
        'ronda' => 'Ronda',
        'novedad' => 'Novedad',
        'cosecha' => 'Cosecha',
        'venta' => 'Venta',
    ],
    'detalles' => [
        'racion' => ':kg kg de :concentrado (fecha :fecha)',
        'mortalidad' => ':peces peces de :especie (fecha :fecha)',
        'traslado' => ':peces peces de :especie hacia :destino (fecha :fecha)',
        'muestreo' => ':especie: :peces peces pesados, promedio :gramos g (fecha :fecha)',
        'ronda' => ':tipo',
        'novedad' => ':tipo: :descripcion',
        'cosecha' => ':peces peces de :especie, :kg kg (fecha :fecha)',
        'venta' => ':kg kg a :cliente por :total, :forma_pago (fecha :fecha)',
    ],
];
