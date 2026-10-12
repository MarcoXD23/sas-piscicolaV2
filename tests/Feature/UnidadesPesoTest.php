<?php

namespace Tests\Feature;

use App\Support\UnidadesPeso;
use Tests\TestCase;

/**
 * Unidades de peso del servidor con la misma tabla de casos que tests/js/unidades-peso.test.mjs
 * (tests/fixtures/unidades-peso.json, generada con aritmética decimal exacta): el redondeo coincide con el teléfono.
 */
class UnidadesPesoTest extends TestCase
{
    private const MIN = 10;

    private const MAX = 99999900;

    /** @return array<string, mixed> */
    private function casos(): array
    {
        return json_decode((string) file_get_contents(base_path('tests/fixtures/unidades-peso.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_conversiones_de_la_tabla_compartida(): void
    {
        foreach ($this->casos()['conversiones'] as $caso) {
            $resultado = UnidadesPeso::aCentesimas($caso['valor'], $caso['unidad']);
            $this->assertSame($caso['centesimas'], $resultado, "{$caso['valor']} {$caso['unidad']}");
            $this->assertSame($caso['en_rango'], $resultado >= self::MIN && $resultado <= self::MAX, "rango de {$caso['valor']} {$caso['unidad']}");
        }
    }

    public function test_valores_invalidos_de_la_tabla_compartida(): void
    {
        foreach ($this->casos()['invalidos'] as $valor) {
            $this->assertFalse(UnidadesPeso::numeroValido($valor), json_encode($valor));
            $this->assertNull(UnidadesPeso::aCentesimas($valor, 'g'), json_encode($valor));
        }
        $this->assertNull(UnidadesPeso::aCentesimas('1', 'onzas'));
        $this->assertNull(UnidadesPeso::aCentesimas(['1'], 'g'));
        $this->assertSame(150, UnidadesPeso::aCentesimas(1.5, 'g'));   // un número (no texto) también
        $this->assertNull(UnidadesPeso::aCentesimas(1.0E-7, 'g'));    // pero no en notación científica
    }

    public function test_promedios_y_valores_en_la_unidad_de_la_tabla_compartida(): void
    {
        $casos = $this->casos();
        foreach ($casos['promedios'] as $caso) {
            $this->assertSame($caso['promedio'], UnidadesPeso::promedio($caso['centesimas'], $caso['peces']), "{$caso['centesimas']}/{$caso['peces']}");
        }
        foreach ($casos['en_unidad'] as $caso) {
            $this->assertSame($caso['milesimas'], UnidadesPeso::aMilesimas($caso['centesimas'], $caso['unidad']), "{$caso['centesimas']} {$caso['unidad']}");
        }
    }

    public function test_los_factores_son_los_exactos(): void
    {
        // 1 lb = 453,59237 g y 1 kg = 1000 g, en centésimas de gramo
        $this->assertSame([45359237, 1000], UnidadesPeso::FACTORES['lb']);
        $this->assertSame([100000, 1], UnidadesPeso::FACTORES['kg']);
        $this->assertSame([100, 1], UnidadesPeso::FACTORES['g']);
        $this->assertSame(['g', 'kg', 'lb'], UnidadesPeso::UNIDADES);
    }
}
