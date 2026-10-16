<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Área de cada pantalla, para el color de acento de la libreta (resources/css/app.css: --color-area-*): ícono del
 * menú, recuadro del ícono del título y raya superior de las fichas. Un solo lugar, por nombre de ruta.
 * - Una pantalla sin área (las NEUTRAS, o una nueva que nadie clasificó) usa el estilo neutro: no se rompe.
 *   IdentidadVisualTest dice cuál quedó sin clasificar.
 * - El color nunca es la única señal: el estado sigue con su texto o su ícono, y el menú marca la pantalla actual
 *   con negrita y raya.
 */
final class Areas
{
    /** área => patrones de nombre de ruta */
    public const AREAS = [
        'campo' => ['admin.lagos.*', 'admin.cosechas.*', 'admin.especies.*', 'especies.*', 'admin.tareas.*', 'admin.ajustes-pesaje.*', 'admin.alertas.*', 'campo', 'noche.*'],
        'bodega' => ['admin.bodega.*'],
        'ventas' => ['admin.ventas.*'],
        'personal' => ['admin.personal.*', 'admin.turnos.*', 'admin.solicitudes.*', 'admin.nomina.*', 'admin.jornaleros.*', 'mi-trabajo.*'],
        'reportes' => ['admin.reportes.*', 'admin.bitacora.*', 'admin.conversion.*'],
    ];

    /** Pantallas neutras a propósito (generales o de la cuenta): sin color de área. */
    public const NEUTRAS = [
        'inicio', 'mi-cuenta.*', 'admin.ajustes.*', 'admin.fases.*', 'admin.concentrados.*', 'admin.datos.*', 'admin.agenda.*',
        'login', 'registro', 'invitacion.*', 'password.*', 'dos-pasos.*', 'legal.*', 'plataforma.*',
    ];

    /** Ícono de cada área (en el recuadro del título de sus pantallas). */
    public const ICONOS = ['campo' => 'lagos', 'bodega' => 'bodega', 'ventas' => 'dinero', 'personal' => 'personal', 'reportes' => 'reporte'];

    /** Área de una ruta, o null (neutra o sin clasificar). */
    public static function deRuta(?string $ruta): ?string
    {
        if ($ruta === null) {
            return null;
        }
        foreach (self::AREAS as $area => $patrones) {
            if (Str::is($patrones, $ruta)) {
                return $area;
            }
        }

        return null;
    }

    /** Área de la pantalla actual, o null. */
    public static function actual(): ?string
    {
        return self::deRuta(request()->route()?->getName());
    }

    /** ¿La ruta está clasificada (con área o neutra a propósito)? */
    public static function clasificada(string $ruta): bool
    {
        return self::deRuta($ruta) !== null || Str::is(self::NEUTRAS, $ruta);
    }
}
