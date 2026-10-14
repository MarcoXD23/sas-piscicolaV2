// Horas en 12 o 24 horas en el teléfono (resources/js/hora.js). Correr con: npm run test:js (node --test, sin paquetes)
// Usa la misma tabla de casos que tests/Feature/FormatoHoraTest.php: el teléfono escribe igual que el servidor.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { deCampo, diaMesHora, hora, horaDe, normalizar } from '../../resources/js/hora.js';

const { casos } = JSON.parse(readFileSync(new URL('../fixtures/horas.json', import.meta.url), 'utf8'));
const textos = { am: 'a. m.', pm: 'p. m.' };   // los de lang/es (js.php y region.php)
const DURO = ' ';

test('cada hora de la tabla compartida, en 12 y 24 horas', () => {
    for (const { horas, minutos, formato, texto } of casos) {
        assert.equal(horaDe(horas, minutos, formato, textos), texto, `${horas}:${minutos} en ${formato}`);
    }
});

test('medianoche y mediodía en 12 horas', () => {
    assert.equal(horaDe(0, 0, '12', textos), `12:00${DURO}a.${DURO}m.`);
    assert.equal(horaDe(12, 0, '12', textos), `12:00${DURO}p.${DURO}m.`);
});

test('sin formato o con uno desconocido (un paquete viejo): 24 horas', () => {
    assert.equal(normalizar(undefined), '24');
    assert.equal(normalizar('7'), '24');
    assert.equal(horaDe(22, 24, undefined, textos), '22:24');
});

test('fecha del teléfono con día y mes', () => {
    const fecha = new Date(2026, 9, 15, 22, 24);
    assert.equal(hora(fecha, '12', textos), `10:24${DURO}p.${DURO}m.`);
    assert.equal(diaMesHora(fecha, '12', textos), `15/10 10:24${DURO}p.${DURO}m.`);
    assert.equal(diaMesHora(fecha, '24', textos), '15/10 22:24');
});

test('ayuda bajo los campos de hora (time y datetime-local)', () => {
    assert.equal(deCampo('22:24', '12', textos), `10:24${DURO}p.${DURO}m.`);
    assert.equal(deCampo('06:00', '24', textos), '06:00');
    assert.equal(deCampo('2026-10-15T00:30', '12', textos), `15/10/2026 12:30${DURO}a.${DURO}m.`);
    assert.equal(deCampo('', '12', textos), '');
});
