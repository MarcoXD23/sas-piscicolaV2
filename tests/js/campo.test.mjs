// Reglas de la pantalla Registro rápido. Correr con: npm run test:js (node --test, sin paquetes)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { antiguedad, armarRegistro, avisarFueraDeTurno, enTurno, numero, promedioMuestreo, resumenErrores, textoHace, validar } from '../../resources/js/campo-logica.js';

const HOY = '2026-10-08';
const t = (clave, datos = {}) => `${clave}${Object.keys(datos).length ? JSON.stringify(datos) : ''}`;

const paquete = {
    clave: '1|2',
    secciones: ['racion', 'mortalidad', 'aviso_pesca', 'ronda', 'medicion'],
    lagos: [
        { id: 1, nombre: 'L01', url_racion: '/admin/lagos/1/raciones', url_aviso_pesca: '/admin/lagos/1/avisos-pesca', url_medicion: '/noche/lagos/1/mediciones', aviso_pendiente: false, racion_sugerida_kg: 4.5,
            siembras: [{ id: 7, especie: 'Cachama blanca', fecha: '2026-09-01', peces_vivos: 950, url_mortalidad: '/admin/siembras/7/mortalidades', peso_actual_g: 50, url_muestreo: '/admin/siembras/7/muestreos' }] },
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
    muestreo: { peces_pesados: { min: 1, max: 100000 }, peso_total_g: { min: 0.1, max: 999999 },
        unidades: ['g', 'kg', 'lb'], factores: { g: [100, 1], kg: [100000, 1], lb: [45359237, 1000] }, unidad_defecto: 'g' },
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

test('muestreo: mismos límites que el servidor, lote del lago y no antes de la siembra', () => {
    const bien = { lago_id: '1', siembra_id: '7', peces_pesados: '20', peso_total: '1250,5', unidad: 'g', fecha: HOY };
    assert.deepEqual(validar('muestreo', bien, paquete, HOY), {});
    // Límites: peces enteros de 1 a 100.000; peso de 0,1 a 999.999 g
    assert.deepEqual(claves(validar('muestreo', { ...bien, peces_pesados: '0', peso_total: '0,09' }, paquete, HOY)),
        { peces_pesados: 'campo_error_entero_rango', peso_total: 'campo_error_rango' });
    assert.deepEqual(validar('muestreo', { ...bien, peces_pesados: '100001' }, paquete, HOY).peces_pesados, ['campo_error_entero_rango', { min: 1, max: 100000 }]);
    assert.equal(validar('muestreo', { ...bien, peces_pesados: '2.5' }, paquete, HOY).peces_pesados[0], 'campo_error_entero_rango');
    assert.deepEqual(validar('muestreo', { ...bien, peces_pesados: '100000', peso_total: '0,1' }, paquete, HOY), {});
    assert.deepEqual(validar('muestreo', { ...bien, peso_total: '999999' }, paquete, HOY), {});
    // Como el servidor: los peces pesados pueden ser más que los vivos del paquete
    assert.deepEqual(validar('muestreo', { ...bien, peces_pesados: '5000' }, paquete, HOY), {});
    assert.equal(validar('muestreo', { ...bien, fecha: '2026-10-09' }, paquete, HOY).fecha[0], 'campo_error_fecha_futura');
    assert.equal(validar('muestreo', { ...bien, fecha: '2026-08-31' }, paquete, HOY).fecha[0], 'campo_error_fecha_siembra');
    // La siembra 8 no tiene muestreo en este paquete
    assert.equal(validar('muestreo', { ...bien, lago_id: '2', siembra_id: '8' }, paquete, HOY).siembra_id[0], 'campo_error_siembra');
});

test('muestreo: primero un número válido, después convertir y el rango en gramos, en cada unidad', () => {
    const bien = { lago_id: '1', siembra_id: '7', peces_pesados: '10', fecha: HOY };
    for (const valor of ['abc', '1e3', '-1', '1.2345', '1234567', 'Infinity', '']) {
        assert.equal(validar('muestreo', { ...bien, peso_total: valor, unidad: 'kg' }, paquete, HOY).peso_total[0], 'campo_error_peso_numero', valor);
    }
    assert.deepEqual(validar('muestreo', { ...bien, peso_total: '999.999', unidad: 'kg' }, paquete, HOY), {});
    assert.deepEqual(validar('muestreo', { ...bien, peso_total: '1000', unidad: 'kg' }, paquete, HOY).peso_total,
        ['campo_error_rango_peso', { min: 0.1, max: 999999, maximo: 999.999, unidad: 'kg' }]);
    assert.deepEqual(validar('muestreo', { ...bien, peso_total: '2204.620', unidad: 'lb' }, paquete, HOY), {});
    assert.deepEqual(validar('muestreo', { ...bien, peso_total: '2204.622', unidad: 'lb' }, paquete, HOY).peso_total,
        ['campo_error_rango_peso', { min: 0.1, max: 999999, maximo: 2204.62, unidad: 'lb' }]);
    assert.deepEqual(validar('muestreo', { ...bien, peso_total: '0,5', unidad: 'lb' }, paquete, HOY), {});
    assert.equal(validar('muestreo', { ...bien, peso_total: '1', unidad: 'onzas' }, paquete, HOY).unidad[0], 'campo_error_unidad');
});

test('muestreo: promedio por pez con enteros, como el servidor', () => {
    assert.equal(promedioMuestreo('20', '1250,5'), 6253);   // 62,525 g → 62,53 g (centésimas)
    assert.equal(promedioMuestreo('3', '100'), 3333);
    assert.equal(promedioMuestreo('1', '1', 'lb', paquete.muestreo.factores), 45359);
    assert.equal(promedioMuestreo('2', '0,5', 'lb', paquete.muestreo.factores), 11340);
    assert.equal(promedioMuestreo('', '100'), null);
    assert.equal(promedioMuestreo('0', '100'), null);
    assert.equal(promedioMuestreo('2,5', '100'), null);
});

test('muestreo: el registro lleva el valor escrito y la unidad (el servidor convierte)', () => {
    const registro = armarRegistro('muestreo', { lago_id: '1', siembra_id: '7', peces_pesados: '20', peso_total: '2,5', unidad: 'lb', fecha: HOY }, paquete, t);
    assert.equal(registro.url, '/admin/siembras/7/muestreos');
    assert.deepEqual(registro.campos, [['fecha', HOY], ['peces_pesados', '20'], ['peso_total', '2,5'], ['unidad', 'lb']]);
    assert.deepEqual(registro.detalle.at(-2), ['campo_peso_total', '2,5 lb']);
    assert.match(registro.detalle.at(-1)[1], /^56[.,]70 g \(0[.,]125 lb\)$/);   // 1.133,98 g ÷ 20
    assert.equal(registro.resumen, 'campo_resumen_muestreo{"especie":"Cachama blanca","lago":"L01"}');
});

test('muestreo con un paquete viejo (sin unidades ni factores): sigue en gramos', () => {
    const viejo = { ...paquete, muestreo: { peces_pesados: { min: 1, max: 100000 }, peso_total_g: { min: 0.1, max: 999999 } } };
    const datos = { lago_id: '1', siembra_id: '7', peces_pesados: '20', peso_total: '1250,5', fecha: HOY };
    assert.deepEqual(validar('muestreo', datos, viejo, HOY), {});
    assert.deepEqual(armarRegistro('muestreo', datos, viejo, t).campos, [['fecha', HOY], ['peces_pesados', '20'], ['peso_total', '1250,5'], ['unidad', 'g']]);
    // En libras no se puede: el paquete viejo no trae el factor
    assert.equal(validar('muestreo', { ...datos, unidad: 'lb' }, viejo, HOY).unidad[0], 'campo_error_unidad');
});

test('lotes para elegir: agrupados por especie cuando hay varios, como antes si hay uno o el paquete es viejo', async () => {
    const { opcionesLotes } = await import('../../resources/js/campo-logica.js');
    const formatear = { fecha: (f) => f, numero: (n) => String(n) };
    const siembras = [
        { id: 5, especie: 'Bagre rayado', fecha: '2026-10-07', peces_vivos: 5000, lote: 1, origen: 'Sembrado el 07/10/2026' },
        { id: 8, especie: 'Bagre rayado', fecha: '2026-10-11', peces_vivos: 300, lote: 2, origen: 'Creado por traslado desde L02 el 11/10/2026' },
        { id: 9, especie: 'Trucha arcoíris', fecha: '2026-10-01', peces_vivos: 400 },
    ];
    assert.deepEqual(opcionesLotes(siembras, t, formatear), [
        { grupo: 'Bagre rayado', opciones: [
            [5, 'campo_opcion_lote_numero{"numero":1,"origen":"Sembrado el 07/10/2026","vivos":"5000"}'],
            [8, 'campo_opcion_lote_numero{"numero":2,"origen":"Creado por traslado desde L02 el 11/10/2026","vivos":"300"}'],
        ] },
        { grupo: null, opciones: [[9, 'campo_opcion_lote{"especie":"Trucha arcoíris","fecha":"2026-10-01","vivos":"400"}']] },
    ]);
    // Paquete viejo (sin lote ni origen): todo como antes, sin grupos
    const viejo = siembras.map(({ lote, origen, ...resto }) => resto);
    assert.ok(opcionesLotes(viejo, t, formatear).every((grupo) => grupo.grupo === null));
});
