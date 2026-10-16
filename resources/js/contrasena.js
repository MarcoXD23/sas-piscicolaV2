/*
 * Mostrar u ocultar la contraseña (botón de ojo de <x-contrasena>, resources/views/components/contrasena.blade.php).
 * - El botón (data-ver-contrasena, aria-controls = id del campo) cambia el campo entre password y text, con
 *   aria-pressed y aria-label («Mostrar» / «Ocultar», desde data-texto-*). No le quita el foco al campo y deja el
 *   cursor donde estaba.
 * - Vuelve a ocultarse sola al enviar el formulario, al salir de la pantalla (pagehide) y al volver con «atrás»
 *   (pageshow desde la caché del navegador).
 * Sin scripts en línea: todo va en el bundle. Se prueba con node --test (tests/js/contrasena.test.mjs).
 */

const BOTON = '[data-ver-contrasena]';

// Los íconos son SVG: en SVG la propiedad .hidden no existe, se cambia el atributo
const esconder = (nodo, si) => nodo?.toggleAttribute('hidden', si);

/** Campo que controla el botón. */
export const campoDe = (boton, documento = document) => documento.getElementById(boton.getAttribute('aria-controls'));

/** Muestra (visible = true) u oculta la contraseña y deja el botón diciendo qué hará al tocarlo. */
export const mostrar = (boton, campo, visible) => {
    if (!boton || !campo) return;
    campo.type = visible ? 'text' : 'password';
    boton.setAttribute('aria-pressed', visible ? 'true' : 'false');
    boton.setAttribute('aria-label', visible ? boton.dataset.textoOcultar : boton.dataset.textoMostrar);
    esconder(boton.querySelector('[data-icono-mostrar]'), visible);
    esconder(boton.querySelector('[data-icono-ocultar]'), !visible);
};

/** Cambia entre mostrar y ocultar. Devuelve si quedó visible. */
export const alternar = (boton, documento = document) => {
    const campo = campoDe(boton, documento);
    if (!campo) return false;
    const visible = campo.type === 'password';
    mostrar(boton, campo, visible);
    return visible;
};

/** Oculta todas las contraseñas dentro de raiz (un formulario o toda la página). */
export const ocultarTodas = (raiz, documento = document) => {
    raiz.querySelectorAll(BOTON).forEach((boton) => mostrar(boton, campoDe(boton, documento), false));
};

/**
 * Requisitos de una contraseña nueva que ya cumple el texto: los mismos de Password::defaults() en el servidor
 * (AppServiceProvider), que es el que decide al guardar.
 */
export const requisitos = (texto, minimo) => ({
    largo: [...texto].length >= minimo,
    mayuscula: /\p{Lu}/u.test(texto),
    minuscula: /\p{Ll}/u.test(texto),
    numero: /\p{N}/u.test(texto),
});

export const iniciarVerContrasena = (documento = document, ventana = window) => {
    // Contraseña nueva: marca en su lista (data-requisitos-de = id del campo) los requisitos que ya cumple
    documento.addEventListener('input', (evento) => {
        const campo = evento.target;
        const lista = campo?.id ? documento.querySelector(`[data-requisitos-de="${campo.id}"]`) : null;
        if (!lista) return;
        const cumple = requisitos(campo.value, Number(lista.dataset.minimo));
        lista.querySelectorAll('[data-requisito]').forEach((item) => item.toggleAttribute('data-cumple', Boolean(cumple[item.dataset.requisito])));
    });

    // El campo conserva el foco al tocar el botón (si no, el teclado del teléfono se cierra)
    documento.addEventListener('mousedown', (evento) => {
        if (evento.target.closest?.(BOTON)) evento.preventDefault();
    });

    documento.addEventListener('click', (evento) => {
        const boton = evento.target.closest?.(BOTON);
        if (!boton) return;
        const campo = campoDe(boton, documento);
        if (!campo) return;
        const [inicio, fin] = [campo.selectionStart, campo.selectionEnd];
        alternar(boton, documento);
        campo.focus({ preventScroll: true });
        try {
            if (inicio !== null && fin !== null) campo.setSelectionRange(inicio, fin);
        } catch {
            // Algunos tipos de campo no permiten selección: el foco basta
        }
    });

    // Al enviar (en captura, antes que cualquier otro manejador), al salir y al volver con «atrás»: oculta
    documento.addEventListener('submit', (evento) => ocultarTodas(evento.target, documento), true);
    ventana.addEventListener('pagehide', () => ocultarTodas(documento, documento));
    ventana.addEventListener('pageshow', () => ocultarTodas(documento, documento));
};
