<?php

namespace App\Support\Reportes;

/**
 * Tabla de un reporte para exportar: una hoja de Excel o una sección del PDF.
 * Cada columna es [encabezado, decimales]: decimales null = texto; un número = columna numérica.
 */
final class Tabla
{
    /**
     * @param  list<array{0: string, 1: ?int}>  $columnas
     * @param  list<list<string|int|float|null>>  $filas
     */
    public function __construct(
        public readonly string $nombre,
        public readonly array $columnas,
        public readonly array $filas,
    ) {}
}
