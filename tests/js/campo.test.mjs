// Reglas de la pantalla Registro rápido. Correr con: npm run test:js (node --test, sin paquetes)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { antiguedad, armarRegistro, avisarFueraDeTurno, enTurno, numero, resumenErrores, textoHace, validar } from '../../resources/js/campo-logica.js';

const HOY = '2026-10-08';
const t = (clave, datos = {}) => `${clave}${Object.keys(datos).length ? JSON.stringify(datos) : ''}`;

const paquete = {
    clave: '1|2',
    secciones: ['racion', 'mortalidad', 'aviso_pesca', 'ronda', 'medicion'],
    lagos: [
        { id: 1, nombre: 'L01', url_racion: '/admin/lagos/1/raciones', url_aviso_pesca: '/admin/lagos/1/avisos-pesca', url_medicion: '/noche/lagos/1/mediciones', aviso_pendiente: false, racion_sugerida_kg: 4.5,
            siembras: [{ id: 7, especie: 'Cachama blanca', fecha: '2026-09-01', peces_vivos: 950, url_mortalidad: '/admin/siembras/7/mortalidades' }] },
        { id: 2, nombre: 'L02', url_medicion: '/noche/lagos/2/mediciones', url_aviso_pesca: '/admin/lagos/2/avisos-pesca', aviso_pendiente: true,
            siembras: [{ id: 8, especie: 'Bocachico', fecha: '2026-10-01', peces_vivos: 10 }] },
    ],
    concentrados: [{ tipo: 'Engorde 32%', kg: 120.5 }],
    tipos_ronda: [{ codigo: 'sin_novedad', texto: 'Sin novedad' }, { codigo: 'intrusion', texto: 'Intrusión' }],
    sin_novedad: 'sin_novedad',
    medicion: [
        { campo: 'temperatura_c', etiqueta: 'Temperatura (°C)', min: 0, max: 45, paso: 0.1 },
        { campo: 'transparencia_cm', etiqueta: 'Transparencia (cm)', min: 0, max: 500, paso: 1 },
    ],
    urls: { ronda: '/noche/rondas' },
    turnos: [{ rol: 'trabajador', inicia_at: '2026-10-08T11:00:00Z', termina_at: '2026-10-08T19:00:00Z' }],
    exige_turno: { racion: true, mortalidad: true, aviso_pesca: true, ronda: true, medicion: true },
};

const claves = (errores) => Object.fromEntries(Object.entries(errores).map(([campo, [clave]]) => [campo, clave]));

test('números con coma o punto', () => {
    assert.equal(numero('4,5'), 4.5);
    assert.equal(numero(' 12 '), 12);
    assert.ok(Number.isNaN(numero('')));
    assert.ok(Number.isNaN(numero('abc')));
    assert.ok(Number.isNaN(numero('1e3')));
});

test('ración: lago, concentrado de bodega, kilos y fecha', () => {
    assert.deepEqual(validar('racion', { lago_id: '1', tipo_concentrado: 'Engorde 32%', cantidad_kg: '4,5', fecha: HOY }, paquete, HOY), {});
    assert.deepEqual(claves(validar('racion', { lago_id: '2', tipo_concentrado: 'Otro', cantidad_kg: '0', fecha: '2026-10-09' }, paquete, HOY)),
        { lago_id: 'campo_error_lago', tipo_concentrado: 'campo_error_concentrado', cantidad_kg: 'campo_error_kg', fecha: 'campo_error_fecha_futura' });
    assert.deepEqual(validar('racion', { lago_id: '1', tipo_concentrado: 'Engorde 32%', cantidad_kg: '121', fecha: HOY }, paquete, HOY).cantidad_kg,
        ['campo_error_kg_bodega', { kg: 120.5 }]);
});

test('mortalidad: lote del lago, hasta los peces vivos y no antes de la siembra', () => {
    assert.deepEqual(validar('mortalidad', { lago_id: '1', siembra_id: '7', cantidad: '5', fecha: HOY }, paquete, HOY), {});
    assert.deepEqual(validar('mortalidad', { lago_id: '1', siembra_id: '7', cantidad: '951', fecha: HOY }, paquete, HOY).cantidad, ['campo_error_mas_que_vivos', { n: 950 }]);
    assert.equal(validar('mortalidad', { lago_id: '1', siembra_id: '7', cantidad: '2.5', fecha: HOY }, paquete, HOY).cantidad[0], 'campo_error_cantidad');
    assert.deepEqual(validar('mortalidad', { lago_id: '1', siembra_id: '7', cantidad: '1', fecha: '2026-08-31' }, paquete, HOY).fecha, ['campo_error_fecha_siembra', { fecha: '2026-09-01' }]);
    // La siembra 8 es de otro lago (y sin mortalidad en este paquete)
    assert.equal(validar('mortalidad', { lago_id: '1', siembra_id: '8', cantidad: '1', fecha: HOY }, paquete, HOY).siembra_id[0], 'campo_error_siembra');
});

test('aviso de pesca: no si el lago ya tiene uno pendiente', () => {
    assert.deepEqual(validar('aviso_pesca', { lago_id: '1', siembra_id: '7', nota: '' }, paquete, HOY), {});
    assert.equal(validar('aviso_pesca', { lago_id: '2', siembra_id: '8', nota: '' }, paquete, HOY).lago_id[0], 'campo_error_aviso_pendiente');
    assert.equal(validar('aviso_pesca', { lago_id: '1', siembra_id: '7', nota: 'x'.repeat(501) }, paquete, HOY).nota[0], 'campo_error_largo');
});

