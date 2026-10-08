// Reglas del paquete de trabajo (modo de campo). Correr con: npm run test:js (node --test, sin paquetes)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { clavesAjenas, debeRenovar, estadoToken, paqueteValido } from '../../resources/js/paquete-logica.js';

const paquete = (clave, generado) => ({ clave, generado_at: generado, secciones: ['ronda'], lagos: [] });

test('se renueva sin paquete, de otra persona, sin fecha válida o viejo', () => {
    const ahora = new Date('2026-10-08T12:00:00Z');
    assert.equal(debeRenovar(null, '1|2', 15, ahora), true);
    assert.equal(debeRenovar(paquete('1|3', '2026-10-08T11:59:00Z'), '1|2', 15, ahora), true);   // de otra persona
    assert.equal(debeRenovar(paquete('1|2', 'no es fecha'), '1|2', 15, ahora), true);
    assert.equal(debeRenovar(paquete('1|2', '2026-10-08T11:44:00Z'), '1|2', 15, ahora), true);     // 16 minutos
    assert.equal(debeRenovar(paquete('1|2', '2026-10-08T11:50:00Z'), '1|2', 15, ahora), false);    // 10 minutos
});

test('solo se guarda un paquete completo de la misma finca y persona', () => {
    assert.equal(paqueteValido(paquete('1|2', '2026-10-08T11:50:00Z'), '1|2'), true);
    assert.equal(paqueteValido(paquete('2|2', '2026-10-08T11:50:00Z'), '1|2'), false);   // otra finca
    assert.equal(paqueteValido({ clave: '1|2', generado_at: '2026-10-08T11:50:00Z', lagos: [] }, '1|2'), false);
    assert.equal(paqueteValido({ ...paquete('1|2', 'x') }, '1|2'), false);
    assert.equal(paqueteValido(null, '1|2'), false);
    assert.equal(paqueteValido('texto', '1|2'), false);
});

test('se borran los paquetes de otras personas y fincas', () => {
    assert.deepEqual(clavesAjenas(['1|2', '1|3', '2|2'], '1|2'), ['1|3', '2|2']);
    assert.deepEqual(clavesAjenas([], '1|2'), []);
});

test('el token: listo, sesión vencida o intentar después', () => {
    assert.equal(estadoToken(200, { token: 'abc' }), 'listo');
    assert.equal(estadoToken(200, { token: '' }), 'reintentar');
    assert.equal(estadoToken(200, null), 'reintentar');
    assert.equal(estadoToken(401, null), 'sesion');
    assert.equal(estadoToken(419, null), 'sesion');
    for (const estado of [0, 302, 429, 500, 503]) assert.equal(estadoToken(estado, null), 'reintentar', String(estado));
});
