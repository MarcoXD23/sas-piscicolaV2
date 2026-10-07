<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Hora real en que ocurrió lo que se registra en la petición en curso.
 * Normalmente es "ahora". Si el registro se hizo sin conexión y llega después, es la hora que guardó el teléfono
 * (ya validada por el middleware RegistroSinConexion): el turno, la bitácora y las horas de la ronda,
 * el aviso o la medición usan esa hora y no la de llegada.
 */
class MomentoRegistro
{
    private ?CarbonImmutable $momento = null;

    public function fijar(CarbonImmutable $momento): void
    {
        $this->momento = $momento->utc();
    }

    public function olvidar(): void
    {
        $this->momento = null;
    }

    public function ahora(): CarbonImmutable
    {
        return $this->momento ?? CarbonImmutable::now();
    }

    public function sinConexion(): bool
    {
        return $this->momento !== null;
    }

    public static function actual(): CarbonImmutable
    {
        return app(self::class)->ahora();
    }
}
