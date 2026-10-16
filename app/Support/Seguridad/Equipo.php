<?php

namespace App\Support\Seguridad;

/**
 * Nombre legible del equipo a partir del user agent, sin paquetes: «Chrome en Android», «Safari en iPhone».
 * Es una ayuda para reconocer el equipo, no una huella: dos teléfonos iguales se ven igual.
 */
final class Equipo
{
    /** Navegadores en el orden en que se buscan (Edge y Opera dicen también «Chrome», Chrome dice también «Safari»). */
    private const NAVEGADORES = [
        'edge' => '/Edg(e|A|iOS)?\//',
        'opera' => '/OPR\/|Opera/',
        'samsung' => '/SamsungBrowser\//',
        'firefox' => '/Firefox\/|FxiOS\//',
        'chrome' => '/Chrome\/|CriOS\//',
        'safari' => '/Safari\//',
    ];

    private const SISTEMAS = [
        'android' => '/Android/',
        'iphone' => '/iPhone/',
        'ipad' => '/iPad/',
        'windows' => '/Windows/',
        'chromeos' => '/CrOS/',
        'macos' => '/Macintosh|Mac OS X/',
        'linux' => '/Linux/',
    ];

    public static function describir(?string $userAgent): string
    {
        $agente = (string) $userAgent;
        $navegador = self::primero(self::NAVEGADORES, $agente);
        $sistema = self::primero(self::SISTEMAS, $agente);

        return match (true) {
            $navegador !== null && $sistema !== null => __('seguridad.equipo.en', ['navegador' => __("seguridad.equipo.navegadores.{$navegador}"), 'sistema' => __("seguridad.equipo.sistemas.{$sistema}")]),
            $navegador !== null => __("seguridad.equipo.navegadores.{$navegador}"),
            $sistema !== null => __("seguridad.equipo.sistemas.{$sistema}"),
            default => __('seguridad.equipo.desconocido'),
        };
    }

    /** Red recortada: los 3 primeros bloques en IPv4 (190.24.8.x) y 48 bits en IPv6. Sin la IP completa. */
    public static function red(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return implode('.', array_slice(explode('.', $ip), 0, 3)).'.x';
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // Los 8 bloques de 16 bits (también si se escribió con «::»); se guardan los 3 primeros
            $bloques = array_map(fn (string $bloque) => dechex(hexdec($bloque)), str_split(bin2hex(inet_pton($ip)), 4));

            return implode(':', array_slice($bloques, 0, 3)).'::/48';
        }

        return null;
    }

    /** @param  array<string, string>  $patrones */
    private static function primero(array $patrones, string $agente): ?string
    {
        foreach ($patrones as $clave => $patron) {
            if (preg_match($patron, $agente)) {
                return $clave;
            }
        }

        return null;
    }
}
