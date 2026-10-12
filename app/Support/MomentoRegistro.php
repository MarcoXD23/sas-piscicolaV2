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

    /** Minutos que se corrigió el reloj del teléfono, si pasan del margen (se anotan en la bitácora). */
    private ?int $minutosCorregidos = null;

    public function fijar(CarbonImmutable $momento, ?int $minutosCorregidos = null): void
    {
        $this->momento = $momento->utc();
        $this->minutosCorregidos = $minutosCorregidos;
    }

    public function olvidar(): void
    {
        $this->momento = null;
        $this->minutosCorregidos = null;
    }

    public function minutosCorregidos(): ?int
    {
        return $this->minutosCorregidos;
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

    /** ¿La petición en curso es un registro que llegó de la cola sin conexión? */
    public static function sinConexionActual(): bool
    {
        return app(self::class)->sinConexion();
    }
}
