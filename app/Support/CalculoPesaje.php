<?php

namespace App\Support;

use App\Models\Cosecha;
use App\Models\PesajeCosecha;

/**
 * Cuentas del pesaje de una cosecha, como en el cuaderno de la finca.
 * Cada PESADA es un montón de pescado en varias canastas o canastillas:
 *   descuento = cantidad × peso de UNA vacía (se MULTIPLICA, nunca se divide)
 *   peso entero = peso pesado − descuento
 * Ejemplo: 20 canastas de 2 kg = 40 kg de descuento; 655 kg pesados − 40 kg = 615 kg enteros.
 * Las cuentas se hacen en centésimas de kg (enteros) para que sean exactas, igual que el CHECK de la base.
 * La usan el servidor al guardar, el detalle de la cosecha, el historial y las exportaciones.
 */
final class CalculoPesaje
{
    public const CANASTA = 'canasta';

    public const CANASTILLA = 'canastilla';

    public const TIPOS = [self::CANASTA, self::CANASTILLA];

    public const MAX_PESADAS = 200;

    public const MAX_CANTIDAD = 50;

    public const MAX_PESO_PESADA_KG = 2000;

    public const MIN_PESO_VACIA_KG = 0.01;

    public const MAX_PESO_VACIA_KG = 20;

    /**
     * @param  list<array{tipo: string, cantidad: int|string, peso_pesado: float|string}>  $pesadas
     * @return array{filas: list<array{numero: int, tipo: string, cantidad: int, peso_vacia: float, peso_pesado: float, descuento: float, entero: float}>, totales: array<string, mixed>}
     */
    public static function calcular(array $pesadas, float $pesoCanasta, float $pesoCanastilla): array
    {
        $vacias = [self::CANASTA => self::centesimas($pesoCanasta), self::CANASTILLA => self::centesimas($pesoCanastilla)];

        $filas = [];
        foreach (array_values($pesadas) as $indice => $pesada) {
            $filas[] = self::fila($indice + 1, (string) $pesada['tipo'], (int) $pesada['cantidad'], $vacias[$pesada['tipo']], self::centesimas($pesada['peso_pesado']));
        }

        return ['filas' => $filas, 'totales' => self::totales($filas)];
    }

    /** Las pesadas guardadas de una cosecha (cada una con el peso de una vacía que se usó ese día). */
    public static function deCosecha(Cosecha $cosecha): array
    {
        $filas = $cosecha->pesajes->map(fn (PesajeCosecha $p) => self::fila(
            $p->numero, $p->tipo, $p->canastas, self::centesimas($p->tara_unitaria_kg), self::centesimas($p->bruto_kg),
        ))->all();

        return ['filas' => $filas, 'totales' => self::totales($filas)];
    }

    /** Columnas de pesajes_cosecha para guardar una fila calculada. */
    public static function paraGuardar(array $fila): array
    {
        return [
            'numero' => $fila['numero'],
            'tipo' => $fila['tipo'],
            'canastas' => $fila['cantidad'],
            'tara_unitaria_kg' => $fila['peso_vacia'],
            'bruto_kg' => $fila['peso_pesado'],
            'tara_kg' => $fila['descuento'],
            // La columna se llama neto_kg, pero guarda el peso ENTERO (pescado sin procesar, ya sin canastas)
            'neto_kg' => $fila['entero'],
        ];
    }

    /**
     * La cuenta en números, por ejemplo:
     * "11 canastas × 2 kg + 9 canastillas × 1,5 kg = 35,5 kg de descuento; 655 kg − 35,5 kg = 619,5 kg enteros".
     */
    public static function lineaCuenta(array $totales): string
    {
        $partes = collect($totales['grupos'])->map(fn (array $grupo) => trans_choice('cosechas.cuenta.grupo_'.$grupo['tipo'], $grupo['cantidad'], [
            'cantidad' => Formato::numero($grupo['cantidad']),
            'kg' => self::kg($grupo['peso_vacia']),
        ]))->implode(' + ');

        return __('cosechas.cuenta.linea', [
            'grupos' => $partes,
            'descuento' => self::kg($totales['descuento']),
            'pesado' => self::kg($totales['pesado']),
            'entero' => self::kg($totales['entero']),
        ]);
    }

    /** Kg con los separadores de la finca y sin ceros de más (2; 35,5; 619,5; 1.234,25). */
    public static function kg(float $valor): string
    {
        return Formato::sinCeros($valor);
    }

    private static function fila(int $numero, string $tipo, int $cantidad, int $vaciaCent, int $pesadoCent): array
    {
        $descuentoCent = $cantidad * $vaciaCent;

        return [
            'numero' => $numero,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'peso_vacia' => $vaciaCent / 100,
            'peso_pesado' => $pesadoCent / 100,
            'descuento' => $descuentoCent / 100,
            'entero' => ($pesadoCent - $descuentoCent) / 100,
        ];
    }

    private static function totales(array $filas): array
    {
        $suma = fn (string $campo) => array_sum(array_map(fn ($fila) => self::centesimas($fila[$campo]), $filas)) / 100;

        // Grupos por tipo y peso de una vacía, en orden de aparición (para la línea de la cuenta)
        $grupos = [];
        foreach ($filas as $fila) {
            $clave = $fila['tipo'].'|'.$fila['peso_vacia'];
            $grupos[$clave] ??= ['tipo' => $fila['tipo'], 'peso_vacia' => $fila['peso_vacia'], 'cantidad' => 0];
            $grupos[$clave]['cantidad'] += $fila['cantidad'];
        }

        $porTipo = array_fill_keys(self::TIPOS, 0);
        foreach ($filas as $fila) {
            $porTipo[$fila['tipo']] += $fila['cantidad'];
        }

        return [
            'pesadas' => count($filas),
            'cantidad' => array_sum($porTipo),
            'por_tipo' => $porTipo,
            'grupos' => array_values($grupos),
            'pesado' => $suma('peso_pesado'),
            'descuento' => $suma('descuento'),
            'entero' => $suma('entero'),
        ];
    }

    private static function centesimas(float|int|string $kg): int
    {
        return (int) round(((float) $kg) * 100);
    }
}
