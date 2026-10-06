<?php

/*
| Textos de los comandos de consola.
*/

return [
    'crear_propietario' => [
        'descripcion' => 'Crea una finca y su usuario propietario (pide la contraseña)',
        'finca' => 'Nombre de la finca',
        'codigo' => 'Código de la finca (lo usará el personal para registrarse)',
        'pais' => 'País de la finca (código de 2 letras, por ejemplo CO, MX o PE)',
        'zona' => 'Zona horaria de la finca',
        'nombre' => 'Nombre del propietario',
        'correo' => 'Correo del propietario',
        'clave' => 'Contraseña (mínimo 8 caracteres)',
        'confirmacion' => 'Confirma la contraseña',
        'creado' => 'Propietario creado para la finca «:finca» (código: :codigo, país: :pais, zona: :zona).',
        'pais_invalido' => 'El país no es un código válido de 2 letras.',
        'zona_invalida' => 'La zona horaria no es válida.',
    ],
];
