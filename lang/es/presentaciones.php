<?php

/*
| Catálogo de presentaciones de la finca (F6b): docs/diseno/dia-de-proceso.md, tema 6. Ningún nombre de la finca piloto:
| el ejemplo va solo en la ayuda de pantalla.
*/

return [
    'titulo' => 'Presentaciones',
    'subtitulo' => 'Tamaños de cada especie, procesos de la finca y las presentaciones que se empacan y se pesan como limpio.',
    'solo_lectura' => 'Estás viendo el catálogo de presentaciones. Lo edita el administrador.',
    'ejemplo' => 'Una presentación es especie + tamaño + proceso. Por ejemplo, con los tamaños grande, mediana y pequeña, y los procesos lavado con escamas, descamado y embolsado, se marca «grande descamado» en la tabla de la especie. Cada finca pone sus propios nombres.',
    'nombre' => ':especie :tamano :proceso',

    'procesos' => 'Procesos',
    'procesos_ayuda' => 'Los procesos de la finca, para todas las especies.',
    'sin_procesos' => 'Todavía no hay procesos.',
    'nuevo_proceso' => 'Nuevo proceso',
    'tamanos_ayuda' => 'Tamaños de esta especie, con el nombre que usa la finca. El rango de peso es solo informativo.',
    'sin_tamanos' => 'Todavía no hay tamaños de esta especie.',
    'nuevo_tamano' => 'Nuevo tamaño',
    'nombre_campo' => 'Nombre',
    'peso_min' => 'Desde (g)',
    'peso_max' => 'Hasta (g)',
    'rango' => ':min a :max g',
    'activo' => 'Activo',
    'activa' => 'Activa',
    'inactivo' => 'inactivo',
    'agregar' => 'Agregar',
    'guardar' => 'Guardar',
    'eliminar_nombre' => 'Eliminar :nombre',
    'confirmar_eliminar' => '¿Eliminar «:nombre»? Si ya se usó en un pesaje no se puede: desactívalo.',

    'tabla' => 'Presentaciones de la especie',
    'tabla_ayuda' => 'Marca cada combinación de tamaño y proceso que la finca empaca. El color de cabuya es opcional. Al desmarcar una presentación se desactiva; lo que ya se pesó se conserva.',
    'tamano' => 'Tamaño',
    'cabuya' => 'Color de cabuya',
    'cabuya_de' => 'Color de cabuya de :tamano :proceso',
    'cabuya_texto' => 'Cabuya: :color',
    'guardar_tabla' => 'Guardar presentaciones',

    'proceso_creado' => 'Proceso «:nombre» agregado.',
    'tamano_creado' => 'Tamaño «:nombre» agregado.',
    'guardado' => '«:nombre» guardado.',
    'eliminado' => '«:nombre» eliminado.',
    'tabla_guardada' => 'Presentaciones de :especie guardadas: :cantidad activas.',

    'bitacora' => [
        'proceso_creado' => 'Proceso «:nombre» agregado',
        'proceso_editado' => 'Proceso «:nombre» actualizado',
        'tamano_creado' => 'Tamaño «:nombre» de :especie agregado',
        'tamano_editado' => 'Tamaño «:nombre» de :especie actualizado',
        'tabla' => 'Presentaciones de :especie: :cantidad activas',
        'eliminado' => '«:nombre» eliminado del catálogo',
    ],

    'errores' => [
        'escribe_nombre' => 'Escribe el nombre.',
        'nombre_repetido' => 'Ya existe uno con ese nombre.',
        'elige_especie' => 'Elige la especie.',
        'rango_invertido' => 'El peso «desde» no puede ser mayor que el peso «hasta».',
        'cabuya_larga' => 'El color de cabuya puede tener hasta :maximo caracteres.',
        'en_uso' => '«:nombre» ya se usó en pesajes de limpio: no se puede eliminar. Desactívalo.',
    ],
];
