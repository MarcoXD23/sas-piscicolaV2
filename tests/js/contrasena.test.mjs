// Mostrar u ocultar la contraseña (resources/js/contrasena.js). Correr con: npm run test:js (node --test, sin paquetes)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { alternar, iniciarVerContrasena, mostrar, ocultarTodas } from '../../resources/js/contrasena.js';

// DOM mínimo: lo justo que usa el módulo
const elemento = (atributos = {}, dataset = {}) => {
    const attrs = { ...atributos };
    const hijos = [];
    return {
        attrs, dataset, hijos, hidden: false,
        getAttribute: (n) => attrs[n] ?? null,
        setAttribute: (n, v) => { attrs[n] = String(v); },
        querySelector: (sel) => hijos.find((h) => h.selector === sel) ?? null,
        querySelectorAll: () => [],
        closest: () => null,
    };
};

const armar = () => {
    const campo = { type: 'password', selectionStart: 3, selectionEnd: 3, enfocado: false, rango: null,
        focus() { this.enfocado = true; }, setSelectionRange(a, b) { this.rango = [a, b]; } };
    const boton = elemento({ 'aria-controls': 'clave', 'aria-pressed': 'false', 'aria-label': 'Mostrar contraseña' },
        { textoMostrar: 'Mostrar contraseña', textoOcultar: 'Ocultar contraseña' });
    // Íconos SVG: solo el atributo hidden los oculta (no hay propiedad .hidden)
    const icono = (selector, oculto) => ({ selector, oculto, toggleAttribute(nombre, si) { if (nombre === 'hidden') this.oculto = si; return si; } });
    const ojo = icono('[data-icono-mostrar]', false);
    const tachado = icono('[data-icono-ocultar]', true);
    boton.hijos.push(ojo, tachado);
    boton.closest = (sel) => (sel === '[data-ver-contrasena]' ? boton : null);
    const manejadores = {};
    const documento = {
        getElementById: (id) => (id === 'clave' ? campo : null),
        querySelectorAll: (sel) => (sel === '[data-ver-contrasena]' ? [boton] : []),
        addEventListener: (tipo, fn) => { (manejadores[tipo] ??= []).push(fn); },
    };
    const ventana = { addEventListener: (tipo, fn) => { (manejadores[`ventana:${tipo}`] ??= []).push(fn); } };
    const disparar = (tipo, evento = {}) => (manejadores[tipo] ?? []).forEach((fn) => fn(evento));
    return { campo, boton, ojo, tachado, documento, ventana, disparar };
};

test('mostrar y ocultar cambian el tipo, aria-pressed, aria-label y el ícono', () => {
    const { campo, boton, ojo, tachado, documento } = armar();

    assert.equal(alternar(boton, documento), true);
    assert.equal(campo.type, 'text');
    assert.equal(boton.getAttribute('aria-pressed'), 'true');
    assert.equal(boton.getAttribute('aria-label'), 'Ocultar contraseña');
    assert.deepEqual([ojo.oculto, tachado.oculto], [true, false]);

    assert.equal(alternar(boton, documento), false);
    assert.equal(campo.type, 'password');
    assert.equal(boton.getAttribute('aria-pressed'), 'false');
    assert.equal(boton.getAttribute('aria-label'), 'Mostrar contraseña');
    assert.deepEqual([ojo.oculto, tachado.oculto], [false, true]);
});

test('al tocar el botón el campo conserva el foco y el cursor', () => {
    const { campo, boton, documento, ventana, disparar } = armar();
    iniciarVerContrasena(documento, ventana);

    let prevenido = false;
    disparar('mousedown', { target: boton, preventDefault: () => { prevenido = true; } });
    assert.equal(prevenido, true);   // el toque no le quita el foco al campo
    disparar('click', { target: boton });
    assert.equal(campo.type, 'text');
    assert.equal(campo.enfocado, true);
    assert.deepEqual(campo.rango, [3, 3]);
});

test('vuelve a ocultarse al enviar, al salir y al volver con «atrás»', () => {
    for (const salida of ['submit', 'ventana:pagehide', 'ventana:pageshow']) {
        const { campo, boton, documento, ventana, disparar } = armar();
        iniciarVerContrasena(documento, ventana);
        mostrar(boton, campo, true);
        assert.equal(campo.type, 'text', salida);

        disparar(salida, { target: documento });
        assert.equal(campo.type, 'password', salida);
        assert.equal(boton.getAttribute('aria-pressed'), 'false', salida);
        assert.equal(boton.getAttribute('aria-label'), 'Mostrar contraseña', salida);
    }
});

test('un clic fuera del botón o sin campo no hace nada', () => {
    const { campo, documento, ventana, disparar } = armar();
    iniciarVerContrasena(documento, ventana);
    disparar('click', { target: { closest: () => null } });
    assert.equal(campo.type, 'password');
    // Botón que apunta a un campo que no existe
    const huerfano = elemento({ 'aria-controls': 'no-existe' });
    assert.equal(alternar(huerfano, documento), false);
    assert.doesNotThrow(() => ocultarTodas({ querySelectorAll: () => [huerfano] }, documento));
});
