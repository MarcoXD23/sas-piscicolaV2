<?php

namespace App\Support;

/**
 * Códigos temporales de verificación en dos pasos (TOTP, RFC 6238), sin servicios externos ni paquetes:
 * HMAC-SHA1, 6 dígitos, pasos de 30 segundos. Compatible con Google Authenticator, Authy, Microsoft Authenticator, etc.
 */
final class Totp
{
    public const DIGITOS = 6;

    public const PERIODO = 30;

    /** Pasos de tolerancia hacia atrás y hacia adelante (relojes de teléfono un poco corridos). */
    public const TOLERANCIA = 1;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Clave secreta nueva: 20 bytes aleatorios en Base32 (32 caracteres). */
    public static function nuevaClave(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function pasoActual(?int $instante = null): int
    {
        return intdiv($instante ?? time(), self::PERIODO);
    }

    public static function codigo(string $clave, int $paso): string
    {
        $hash = hash_hmac('sha1', pack('J', $paso), self::desdeBase32($clave), true);
        $desfase = ord($hash[19]) & 0x0F;
        $valor = (unpack('N', substr($hash, $desfase, 4))[1] & 0x7FFFFFFF) % (10 ** self::DIGITOS);

        return str_pad((string) $valor, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * Paso de tiempo del código si es válido (dentro de la tolerancia y POSTERIOR al último ya usado); null si no.
     * Así un mismo código no se acepta dos veces.
     */
    public static function verificar(string $clave, string $codigo, ?int $ultimoPasoUsado = null, ?int $instante = null): ?int
    {
        $codigo = preg_replace('/\s+/', '', $codigo);
        if (! preg_match('/^\d{'.self::DIGITOS.'}$/', $codigo)) {
            return null;
        }

        $actual = self::pasoActual($instante);
        for ($paso = $actual - self::TOLERANCIA; $paso <= $actual + self::TOLERANCIA; $paso++) {
            if (($ultimoPasoUsado === null || $paso > $ultimoPasoUsado) && hash_equals(self::codigo($clave, $paso), $codigo)) {
                return $paso;
            }
        }

        return null;
    }

    /** Dirección otpauth:// que leen las apps (también va en el código QR). */
    public static function uri(string $clave, string $cuenta, string $emisor): string
    {
        $etiqueta = rawurlencode($emisor).':'.rawurlencode($cuenta);

        return 'otpauth://totp/'.$etiqueta.'?'.http_build_query([
            'secret' => $clave, 'issuer' => $emisor, 'algorithm' => 'SHA1', 'digits' => self::DIGITOS, 'period' => self::PERIODO,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** Clave en grupos de 4 para escribirla a mano. */
    public static function agrupada(string $clave): string
    {
        return trim(chunk_split($clave, 4, ' '));
    }

    private static function base32(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $grupo) => self::BASE32[bindec(str_pad($grupo, 5, '0'))], str_split($bits, 5)));
    }

    private static function desdeBase32(string $clave): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $clave))) as $letra) {
            $bits .= str_pad(decbin(strpos(self::BASE32, $letra)), 5, '0', STR_PAD_LEFT);
        }

        return implode('', array_map(fn (string $byte) => chr(bindec($byte)), array_filter(str_split($bits, 8), fn ($b) => strlen($b) === 8)));
    }
}
