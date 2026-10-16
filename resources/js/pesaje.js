/*
 * Pantalla del pesaje de la cosecha (Cosechas › Registrar), en JS sin Alpine: la Content-Security-Policy no permite
 * evaluar código escrito en el HTML. Las cuentas están en pesaje-logica.js (probadas con node --test).
 * - Las pesadas se arman desde <template data-plantilla-pesada>; los nombres pesadas[i][...] se renumeran al quitar.
 * - Modo «pesadas» o «total»: se muestra una zona y la otra queda oculta y deshabilitada (no se envía).
 * - Al enviar con el modo de pesadas, una fila incompleta no deja enviar y se avisa cuál es.
 */
import { avance, descuentoC, enteroC, estadoModo, filaNueva, kg, linea, metaC, metaTexto, totales, validar } from './pesaje-logica';

const t = (clave, datos = {}) => (window.textos?.[clave] ?? clave).replace(/:(\w+)/g, (marca, nombre) => datos[nombre] ?? marca);

export const iniciarPesaje = (formulario) => {
    const config = JSON.parse(formulario.querySelector('[data-pesaje-config]')?.textContent || '{}');
    const separadores = { decimal: config.decimal ?? ',', miles: config.miles ?? '.' };
    const $ = (selector) => formulario.querySelector(selector);
    const lista = $('[data-pesadas]');
    const plantilla = $('[data-plantilla-pesada]');
    if (!lista || !plantilla) return;

    const zonaTotal = $('[data-zona="total"]');
    const zonaPesadas = $('[data-zona="pesadas"]');
    const campoKg = zonaTotal?.querySelector('input[name="kg"]');
    const vacia = { canasta: $('input[name="peso_canasta_kg"]'), canastilla: $('input[name="peso_canastilla_kg"]') };
    const meta = $('[data-meta]');
    const metaZona = $('[data-meta-zona]');
    const metaBarra = $('[data-meta-avance]');
    const metaTextos = $('[data-meta-textos]');
    const aviso = $('[data-aviso-pesaje]');
    const total = (cual) => $(`[data-total="${cual}"]`);
    let modo = config.modo === 'total' ? 'total' : 'pesadas';

    const vacias = () => ({ canasta: vacia.canasta?.value ?? '', canastilla: vacia.canastilla?.value ?? '' });
    const campo = (li, nombre) => li.querySelector(`[data-pesada="${nombre}"]`);
    const filas = () => [...lista.children].map((li) => ({ tipo: campo(li, 'tipo').value, cantidad: campo(li, 'cantidad').value, peso: campo(li, 'peso').value }));
    const enfocar = (indice) => lista.children[indice] && campo(lista.children[indice], 'cantidad').focus();

    // Nombres pesadas[i][...], número visible y etiqueta del botón de quitar, según la posición de cada pesada
    const numerar = () => {
        [...lista.children].forEach((li, i) => {
            campo(li, 'tipo').name = `pesadas[${i}][tipo]`;
            campo(li, 'cantidad').name = `pesadas[${i}][cantidad]`;
            campo(li, 'peso').name = `pesadas[${i}][peso_pesado]`;
            li.querySelector('[data-numero]').textContent = String(i + 1);
            const quitar = li.querySelector('[data-quitar]');
            quitar.setAttribute('aria-label', quitar.dataset.texto.replace('__N__', String(i + 1)));
        });
    };

    // Descuento y entero de cada fila, totales, línea del cuaderno y meta
    const actualizar = () => {
        const v = vacias();
        [...lista.children].forEach((li, i) => {
            const fila = filas()[i];
            li.querySelector('[data-descuento]').textContent = kg(descuentoC(fila, v), separadores);
            li.querySelector('[data-entero]').textContent = kg(enteroC(fila, v), separadores);
        });

        const suma = totales(filas(), v);
        total('pesadas').textContent = String(suma.pesadas);
        total('cantidad').textContent = total('cantidad').dataset.texto.replace(':canastas', suma.porTipo.canasta).replace(':canastillas', suma.porTipo.canastilla);
        total('pesado').textContent = kg(suma.pesado, separadores);
        total('descuento').textContent = kg(suma.descuento, separadores);
        total('entero').textContent = kg(suma.entero, separadores);
        const texto = linea(suma, t, separadores);
        $('[data-linea]').textContent = texto;
        $('[data-linea]').hidden = texto === '';

        const metaKg = metaC(meta?.value);
        if (metaZona) metaZona.hidden = metaKg === null;
        if (metaBarra) metaBarra.value = avance(suma.entero, metaKg);
        if (metaTextos) {
            metaTextos.replaceChildren(...metaTexto(suma, metaKg, t, separadores).map((frase) => {
                const p = document.createElement('p');
                p.className = 'text-base font-semibold';
                p.textContent = frase;
                return p;
            }));
        }
    };

    const agregar = (fila = filaNueva(filas().at(-1)), conFoco = true) => {
        const li = plantilla.content.firstElementChild.cloneNode(true);
        campo(li, 'tipo').value = fila.tipo;
        campo(li, 'cantidad').value = fila.cantidad;
        campo(li, 'peso').value = fila.peso;
        campo(li, 'tipo').disabled = campo(li, 'cantidad').disabled = campo(li, 'peso').disabled = !estadoModo(modo).pesadas.habilitado;
        lista.append(li);
        numerar();
        actualizar();
        if (conFoco) campo(li, 'cantidad').focus();
    };

    const quitar = (li) => {
        if (lista.children.length > 1) {
            li.remove();
        } else {
            campo(li, 'cantidad').value = '';
            campo(li, 'peso').value = '';
        }
        numerar();
        actualizar();
    };

    // Una zona visible y la otra oculta y deshabilitada: lo deshabilitado no se envía
    const aplicarModo = () => {
        const estado = estadoModo(modo);
        if (zonaTotal) zonaTotal.hidden = !estado.total.visible;
        if (zonaPesadas) zonaPesadas.hidden = !estado.pesadas.visible;
        if (campoKg) {
            campoKg.disabled = !estado.total.habilitado;
            campoKg.required = estado.total.requerido;
        }
        zonaPesadas?.querySelectorAll('input[name], select[name], [data-pesada]').forEach((elemento) => { elemento.disabled = !estado.pesadas.habilitado; });
        formulario.querySelectorAll('[data-modo]').forEach((radio) => { radio.checked = radio.value === modo; });
    };

    (config.filas?.length > 0 ? config.filas : [filaNueva()]).forEach((fila) => agregar(fila, false));

    formulario.querySelectorAll('[data-modo]').forEach((radio) => radio.addEventListener('change', () => {
        if (!radio.checked) return;
        modo = radio.value;
        aplicarModo();
    }));
    lista.addEventListener('input', actualizar);
    lista.addEventListener('change', actualizar);
    lista.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-quitar]');
        if (boton) quitar(boton.closest('li'));
    });
    // Enter en el peso pesado: pasa a la siguiente pesada, o la añade si es la última
    lista.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Enter' || evento.target.dataset.pesada !== 'peso') return;
        evento.preventDefault();
        const indice = [...lista.children].indexOf(evento.target.closest('li'));
        if (indice === lista.children.length - 1) agregar();
        else enfocar(indice + 1);
    });
    $('[data-agregar-pesada]')?.addEventListener('click', () => agregar());
    Object.values(vacia).forEach((entrada) => entrada?.addEventListener('input', actualizar));
    meta?.addEventListener('input', actualizar);

    // No se envía con una fila incompleta: avisa cuál es (el servidor valida todo de nuevo)
    formulario.addEventListener('submit', (evento) => {
        const { aviso: texto, indice } = validar(modo, filas(), vacias(), t);
        if (aviso) {
            aviso.textContent = texto;
            aviso.hidden = texto === '';
        }
        if (texto) {
            evento.preventDefault();
            evento.stopImmediatePropagation();
            if (indice !== null) enfocar(indice);
        }
    });

    aplicarModo();
    actualizar();
};
