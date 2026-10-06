<?php

namespace App\Support;

use App\Models\HoraExtra;
use App\Models\Permiso;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula la nómina semanal de una persona a partir de sus turnos y horas extra aprobadas.
 * No aplica recargos de ley automáticamente: usa las tarifas que el propietario configura.
 */
class CalculadoraNomina
{
    /**
     * @return array{
     *     horas_turnos: float, horas_extra: float, valor_turnos: int, valor_horas_extra: int, bruto: int,
     *     turnos_pagados: Collection<int, Turno>, turnos_en_permiso: Collection<int, Turno>, turnos_sin_terminar: int
     * }
     */
    public static function calcular(User $persona, CarbonImmutable $lunes): array
    {
        $siguienteLunes = $lunes->addWeek();

        $turnos = Turno::query()
            ->where('user_id', $persona->id)
            ->where('inicia_at', '>=', $lunes)
            ->where('inicia_at', '<', $siguienteLunes)
            ->orderBy('inicia_at')
            ->get();

        $permisos = Permiso::query()->aprobadas()->where('user_id', $persona->id)
            ->where('fecha_inicio', '<', $siguienteLunes->toDateString())
            ->where('fecha_fin', '>=', $lunes->toDateString())
            ->get();

        // Un turno que se cruza con un permiso aprobado no se trabajó
        $enPermiso = fn (Turno $turno) => $permisos->contains(fn (Permiso $permiso) => $turno->inicia_at->lt($permiso->finLocal())
            && $turno->termina_at->gt($permiso->inicioLocal()));

        $terminados = $turnos->filter(fn (Turno $turno) => $turno->termina_at->lte(now()));
        $turnosEnPermiso = $terminados->filter($enPermiso)->values();
        $pagados = $terminados->reject($enPermiso)->values();

        $horasTurnos = round($pagados->sum(fn (Turno $turno) => $turno->inicia_at->diffInMinutes($turno->termina_at)) / 60, 2);
        $horasExtra = round((float) HoraExtra::query()->aprobadas()->where('user_id', $persona->id)
            ->whereBetween('fecha', [$lunes->toDateString(), $lunes->addDays(6)->toDateString()])->sum('horas'), 2);

        $valorTurnos = (int) round($horasTurnos * (int) $persona->valor_hora);
        $valorExtra = (int) round($horasExtra * (int) $persona->valor_hora_extra);

        return [
            'horas_turnos' => $horasTurnos,
            'horas_extra' => $horasExtra,
            'valor_turnos' => $valorTurnos,
            'valor_horas_extra' => $valorExtra,
            'bruto' => $valorTurnos + $valorExtra,
            'turnos_pagados' => $pagados,
            'turnos_en_permiso' => $turnosEnPermiso,
            'turnos_sin_terminar' => $turnos->count() - $terminados->count(),
        ];
    }

    /** Lunes de la semana que contiene la fecha. */
    public static function lunes(CarbonImmutable|string|null $fecha = null): CarbonImmutable
    {
        return CarbonImmutable::parse($fecha ?? Region::hoy()->toDateString(), Region::zona())->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }
}
