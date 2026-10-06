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

    private const ESPECIES = [
        'mojarra roja' => [
            'nombre' => 'Mojarra roja',
            'cientifico' => 'Oreochromis sp. (híbrido)',
            'familia' => 'Cichlidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => null,
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.8, 8.2],
            'densidad_tierra' => '3 a 5 peces/m²',
            'densidad_tanque' => '25 a 40 peces/m³',
            'proteina' => ['iniciacion' => '45 %', 'levante' => '32 % a 34 %', 'engorde' => '24 % a 30 %'],
            'meses_cosecha' => '5 a 6 meses',
            'peso_comercial' => '400 a 550 g',
            'policultivo' => 'Especie principal de media agua. Se combina bien con cachama y bocachico.',
            'manejo' => [
                'Preparación del estanque' => 'Secar el fondo, encalar con cal agrícola o dolomita y fertilizar suave para estimular el fitoplancton.',
                'Siembra' => 'Aclimatar las bolsas flotando 20 a 30 minutos (diferencia de temperatura menor a 1 °C) y liberar en las horas frescas de la mañana.',
                'Alimentación' => 'Concentrado flotante para observar el apetito. Reducir la ración en días nublados o lluviosos.',
                'Calidad del agua' => 'Sensible al amonio y a los nitritos. Mantener aireación en la noche y la madrugada.',
                'Cosecha' => 'Ayuno de 24 a 36 horas. Usar redes sin nudos para no desprender escamas y conservar el color.',
            ],
        ],
        'mojarra negra' => [
            'nombre' => 'Mojarra negra (tilapia nilótica)',
            'cientifico' => 'Oreochromis niloticus',
            'familia' => 'Cichlidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => '/images/especies/mojarra-negra.webp',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 8.5],
            'densidad_tierra' => '4 a 6 peces/m²',
            'densidad_tanque' => '30 a 50 peces/m³',
            'proteina' => ['iniciacion' => '40 % a 45 %', 'levante' => '32 % a 34 %', 'engorde' => '24 % a 28 %'],
            'meses_cosecha' => '5 a 6 meses',
            'peso_comercial' => '450 a 600 g',
            'policultivo' => 'Especie principal de media agua y superficie, con buena conversión alimenticia.',
            'manejo' => [
                'Preparación del estanque' => 'Drenar y secar al sol 5 a 7 días. Encalar (50 a 100 g/m²) y llenar con filtro de malla fina para evitar peces silvestres.',
                'Siembra' => 'Aclimatar 20 a 30 minutos mezclando poco a poco el agua del estanque con la de la bolsa. Sembrar entre 6:00 y 8:00 a. m.',
                'Alimentación' => 'Iniciación con polvo o micropellet de 45 % (3 a 4 raciones al día); pasar a 32 % en levante y 24 % a 28 % en engorde según la biomasa.',
                'Calidad del agua' => 'Medir oxígeno a las 5:00 a. m. y encender aireadores si baja de 3,5 mg/L. Revisar pH y transparencia cada semana.',
                'Cosecha' => 'Ayuno de 24 a 36 horas y choque térmico en agua con hielo (0 a 4 °C).',
            ],
        ],
        'cachama blanca' => [
            'nombre' => 'Cachama blanca',
            'cientifico' => 'Piaractus brachypomus',
            'familia' => 'Serrasalmidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => '/images/especies/cachama-blanca.webp',
            'temperatura' => [25, 30],
            'oxigeno_min' => 3,
            'ph' => [6.0, 8.0],
            'densidad_tierra' => '1 a 2 peces/m²',
            'densidad_tanque' => '8 a 15 peces/m³',
            'proteina' => ['iniciacion' => '34 % a 38 %', 'levante' => '28 % a 32 %', 'engorde' => '24 % a 28 %'],
            'meses_cosecha' => '6 a 8 meses',
            'peso_comercial' => '500 g a 1 kg',
            'policultivo' => 'Muy buena para policultivo con mojarra y bocachico; es omnívora y no compite de forma agresiva.',
            'manejo' => [
                'Resistencia' => 'Especie rústica que tolera por poco tiempo niveles bajos de oxígeno.',
                'Estanques' => 'Se adapta mejor a estanques de tierra amplios, de 1,2 a 1,8 m de profundidad.',
                'Alimentación' => 'Omnívora. Además del concentrado aprovecha frutas, hojas tiernas y granos.',
                'Crecimiento' => 'Crece rápido con temperaturas por encima de 27 °C.',
                'Cosecha' => 'Manipular rápido por su fuerza y su dentadura. Despesque total o selectivo.',
            ],
        ],
        'bocachico' => [
            'nombre' => 'Bocachico',
            'cientifico' => 'Prochilodus magdalenae',
            'familia' => 'Prochilodontidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'detritivoro',
            'foto' => '/images/especies/bocachico.webp',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 7.8],
            'densidad_tierra' => '0,5 a 1 pez/m² (en policultivo)',
            'densidad_tanque' => '5 a 8 peces/m³',
            'proteina' => ['iniciacion' => '32 %', 'levante' => '26 % a 28 %', 'engorde' => '20 % a 24 %'],
            'meses_cosecha' => '8 a 10 meses',
            'peso_comercial' => '250 a 400 g',
            'policultivo' => 'Limpiador de fondo: consume detritos y perifiton sin competir por el concentrado.',
            'manejo' => [
                'Policultivo' => 'Especie nativa de la cuenca Magdalena-Cauca. Remueve el lodo del fondo y ayuda a evitar zonas sin oxígeno.',
                'Siembra' => 'Sembrar 1 bocachico por cada 3 o 4 mojarras o cachamas.',
                'Sanidad' => 'Sensible a químicos y plaguicidas que arrastra la lluvia. Mantener canales que desvíen el agua de escorrentía.',
                'Cosecha' => 'Se cosecha en el despesque final del estanque.',
            ],
        ],
        'bagre rayado' => [
            'nombre' => 'Bagre rayado del Magdalena',
            'cientifico' => 'Pseudoplatystoma magdaleniatum',
            'familia' => 'Pimelodidae',
            'clima' => self::CLIMA_CALIDO,
            'alimentacion' => 'calido',
            'foto' => '/images/especies/bagre-rayado.webp',
            'temperatura' => [26, 30],
            'oxigeno_min' => 4,
            'ph' => [6.5, 7.5],
            'densidad_tierra' => '0,5 a 1 pez/m²',
            'densidad_tanque' => '3 a 6 peces/m³',
            'proteina' => ['iniciacion' => '45 % a 50 %', 'levante' => '40 % a 45 %', 'engorde' => '36 % a 40 %'],
            'meses_cosecha' => '9 a 12 meses',
            'peso_comercial' => '1,5 a 2,5 kg',
            'policultivo' => 'Carnívoro de fondo. En baja densidad ayuda a controlar crías no deseadas.',
            'manejo' => [
                'Adaptación al concentrado' => 'El paso de alimento vivo a concentrado en el alevinaje es la etapa más delicada.',
                'Hábitat' => 'Prefiere zonas profundas y con sombra; los refugios reducen el estrés.',
                'Alimentación' => 'Más activo al atardecer y de noche: dar el 60 % a 70 % de la ración al caer la tarde.',
                'Cosecha' => 'Manipular con guantes por las espinas de las aletas pectorales y dorsal.',
            ],
        ],
        'trucha arcoiris' => [
            'nombre' => 'Trucha arcoíris',
            'cientifico' => 'Oncorhynchus mykiss',
            'familia' => 'Salmonidae',
            'clima' => self::CLIMA_FRIO,
            'alimentacion' => 'frio',
            'foto' => '/images/especies/trucha-arcoiris.webp',
            'temperatura' => [12, 16],
            'oxigeno_min' => 6.5,
            'ph' => [6.8, 7.8],
            'densidad_tierra' => 'No recomendada en estanque de tierra',
            'densidad_tanque' => '15 a 25 kg/m³ con flujo continuo',
            'proteina' => ['iniciacion' => '45 % a 50 %', 'levante' => '40 % a 42 %', 'engorde' => '38 % a 42 %'],
            'meses_cosecha' => '7 a 9 meses',
            'peso_comercial' => '250 a 350 g (porción)',
            'policultivo' => 'Solo monocultivo en agua fría. No se mezcla con especies de clima cálido.',
            'manejo' => [
                'Hábitat' => 'Zonas altas y frías, con agua limpia, corriente y bien oxigenada.',
                'Estanques' => 'Tanques de concreto o fibra con recambio continuo de agua.',
                'Alimentación' => 'Carnívora: concentrado de alta proteína.',
                'Clasificación' => 'Clasificar por tallas cada 3 a 4 semanas para evitar canibalismo.',
                'Cosecha' => 'Ayuno de 48 horas y sacrificio en agua con hielo.',
            ],
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
        $ficha = self::ESPECIES[mb_strtolower($clave)] ?? null;

        return $ficha === null ? null : ['clave' => mb_strtolower($clave), 'slug' => self::slug($clave)] + $ficha;
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
        return self::ESPECIES[mb_strtolower($clave)]['nombre'] ?? ucfirst($clave);
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
