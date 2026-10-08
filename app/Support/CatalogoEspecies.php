<?php

namespace App\Support;

/**
 * Datos de REFERENCIA de las 6 especies por defecto. Ya no es la lista de especies de la finca:
 * cada finca tiene su catálogo editable en la tabla especies (App\Models\Especie), que se carga con estos valores
 * al crear la finca. La clave une cada especie por defecto con sus textos de referencia (densidades, proteína,
 * manejo) mientras no tenga ficha propia, y su foto de referencia con licencia libre (autor, licencia y fuente) mientras
 * la finca no suba fotos propias (ver docs/marca/LEEME.md).
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
            // Foto de referencia con licencia libre (autor, licencia y fuente: docs/marca/LEEME.md)
            'foto' => ['ruta' => '/images/especies/mojarra-roja.webp', 'autor' => 'Takeaway', 'licencia' => 'CC BY-SA 3.0', 'fuente' => 'https://commons.wikimedia.org/wiki/File:Pla_thapthim.jpg'],
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.8, 8.2],
        ],
        'mojarra negra' => [
            'cientifico' => 'Oreochromis niloticus',
            'familia' => 'Cichlidae',
            // Foto de referencia con licencia libre (autor, licencia y fuente: docs/marca/LEEME.md)
            'foto' => ['ruta' => '/images/especies/mojarra-negra.webp', 'autor' => 'Duane Raver, U.S. Fish and Wildlife Service', 'licencia' => 'dominio_publico', 'fuente' => 'https://commons.wikimedia.org/wiki/File:Tilapia_oreochromis_niloticus_fish.jpg'],
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 8.5],
        ],
        'cachama blanca' => [
            'cientifico' => 'Piaractus brachypomus',
            'familia' => 'Serrasalmidae',
            // Foto de referencia con licencia libre (autor, licencia y fuente: docs/marca/LEEME.md)
            'foto' => ['ruta' => '/images/especies/cachama-blanca.webp', 'autor' => 'w_endo (iNaturalist)', 'licencia' => 'CC0', 'fuente' => 'https://commons.wikimedia.org/wiki/File:Piaractus_brachypomus_208962529.jpg'],
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'temperatura' => [25, 30],
            'oxigeno_min' => 3,
            'ph' => [6.0, 8.0],
        ],
        'bocachico' => [
            'cientifico' => 'Prochilodus magdalenae',
            'familia' => 'Prochilodontidae',
            // Foto de referencia con licencia libre (autor, licencia y fuente: docs/marca/LEEME.md)
            'foto' => ['ruta' => '/images/especies/bocachico.webp', 'autor' => 'Zuluaga-Gómez A., Ictiología Universidad Católica de Oriente', 'licencia' => 'CC BY 2.0', 'fuente' => 'https://commons.wikimedia.org/wiki/File:Prochilodus_magdalenae_(16197296986).jpg'],
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'detritivoro',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 7.8],
        ],
        'bagre rayado' => [
            'cientifico' => 'Pseudoplatystoma magdaleniatum',
            'familia' => 'Pimelodidae',
            // Foto de referencia con licencia libre (autor, licencia y fuente: docs/marca/LEEME.md)
            'foto' => ['ruta' => '/images/especies/bagre-rayado.webp', 'autor' => 'Whaldener Endo', 'licencia' => 'CC0', 'fuente' => 'https://commons.wikimedia.org/wiki/File:Pseudoplaystoma_duo.jpg', 'nota' => 'mismo_genero'],
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 7.5],
        ],
        'trucha arcoiris' => [
            'cientifico' => 'Oncorhynchus mykiss',
            'familia' => 'Salmonidae',
            // Foto de referencia con licencia libre (autor, licencia y fuente: docs/marca/LEEME.md)
            'foto' => ['ruta' => '/images/especies/trucha-arcoiris.webp', 'autor' => 'USFWS Mountain Prairie', 'licencia' => 'dominio_publico', 'fuente' => 'https://commons.wikimedia.org/wiki/File:Rainbow_Trout_(Oncorhynchus_mykiss)_Gavins_Point.jpg'],
            'clima' => self::CLIMA_FRIO,
            'alimentacion' => 'frio',
            'temperatura' => [12, 16],
            'oxigeno_min' => 6.5,
            'ph' => [6.8, 7.8],
        ],
    ];

    /** @return list<string> Claves de las especies por defecto */
    public static function claves(): array
    {
        return array_keys(self::ESPECIES);
    }

    /**
     * Filas para cargar el catálogo de una finca nueva (o existente, en la migración).
     *
     * @return list<array<string, mixed>>
     */
    public static function porDefecto(): array
    {
        return array_map(fn (string $clave) => [
            'clave' => $clave,
            'nombre' => self::textos($clave)['nombre'] ?? ucfirst($clave),
            'nombre_cientifico' => self::ESPECIES[$clave]['cientifico'],
            'clima' => self::ESPECIES[$clave]['clima'] === self::CLIMA_FRIO ? 'frio' : 'calido',
            'temp_min' => self::ESPECIES[$clave]['temperatura'][0],
            'temp_max' => self::ESPECIES[$clave]['temperatura'][1],
            'oxigeno_min' => self::ESPECIES[$clave]['oxigeno_min'],
            'ph_min' => self::ESPECIES[$clave]['ph'][0],
            'ph_max' => self::ESPECIES[$clave]['ph'][1],
            'alimentacion' => self::ESPECIES[$clave]['alimentacion'],
        ], self::claves());
    }

    /**
     * Ficha editable de referencia (cultivo, procesamiento, color y aspecto) de una especie por defecto, marcada como
     * orientativa. Va aparte de porDefecto() porque la agrega una migración posterior a la del catálogo.
     *
     * @return array{cultivo: array<string, string>, procesamiento: array<string, string>, color: ?string, aspecto: ?string, ficha_orientativa: bool}
     */
    public static function fichaPorDefecto(string $clave): array
    {
        $textos = self::textos($clave);

        return [
            'cultivo' => $textos['cultivo'] ?? [],
            'procesamiento' => $textos['procesamiento'] ?? [],
            'color' => $textos['color'] ?? null,
            'aspecto' => $textos['aspecto'] ?? null,
            'ficha_orientativa' => true,
        ];
    }

    /**
     * Textos y foto de referencia de una especie por defecto (familia, densidades, proteína, manejo...). Vacío si no lo es.
     *
     * @return array<string, mixed>
     */
    public static function referencia(?string $clave): array
    {
        if ($clave === null || ! isset(self::ESPECIES[$clave])) {
            return [];
        }

        return self::textos($clave) + ['familia' => self::ESPECIES[$clave]['familia'], 'foto' => self::ESPECIES[$clave]['foto']];
    }

    /** @return array<string, mixed> Textos de la ficha en el idioma actual (nombre, densidades, proteína, manejo...) */
    private static function textos(string $clave): array
    {
        $textos = __('especies.catalogo.'.$clave);

        return is_array($textos) ? $textos : [];
    }
}
