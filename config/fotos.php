<?php

/*
 * Fotos reales de especies (las sube una persona; la app nunca genera imágenes).
 * Cada foto se guarda dos veces en el disco privado «fotos»: el original sin recomprimir y una versión ligera WebP.
 */
return [

    // Peso máximo de una foto subida (MB). El servidor debe aceptarlo: upload_max_filesize y post_max_size (ver README).
    'max_mb' => 30,

    // Fotos por especie
    'max_por_especie' => 5,

    // Tope total por finca (MB), sumando originales y versiones ligeras. Vacío = 2048 (2 GB).
    'tope_finca_mb' => max(1, (int) (env('FOTOS_TOPE_FINCA_MB') ?: 2048)),

    // Versión ligera: lado mayor máximo (px) y peso máximo (bytes, menos de 200 KB)
    'ligera_lado_max' => 1600,
    'ligera_bytes_max' => 200 * 1024,

    // Tope de dimensiones: la mitad de memory_limit entre los bytes por píxel que usa GD al decodificar (unos 5),
    // nunca más que este máximo fijo, y ningún lado mayor que lado_max.
    'megapixeles_max' => 50,
    'lado_max' => 12000,
    'bytes_por_pixel' => 5,

];
