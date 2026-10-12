// Unidades de peso del teléfono. Correr con: npm run test:js (node --test, sin paquetes)
// Usa la misma tabla de casos que tests/Feature/UnidadesPesoTest.php: el redondeo coincide exactamente con el servidor.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { aCentesimas, aMilesimas, FACTORES_GRAMOS, maximoEnUnidad, numeroValido, promedio, texto, unidadInicial } from '../../resources/js/unidades-peso.js';

const casos = JSON.parse(readFileSync(new URL('../fixtures/unidades-peso.json', import.meta.url), 'utf8'));
// Los mismos factores que manda el servidor (App\Support\UnidadesPeso::FACTORES)
const FACTORES = { g: [100, 1], kg: [100000, 1], lb: [45359237, 1000] };
const MIN = 10;
const MAX = 99999900;

test('conversiones de la tabla compartida', () => {
    for (const { valor, unidad, centesimas, en_rango: enRango } of casos.conversiones) {
        const resultado = aCentesimas(valor, unidad, FACTORES);
        assert.equal(resultado, centesimas, `${valor} ${unidad}`);
        assert.equal(resultado >= MIN && resultado <= MAX, enRango, `rango de ${valor} ${unidad}`);
    }
});

test('valores inválidos de la tabla compartida', () => {
    for (const valor of casos.invalidos) {
        assert.equal(numeroValido(valor), false, JSON.stringify(valor));
        assert.equal(aCentesimas(valor, 'g', FACTORES), null, JSON.stringify(valor));
    }
});

test('promedios y valores en la unidad de la tabla compartida', () => {
    for (const { centesimas, peces, promedio: esperado } of casos.promedios) assert.equal(promedio(centesimas, peces), esperado, `${centesimas}/${peces}`);
    for (const { centesimas, unidad, milesimas } of casos.en_unidad) assert.equal(aMilesimas(centesimas, unidad, FACTORES), milesimas, `${centesimas} ${unidad}`);
    for (const [unidad, maximo] of Object.entries(casos.maximos)) assert.equal(maximoEnUnidad(MAX, unidad, FACTORES), Number(maximo), unidad);
});

test('paquete viejo sin factores: solo gramos', () => {
    assert.equal(aCentesimas('1250,5', 'g'), 125050);
    assert.equal(aCentesimas('1', 'lb'), null);
    assert.equal(unidadInicial('lb', 'kg', FACTORES_GRAMOS), 'g');
});

test('unidad al abrir: la recordada, la de la finca o gramos', () => {
    assert.equal(unidadInicial('lb', 'kg', FACTORES), 'lb');
    assert.equal(unidadInicial(null, 'kg', FACTORES), 'kg');
    assert.equal(unidadInicial('xx', null, FACTORES), 'g');
});

test('texto en gramos y en la unidad elegida', () => {
    assert.equal(texto(6253, 'g', FACTORES, 'en'), '62.53 g');
    assert.equal(texto(6253, 'lb', FACTORES, 'en'), '62.53 g (0.138 lb)');
    assert.equal(texto(100000, 'kg', FACTORES, 'en'), '1,000.00 g (1.000 kg)');
});
