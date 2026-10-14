<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Cómo se muestra una hora: 12 horas («10:24 p. m.») o 24 horas («22:24»). Solo la presentación: lo guardado sigue en
 * UTC, la fecha no cambia y los cálculos tampoco. La zona la decide quien llama (como hasta ahora con format()).
 * - Preferencia, en este orden: la persona (Mi cuenta) → la finca (Ajustes) → el país (config/region.php) → 24.
 * - En las vistas y el código se usa el macro de Carbon formatoHora('d/m/Y H:i'): igual que format(), pero la parte
 *   «H:i» (o «H:i:s») sale en el formato de la persona. Los formatos técnicos (formularios, ISO) siguen con format().
 * - El teléfono usa la misma regla con resources/js/hora.js (casos compartidos en tests/fixtures/horas.json).
 */
final class Hora
{
    public const FORMATOS = ['12', '24'];

    public const DOCE = '12';

    public const VEINTICUATRO = '24';

    /** Formato forzado (para avisos y pruebas); null = la cadena normal. */
    private static ?string $forzado = null;

    public static function valido(?string $formato): bool
    {
        return in_array($formato, self::FORMATOS, true);
    }

    /** Formato del país de la finca: 12 en los países de config('region.formato_hora_12'); si no, o sin país, 24. */
    public static function delPais(?string $pais): string
    {
        return $pais !== null && in_array($pais, (array) config('region.formato_hora_12'), true) ? self::DOCE : self::VEINTICUATRO;
    }

    /** Formato de la finca: el que eligió el propietario o el de su país. */
    public static function deFinca(?Tenant $finca): string
    {
        return self::valido($finca?->formato_hora) ? $finca->formato_hora : self::delPais($finca?->pais);
    }

    /** Formato de una persona: el suyo, el de su finca o el del país. Sin persona (avisos, comandos): el de la finca activa. */
    public static function de(?User $persona = null): string
    {
        if (self::$forzado !== null) {
            return self::$forzado;
        }
        $persona ??= Auth::user() instanceof User ? Auth::user() : null;
        if (self::valido($persona?->formato_hora)) {
            return $persona->formato_hora;
        }

        return self::deFinca($persona?->tenant ?? Region::tenant());
    }

    /** Ejecuta $accion con un formato fijo (por ejemplo, un correo en el formato de la finca). */
    public static function con(string $formato, callable $accion): mixed
    {
        $anterior = self::$forzado;
        self::$forzado = $formato;
        try {
            return $accion();
        } finally {
            self::$forzado = $anterior;
        }
    }

    /** «a. m.» o «p. m.» con espacios que no se parten («10:24 p. m.» nunca queda partido). */
    public static function meridiano(CarbonInterface $instante): string
    {
        return str_replace(' ', "\u{00A0}", __($instante->hour < 12 ? 'region.am' : 'region.pm'));
    }

    /**
     * Como format(), pero «H:i» (o «H:i:s») sale en el formato elegido: «d/m/Y H:i» → «15/10/2026 10:24 p. m.».
     * Sin «H:i» en el patrón, igual que format().
     */
    public static function formatear(CarbonInterface $instante, string $patron, ?string $formato = null): string
    {
        $formato ??= self::de();
        $conSegundos = str_contains($patron, 'H:i:s');
        $marca = $conSegundos ? 'H:i:s' : 'H:i';
        $posicion = strpos($patron, $marca);
        if ($posicion === false || $formato === self::VEINTICUATRO) {
            return $instante->format($patron);
        }
        $antes = substr($patron, 0, $posicion);
        $despues = substr($patron, $posicion + strlen($marca));
        $hora = $instante->format($conSegundos ? 'g:i:s' : 'g:i')."\u{00A0}".self::meridiano($instante);

        return ($antes === '' ? '' : $instante->format($antes)).$hora.($despues === '' ? '' : $instante->format($despues));
    }

    /** «10:24 p. m.» o «22:24». */
    public static function hora(CarbonInterface $instante): string
    {
        return self::formatear($instante, 'H:i');
    }

    /** Registra el macro formatoHora() en Carbon (AppServiceProvider). */
    public static function registrar(): void
    {
        $macro = function (string $patron): string {
            /** @var CarbonInterface $this */
            return Hora::formatear($this, $patron);
        };
        Carbon::macro('formatoHora', $macro);
        \Carbon\Carbon::macro('formatoHora', $macro);
        CarbonImmutable::macro('formatoHora', $macro);
    }
}