test('ronda: tipo válido, lago opcional y descripción si hubo novedad', () => {
    assert.deepEqual(validar('ronda', { tipo: 'sin_novedad', lago_id: '', descripcion: '' }, paquete, HOY), {});
    assert.equal(validar('ronda', { tipo: 'intrusion', lago_id: '', descripcion: '  ' }, paquete, HOY).descripcion[0], 'campo_error_descripcion');
    assert.equal(validar('ronda', { tipo: 'inventado', lago_id: '', descripcion: '' }, paquete, HOY).tipo[0], 'campo_error_tipo_ronda');
    assert.equal(validar('ronda', { tipo: 'sin_novedad', lago_id: '99', descripcion: '' }, paquete, HOY).lago_id[0], 'campo_error_lago');
});

test('medición: al menos un valor y cada uno en su rango', () => {
    assert.deepEqual(validar('medicion', { lago_id: '2', temperatura_c: '26,5', transparencia_cm: '' }, paquete, HOY), {});
    assert.equal(validar('medicion', { lago_id: '2', temperatura_c: '', transparencia_cm: '' }, paquete, HOY).medicion[0], 'campo_error_un_parametro');
    assert.deepEqual(validar('medicion', { lago_id: '2', temperatura_c: '46', transparencia_cm: '10.5' }, paquete, HOY), {
        temperatura_c: ['campo_error_rango', { min: 0, max: 45 }],
        transparencia_cm: ['campo_error_rango', { min: 0, max: 500 }],   // la transparencia es entera
    });
});

test('el registro armado lleva la dirección, los campos y el resumen', () => {
    assert.deepEqual(armarRegistro('racion', { lago_id: '1', tipo_concentrado: 'Engorde 32%', cantidad_kg: '4,5', fecha: HOY }, paquete, t), {
        url: '/admin/lagos/1/raciones',
        campos: [['fecha', HOY], ['tipo_concentrado', 'Engorde 32%'], ['cantidad_kg', '4.5']],
        detalle: [['campo_lago', 'L01'], ['campo_fecha', HOY], ['campo_concentrado', 'Engorde 32%'], ['campo_kg', '4.5']],
        resumen: 'campo_resumen_racion{"lago":"L01"}',
    });
    assert.equal(armarRegistro('mortalidad', { lago_id: '1', siembra_id: '7', cantidad: '5', fecha: HOY }, paquete, t).url, '/admin/siembras/7/mortalidades');
    assert.deepEqual(armarRegistro('aviso_pesca', { lago_id: '1', siembra_id: '7', nota: '' }, paquete, t).campos, [['siembra_id', '7'], ['nota', '']]);
    assert.deepEqual(armarRegistro('ronda', { tipo: 'sin_novedad', lago_id: '', descripcion: '' }, paquete, t).campos, [['tipo', 'sin_novedad'], ['lago_id', ''], ['descripcion', '']]);
    const medicion = armarRegistro('medicion', { lago_id: '2', temperatura_c: '26,5', transparencia_cm: '' }, paquete, t);
    assert.equal(medicion.url, '/noche/lagos/2/mediciones');
    assert.deepEqual(medicion.campos, [['temperatura_c', '26.5']]);   // solo lo escrito
});

test('antigüedad del paquete y texto «hace …»', () => {
    const ahora = new Date('2026-10-08T12:00:00Z');
    assert.deepEqual(antiguedad('2026-10-08T11:58:30Z', 12, ahora), { minutos: 1, horas: 0, vieja: false });
    assert.equal(antiguedad('2026-10-08T00:00:00Z', 12, ahora).vieja, true);
    assert.equal(antiguedad('2026-10-08T00:01:00Z', 12, ahora).vieja, false);
    assert.deepEqual(textoHace({ minutos: 0, horas: 0 }), ['campo_hace_instante', {}]);
    assert.deepEqual(textoHace({ minutos: 25, horas: 0 }), ['campo_hace_minutos', { n: 25 }]);
    assert.deepEqual(textoHace({ minutos: 130, horas: 2 }), ['campo_hace_horas', { n: 2 }]);
    assert.deepEqual(textoHace({ minutos: 3000, horas: 50 }), ['campo_hace_dias', { n: 2 }]);
});

test('aviso de turno: solo si la sección lo exige y no hay turno ahora', () => {
    const enElTurno = new Date('2026-10-08T12:00:00Z');
    const fuera = new Date('2026-10-08T20:00:00Z');
    assert.equal(enTurno(paquete.turnos, 'trabajador', enElTurno), true);
    assert.equal(avisarFueraDeTurno(paquete, 'racion', enElTurno), false);
    assert.equal(avisarFueraDeTurno(paquete, 'racion', fuera), true);
    assert.equal(avisarFueraDeTurno(paquete, 'ronda', enElTurno), true);   // no tiene turno de celador
    assert.equal(avisarFueraDeTurno({ ...paquete, exige_turno: { racion: false } }, 'racion', fuera), false);   // administrador
});

test('el resumen de errores sigue el orden de los campos en pantalla', () => {
    const errores = validar('racion', { lago_id: '', tipo_concentrado: '', cantidad_kg: '0', fecha: '2026-10-09' }, paquete, HOY);
    assert.deepEqual(resumenErrores(errores, ['lago_id', 'tipo_concentrado', 'cantidad_kg', 'fecha']).map(([campo]) => campo),
        ['lago_id', 'tipo_concentrado', 'cantidad_kg', 'fecha']);
    // Un error sin campo en pantalla (el general de la medición) va al final
    assert.deepEqual(resumenErrores({ medicion: ['x', {}], lago_id: ['y', {}] }, ['lago_id']).map(([campo]) => campo), ['lago_id', 'medicion']);
    assert.deepEqual(resumenErrores({}, ['lago_id']), []);
});
