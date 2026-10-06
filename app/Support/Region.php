<?php

namespace App\Support;

use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;
use NumberFormatter;

/**
 * Región de la finca actual: zona horaria, idioma, país y formatos.
 *
 * Regla de fechas: en la base de datos las fechas con hora se guardan en UTC (app.timezone = UTC).
 * Se muestran y se interpretan (formularios, "hoy", turnos) en la zona horaria de la finca.
 * Las columnas de solo fecha (date) son días del calendario de la finca y no se convierten.
 */
class Region
{
    public const ZONA_POR_DEFECTO = 'America/Bogota';

    public const IDIOMA_POR_DEFECTO = 'es';

    /** Idiomas con traducción completa en lang/. */
    public const IDIOMAS = ['es'];

    /** @var array<int, Tenant|null> */
    private static array $cache = [];

    public static function tenant(): ?Tenant
    {
        $id = app(TenantActual::class)->id();
        if ($id === null) {
            return null;
        }

        return self::$cache[$id] ??= Tenant::find($id);
    }

    /** Olvida los datos guardados en memoria (por ejemplo, después de editar los ajustes de la finca). */
    public static function olvidar(): void
    {
        self::$cache = [];
    }

    public static function zona(): string
    {
        return self::tenant()?->zona_horaria ?: self::ZONA_POR_DEFECTO;
    }

    public static function idioma(): string
    {
        return self::tenant()?->idioma ?: self::IDIOMA_POR_DEFECTO;
    }

    public static function pais(): ?string
    {
        return self::tenant()?->pais;
    }

    /** Configuración regional para formatos, por ejemplo "es_CO" (o solo "es" si no hay país). */
    public static function locale(): string
    {
        return self::idioma().(self::pais() ? '_'.self::pais() : '');
    }

    /** Momento actual en la zona de la finca. */
    public static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(self::zona());
    }

    /** Inicio del día de hoy en la zona de la finca. */
    public static function hoy(): CarbonImmutable
    {
        return self::ahora()->startOfDay();
    }

    /** Un instante (guardado en UTC) expresado en la zona de la finca, para mostrarlo o compararlo por día. */
    public static function local(?CarbonInterface $instante): ?CarbonImmutable
    {
        return $instante === null ? null : CarbonImmutable::instance($instante)->setTimezone(self::zona());
    }

    /** Interpreta una fecha y hora escritas en la zona de la finca y devuelve el instante en UTC (para guardar). */
    public static function aUtc(string $fechaHora): CarbonImmutable
    {
        return CarbonImmutable::parse($fechaHora, self::zona())->utc();
    }

    /** Pasa a UTC un instante que ya está en la zona de la finca. */
    public static function utc(CarbonInterface $instante): CarbonImmutable
    {
        return CarbonImmutable::instance($instante)->utc();
    }

    /** Valor en dinero con el formato de la moneda del país de la finca (montos enteros, sin centavos). */
    public static function dinero(int|float $valor): string
    {
        $pais = self::pais();
        if ($pais === null) {
            return number_format((float) $valor, 0, ',', '.');
        }

        $formato = new NumberFormatter(self::locale(), NumberFormatter::CURRENCY);
        $formato->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 0);
        $formato->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $moneda = (new NumberFormatter(self::locale(), NumberFormatter::CURRENCY))->getTextAttribute(NumberFormatter::CURRENCY_CODE);

        // NumberFormatter usa espacios especiales; se normalizan a espacios comunes
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', (string) $formato->formatCurrency((float) $valor, $moneda));
    }

    /**
     * Separadores decimal y de miles de la región (por ejemplo [',', '.'] en es_CO y ['.', ','] en es_MX).
     *
     * @return array{0: string, 1: string}
     */
    public static function separadores(): array
    {
        static $cache = [];
        $locale = self::locale();

        if (! isset($cache[$locale])) {
            $formato = new NumberFormatter($locale, NumberFormatter::DECIMAL);
            $normalizar = fn (string $simbolo) => str_replace(["\u{00A0}", "\u{202F}"], ' ', $simbolo);
            $cache[$locale] = [
                $normalizar($formato->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL)),
                $normalizar($formato->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL)),
            ];
        }

        return $cache[$locale];
    }

    /** Etiqueta de idioma para HTML y JavaScript: idioma y país separados por guion. */
    public static function etiquetaIdioma(): string
    {
        return str_replace('_', '-', self::locale());
    }

    /** @return list<string> Zonas horarias válidas (IANA). */
    public static function zonasValidas(): array
    {
        return DateTimeZone::listIdentifiers();
    }

    /** ¿Es un código de país ISO 3166-1 alfa-2 que intl reconoce? */
    public static function paisValido(?string $codigo): bool
    {
        return $codigo !== null && array_key_exists($codigo, self::paises());
    }

    /**
     * Países que reconoce intl, con su nombre en el idioma indicado, ordenados por nombre.
     *
     * @return array<string, string> código => nombre
     */
    public static function paises(?string $idioma = null): array
    {
        static $cache = [];
        $idioma ??= self::idioma();

        if (! isset($cache[$idioma])) {
            $desconocido = \Locale::getDisplayRegion('-ZZ', $idioma);
            $paises = [];
            foreach (range('A', 'Z') as $a) {
                foreach (range('A', 'Z') as $b) {
                    $codigo = $a.$b;
                    $nombre = \Locale::getDisplayRegion('-'.$codigo, $idioma);
                    // Códigos sin país asignado devuelven el mismo código o "Región desconocida"
                    if ($nombre !== '' && $nombre !== $codigo && $nombre !== $desconocido && count(DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $codigo)) > 0) {
                        $paises[$codigo] = $nombre;
                    }
                }
            }
            asort($paises, SORT_LOCALE_STRING);
            $cache[$idioma] = $paises;
        }

        return $cache[$idioma];
    }

    /** @return list<string> Zonas horarias de un país (para sugerir la correcta). */
    public static function zonasDelPais(string $pais): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $pais);
    }

    public static function nombrePais(string $codigo, ?string $idioma = null): string
    {
        return \Locale::getDisplayRegion('-'.$codigo, $idioma ?? self::idioma());
    }
}
