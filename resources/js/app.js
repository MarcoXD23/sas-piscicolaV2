import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

// Ventanas emergentes (<x-modal>)
document.addEventListener('click', (evento) => {
    const abrir = evento.target.closest('[data-abrir-modal]');
    if (abrir) {
        evento.preventDefault();
        document.getElementById(abrir.dataset.abrirModal)?.showModal();
        return;
    }

    if (evento.target.closest('[data-cerrar-modal]')) {
        evento.target.closest('dialog')?.close();
        return;
    }

    // Clic en el fondo oscuro cierra la ventana
    if (evento.target instanceof HTMLDialogElement && evento.target.classList.contains('modal')) {
        evento.target.close();
    }
});

// Marca de qué ventana salió el formulario, para reabrirla si vuelve con errores
document.addEventListener('submit', (evento) => {
    const ventana = evento.target.closest('dialog.modal');
    if (!ventana || evento.target.querySelector('input[name="_modal"]')) return;

    const campo = document.createElement('input');
    campo.type = 'hidden';
    campo.name = '_modal';
    campo.value = ventana.id;
    evento.target.appendChild(campo);
});

document.querySelectorAll('dialog[data-abrir-al-cargar]').forEach((ventana) => ventana.showModal());

// Tooltip de las gráficas: cualquier elemento con data-tip muestra su texto al pasar el puntero o al enfocarlo
const tooltip = document.createElement('div');
tooltip.className = 'tooltip-grafica';
tooltip.setAttribute('role', 'tooltip');
tooltip.hidden = true;
document.body.appendChild(tooltip);

const mostrarTooltip = (elemento, x, y) => {
    tooltip.textContent = elemento.dataset.tip; // textContent: los datos nunca se interpretan como HTML
    tooltip.hidden = false;
    const ancho = tooltip.offsetWidth;
    tooltip.style.left = `${Math.min(Math.max(8, x - ancho / 2), window.innerWidth - ancho - 8)}px`;
    tooltip.style.top = `${Math.max(8, y - tooltip.offsetHeight - 12)}px`;
};

document.addEventListener('pointermove', (evento) => {
    const elemento = evento.target.closest?.('[data-tip]');
    if (elemento) mostrarTooltip(elemento, evento.clientX, evento.clientY);
    else tooltip.hidden = true;
});

document.addEventListener('focusin', (evento) => {
    const elemento = evento.target.closest?.('[data-tip]');
    if (!elemento) return;
    const caja = elemento.getBoundingClientRect();
    mostrarTooltip(elemento, caja.left + caja.width / 2, caja.top);
});

document.addEventListener('focusout', () => { tooltip.hidden = true; });
document.addEventListener('scroll', () => { tooltip.hidden = true; }, { passive: true });

// Asistente IA (<x-asistente>): un solo widget en toda la aplicación
const asistente = document.querySelector('[data-asistente]');
if (asistente) {
    const lista = asistente.querySelector('[data-asistente-mensajes]');
    const vacio = asistente.querySelector('[data-asistente-vacio]');
    const formulario = asistente.querySelector('[data-asistente-formulario]');
    const campo = formulario.querySelector('textarea');
    const boton = formulario.querySelector('button[type="submit"]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let cargado = false;

    const agregar = (rol, texto, clase = '') => {
        vacio.hidden = true;
        const burbuja = document.createElement('div');
        burbuja.className = rol === 'user'
            ? 'ml-8 rounded-2xl rounded-br-sm bg-marca-600 px-4 py-2.5 text-base text-white whitespace-pre-line'
            : `mr-8 rounded-2xl rounded-bl-sm bg-slate-100 px-4 py-2.5 text-base text-slate-800 whitespace-pre-line ${clase}`;
        burbuja.textContent = texto; // textContent: la respuesta nunca se interpreta como HTML
        lista.appendChild(burbuja);
        lista.scrollTop = lista.scrollHeight;
        return burbuja;
    };

    const cargar = async () => {
        if (cargado) return;
        cargado = true;
        const respuesta = await fetch(asistente.dataset.historial, { headers: { Accept: 'application/json' } });
        if (!respuesta.ok) return;
        const datos = await respuesta.json();
        asistente.querySelector('[data-asistente-no-configurado]').hidden = datos.configurado;
        formulario.hidden = !datos.configurado;
        datos.mensajes.forEach((mensaje) => agregar(mensaje.role, mensaje.content));
    };

    const enviar = async (texto) => {
        texto = texto.trim();
        if (!texto || boton.disabled) return;
        agregar('user', texto);
        campo.value = '';
        boton.disabled = true;
        const pensando = agregar('assistant', 'Pensando…', 'italic text-slate-500');

        try {
            const respuesta = await fetch(asistente.dataset.url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ mensaje: texto }),
            });
            const datos = await respuesta.json().catch(() => ({}));
            pensando.remove();
            if (respuesta.ok) {
                agregar('assistant', datos.respuesta);
            } else {
                const error = datos.error
                    ?? datos.errors?.mensaje?.[0]
                    ?? (respuesta.status === 429 ? 'Hiciste muchas preguntas seguidas. Espera un minuto.' : 'No se pudo enviar la pregunta.');
                agregar('assistant', error, 'bg-red-50 text-red-800');
            }
        } catch {
            pensando.remove();
            agregar('assistant', 'Sin conexión. Intenta de nuevo.', 'bg-red-50 text-red-800');
        } finally {
            boton.disabled = false;
            campo.focus();
        }
    };

    document.querySelectorAll('[data-abrir-modal="asistente"]').forEach((elemento) => elemento.addEventListener('click', cargar));
    formulario.addEventListener('submit', (evento) => { evento.preventDefault(); enviar(campo.value); });
    campo.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter' && !evento.shiftKey) { evento.preventDefault(); enviar(campo.value); }
    });
    asistente.querySelectorAll('[data-asistente-sugerencia]').forEach((sugerencia) =>
        sugerencia.addEventListener('click', () => enviar(sugerencia.textContent)));
    asistente.querySelector('[data-asistente-reiniciar]').addEventListener('click', async () => {
        await fetch(asistente.dataset.historial, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' } });
        lista.querySelectorAll(':scope > :not([data-asistente-vacio])').forEach((burbuja) => burbuja.remove());
        vacio.hidden = false;
    });
}

// Aplicación instalable: service worker para archivos estáticos y aviso sin conexión (public/sw.js)
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
