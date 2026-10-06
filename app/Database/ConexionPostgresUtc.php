<?php

namespace App\Database;

use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\PostgresConnection;

/**
 * Conexión PostgreSQL que guarda y filtra siempre las fechas con hora en UTC.
 *
 * Laravel envía las fechas a la base con su hora "de pared", sin cambiar de zona. Como la aplicación
 * trabaja con horas en la zona de cada finca (App\Support\Region), aquí se pasan a UTC antes de enviarlas.
 * Las columnas de solo fecha deben recibir texto 'Y-m-d' (no un Carbon) para no cambiar de día.
 */
class ConexionPostgresUtc extends PostgresConnection
{
    public function prepareBindings(array $bindings): array
    {
        foreach ($bindings as $clave => $valor) {
            if ($valor instanceof DateTimeInterface) {
                $bindings[$clave] = \DateTimeImmutable::createFromInterface($valor)->setTimezone(new DateTimeZone('UTC'));
            }
        }

        return parent::prepareBindings($bindings);
    }
}
