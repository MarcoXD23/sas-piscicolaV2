// Cuentas de la tabla de pesadas de la cosecha (sin Alpine). Correr con: npm run test:js (node --test, sin paquetes)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    avance, cantidad, centesimas, descuentoC, enteroC, estadoModo, filaNueva, kg, linea, metaC, metaTexto, numero, totales, validar,
} from '../../resources/js/pesaje-logica.js';

const t = (clave, datos = {}) => `${clave}${Object.keys(datos).length ? JSON.stringify(datos) : ''}`;
const vacias = { canasta: '2', canastilla: '1,5' };
const fila = (tipo, cuantas, peso) => ({ tipo, cantidad: String(cuantas), peso: String(peso) });

test('números con coma o punto, en centésimas enteras', () => {
    assert.equal(numero('35,5'), 35.5);
    assert.equal(numero('35.5'), 35.5);
    assert.equal(numero(''), null);
    assert.equal(numero('abc'), null);
    assert.equal(centesimas('0,1'), 10);
    assert.equal(centesimas('2.005'), 201);
    // La cantidad de vacías es un entero de 1 o más
    assert.equal(cantidad({ cantidad: '3' }), 3);
    assert.equal(cantidad({ cantidad: '0' }), null);
    assert.equal(cantidad({ cantidad: '1,5' }), null);
});

test('descuento de canastas y canastillas: cantidad × peso de una vacía', () => {
    assert.equal(descuentoC(fila('canasta', 3, 40), vacias), 600);       // 3 × 2 kg
    assert.equal(descuentoC(fila('canastilla', 2, 20), vacias), 300);    // 2 × 1,5 kg
    assert.equal(enteroC(fila('canasta', 3, 40), vacias), 3400);         // 40 − 6 = 34 kg enteros
    assert.equal(enteroC(fila('canastilla', 2, '20,5'), vacias), 1750);  // 20,5 − 3 = 17,5 kg
    // Sin cantidad, sin peso o sin peso de la vacía: no hay cuenta
    assert.equal(enteroC(fila('canasta', '', 40), vacias), null);
    assert.equal(enteroC(fila('canasta', 3, ''), vacias), null);
    assert.equal(descuentoC(fila('canasta', 3, 40), { canasta: '', canastilla: '1,5' }), null);
});

test('sumas: solo las pesadas completas, por tipo y por peso de vacía', () => {
    const filas = [fila('canasta', 3, 40), fila('canastilla', 2, '20,5'), fila('canasta', 1, 12), fila('canasta', '', '')];
    const suma = totales(filas, vacias);

    assert.equal(suma.pesadas, 3);
    assert.deepEqual(suma.porTipo, { canasta: 4, canastilla: 2 });
    assert.equal(suma.cantidad, 6);
    assert.equal(suma.pesado, 4000 + 2050 + 1200);
    assert.equal(suma.descuento, 600 + 300 + 200);
    assert.equal(suma.entero, 7250 - 1100);
    assert.deepEqual(suma.grupos, [{ tipo: 'canasta', vacia: 200, cantidad: 4 }, { tipo: 'canastilla', vacia: 150, cantidad: 2 }]);

    // La línea del cuaderno
    assert.equal(linea(suma, t, { decimal: ',', miles: '.' }),
        'pesaje_linea{"grupos":"pesaje_grupo_canasta_varios{\\"cantidad\\":4,\\"kg\\":\\"2\\"} + pesaje_grupo_canastilla_varios{\\"cantidad\\":2,\\"kg\\":\\"1,5\\"}","descuento":"11","pesado":"72,5","entero":"61,5"}');
    assert.equal(linea(totales([], vacias), t), '');
});

test('kg con los separadores de la finca y sin ceros de más', () => {
    assert.equal(kg(200), '2');
    assert.equal(kg(3550), '35,5');
    assert.equal(kg(123425), '1.234,25');
    assert.equal(kg(123425, { decimal: '.', miles: ',' }), '1,234.25');
    assert.equal(kg(-150), '-1,5');
    assert.equal(kg(null), '—');
});

test('cambio de modo: se ve y se envía solo un modo', () => {
    assert.deepEqual(estadoModo('pesadas'), { total: { visible: false, habilitado: false, requerido: false }, pesadas: { visible: true, habilitado: true } });
    assert.deepEqual(estadoModo('total'), { total: { visible: true, habilitado: true, requerido: true }, pesadas: { visible: false, habilitado: false } });
});

test('validación al enviar: en modo total no se revisan las pesadas', () => {
    assert.deepEqual(validar('total', [fila('canasta', '', '')], vacias, t), { aviso: '', indice: null });
});

test('validación al enviar: vacías sin peso, fila incompleta y pesada que no pasa del descuento', () => {
    assert.deepEqual(validar('pesadas', [fila('canasta', 3, 40)], { canasta: '0', canastilla: '1,5' }, t), { aviso: 'pesaje_vacias', indice: null });
    assert.deepEqual(validar('pesadas', [fila('canasta', 3, 40), fila('canasta', '', 10)], vacias, t), { aviso: 'pesaje_incompleta{"numero":2}', indice: 1 });
    assert.deepEqual(validar('pesadas', [fila('canasta', 3, 6)], vacias, t), { aviso: 'pesaje_bajo_descuento{"numero":1}', indice: 0 });
    assert.deepEqual(validar('pesadas', [fila('canasta', 3, 40), fila('canastilla', 2, 20)], vacias, t), { aviso: '', indice: null });
});

test('meta: avance de 0 a 100 y lo que falta según el promedio', () => {
    assert.equal(metaC(''), null);
    assert.equal(metaC('0'), null);
    assert.equal(metaC('100'), 10000);
    assert.equal(avance(5000, 10000), 50);
    assert.equal(avance(15000, 10000), 100);
    assert.equal(avance(5000, null), 0);

    const suma = totales([fila('canasta', 1, 22), fila('canasta', 1, 22)], vacias);   // 2 pesadas de 20 kg enteros
    assert.deepEqual(metaTexto(suma, 10000, t), [
        'meta_llevas{"entero":"40","meta":"100"}', 'meta_faltan{"kg":"60"}', 'meta_promedio_varias{"promedio":"20","pesadas":3}',
    ]);
    assert.deepEqual(metaTexto(suma, 4000, t), ['meta_llegaste{"meta":"40"}']);
    assert.deepEqual(metaTexto(suma, 3000, t), ['meta_sobran{"meta":"30","kg":"10"}']);
});

test('una pesada nueva sigue el tipo de la anterior', () => {
    assert.deepEqual(filaNueva(), { tipo: 'canasta', cantidad: '', peso: '' });
    assert.deepEqual(filaNueva({ tipo: 'canastilla', cantidad: '3', peso: '9' }), { tipo: 'canastilla', cantidad: '', peso: '' });
});
