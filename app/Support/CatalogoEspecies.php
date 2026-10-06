<?php

namespace App\Support;

/**
 * Catálogo fijo de especies que se pueden sembrar, con su ficha técnica de referencia.
 * La especie se guarda como string en siembras.especie (la clave de este catálogo).
 * Los valores son orientativos: cada finca debe ajustarlos con su asistente técnico.
 */
class CatalogoEspecies
{
    public const CLIMA_CALIDO = 'cálido';

    public const CLIMA_FRIO = 'frío';

    /** Datos técnicos de cada especie. Los textos (nombre, manejo, rangos escritos) están en lang/es/especies.php, en «catalogo». */
    private const ESPECIES = [
        'mojarra roja' => [
            'cientifico' => 'Oreochromis sp. (híbrido)',
            'familia' => 'Cichlidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => null,
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.8, 8.2],
        ],
        'mojarra negra' => [
            'cientifico' => 'Oreochromis niloticus',
            'familia' => 'Cichlidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => '/images/especies/mojarra-negra.webp',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 8.5],
        ],
        'cachama blanca' => [
            'cientifico' => 'Piaractus brachypomus',
            'familia' => 'Serrasalmidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => '/images/especies/cachama-blanca.webp',
            'temperatura' => [25, 30],
            'oxigeno_min' => 3,
            'ph' => [6.0, 8.0],
        ],
        'bocachico' => [
            'cientifico' => 'Prochilodus magdalenae',
            'familia' => 'Prochilodontidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'detritivoro',
            'foto' => '/images/especies/bocachico.webp',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 7.8],
        ],
        'bagre rayado' => [
            'cientifico' => 'Pseudoplatystoma magdaleniatum',
            'familia' => 'Pimelodidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => '/images/especies/bagre-rayado.webp',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 7.5],
        ],
        'trucha arcoiris' => [
            'cientifico' => 'Oncorhynchus mykiss',
            'familia' => 'Salmonidae',
            'clima' => self::CLIMA_FRIO,
            'alimentacion' => 'frio',
            'foto' => '/images/especies/trucha-arcoiris.webp',
            'temperatura' => [12, 16],
            'oxigeno_min' => 6.5,
            'ph' => [6.8, 7.8],
        ],
    ];

    /** @return list<string> Claves válidas para siembras.especie */
    public static function claves(): array
    {
        return array_keys(self::ESPECIES);
    }

    /** @return array<string, array<string, mixed>> */
    public static function todas(): array
    {
        return array_map(fn (string $clave) => self::ficha($clave), array_combine(self::claves(), self::claves()));
    }

    /** @return array<string, mixed>|null */
    public static function ficha(string $clave): ?array
    {
        $clave = mb_strtolower($clave);
        $ficha = self::ESPECIES[$clave] ?? null;

        return $ficha === null ? null : ['clave' => $clave, 'slug' => self::slug($clave)] + self::textos($clave) + $ficha;
    }

    /** @return array<string, mixed> Textos de la ficha en el idioma actual (nombre, densidades, proteína, manejo...) */
    private static function textos(string $clave): array
    {
        $textos = __('especies.catalogo.'.$clave);

        return is_array($textos) ? $textos : [];
    }

    /** @return array<string, mixed>|null */
    public static function porSlug(string $slug): ?array
    {
        foreach (self::claves() as $clave) {
            if (self::slug($clave) === $slug) {
                return self::ficha($clave);
            }
        }

        return null;
    }

    public static function slug(string $clave): string
    {
        return str_replace(' ', '-', mb_strtolower($clave));
    }

    public static function nombre(string $clave): string
    {
        return self::textos(mb_strtolower($clave))['nombre'] ?? ucfirst($clave);
    }

    public static function foto(string $clave): ?string
    {
        return self::ESPECIES[mb_strtolower($clave)]['foto'] ?? null;
    }

    public static function clima(string $clave): ?string
    {
        return self::ESPECIES[mb_strtolower($clave)]['clima'] ?? null;
    }
}
