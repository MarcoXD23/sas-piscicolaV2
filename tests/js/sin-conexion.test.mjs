// Reglas de la cola de registros sin conexión. Correr con: npm run test:js (usa node --test, sin paquetes)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { camposParaGuardar, clasificar, fechaLocal, horasGuardado, motivoRechazo, porVencer } from '../../resources/js/sin-conexion-logica.js';

test('guardado y duplicado salen de la cola', () => {
    assert.equal(clasificar(201), 'listo');
    assert.equal(clasificar(200), 'listo');
});

test('las reglas de la finca rechazan sin reintentar', () => {
    for (const estado of [403, 404, 409, 422]) assert.equal(clasificar(estado), 'rechazado', String(estado));
});

test('sesión vencida detiene el envío', () => {
    assert.equal(clasificar(401), 'sesion');
    assert.equal(clasificar(419), 'sesion');
});

test('fallas del servidor, límite o red se reintentan', () => {
    for (const estado of [0, 302, 429, 500, 502, 503]) assert.equal(clasificar(estado), 'reintentar', String(estado));
});

test('el motivo es el primer error de validación, si no el mensaje', () => {
    assert.equal(motivoRechazo({ message: 'General', errors: { cantidad: ['No hay tantos peces vivos.'] } }, 'x'), 'No hay tantos peces vivos.');
    assert.equal(motivoRechazo({ message: 'Fuera de tu turno.' }, 'x'), 'Fuera de tu turno.');
    assert.equal(motivoRechazo({}, 'Por defecto'), 'Por defecto');
    assert.equal(motivoRechazo(null, 'Por defecto'), 'Por defecto');
    assert.equal(motivoRechazo({ message: '   ' }, 'Por defecto'), 'Por defecto');
});

test('por vencer: faltan menos de 8 horas del límite', () => {
    const ahora = new Date('2026-10-08T12:00:00Z');
    assert.equal(horasGuardado('2026-10-08T10:00:00Z', ahora), 2);
    assert.equal(porVencer('2026-10-07T00:00:00Z', 48, ahora), false);   // 36 h
    assert.equal(porVencer('2026-10-06T20:00:00Z', 48, ahora), true);    // 40 h
    assert.equal(porVencer('2026-10-08T07:00:00Z', 6, ahora), true);     // límite corto: desde la mitad
});

test('no se guardan el token ni la marca de la ventana ni archivos', () => {
    const entradas = [['_token', 'abc'], ['_modal', 'modal-racion-1'], ['cantidad_kg', '12.5'], ['foto', { name: 'x.jpg' }], ['nota', '']];
    assert.deepEqual(camposParaGuardar(entradas), [['cantidad_kg', '12.5'], ['nota', '']]);
});

test('fecha local con ceros', () => {
    assert.equal(fechaLocal(new Date(2026, 0, 5, 23, 59)), '2026-01-05');
});
