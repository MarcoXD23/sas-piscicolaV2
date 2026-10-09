import Alpine from 'alpinejs';
import pesaje from './pesaje';
import { iniciarCampo } from './campo';
import { iniciarPaquete } from './paquete';
import { iniciarCola } from './sin-conexion';

window.Alpine = Alpine;
Alpine.data('pesaje', pesaje);   // tabla de pesadas de la cosecha
Alpine.start();

// Textos de la interfaz (lang/es/js.php, publicados por el layout en window.textos). Reemplaza :marcadores.
const t = (clave, datos = {}) => (window.textos?.[clave] ?? clave).replace(/:(\w+)/g, (marca, nombre) => datos[nombre] ?? marca);

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

// Muestreo: peso promedio en vivo (peso total ÷ peces pesados). Solo informativo: lo que se guarda lo calcula el servidor.
document.querySelectorAll('[data-muestreo]').forEach((muestreo) => {
    const peces = muestreo.querySelector('[data-peces]');
    const pesoTotal = muestreo.querySelector('[data-peso-total]');
    const promedio = muestreo.querySelector('[data-promedio]');
    const leer = (campo) => Number.parseFloat(String(campo.value).replace(',', '.'));

    const calcular = () => {
        const cantidad = leer(peces);
        const total = leer(pesoTotal);
        // Sin datos, con cero peces o valores no válidos no se divide
        if (!Number.isFinite(cantidad) || !Number.isFinite(total) || cantidad <= 0 || total <= 0) {
            promedio.textContent = '—';
            return;
        }
        const valor = (total / cantidad).toLocaleString(document.documentElement.lang, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        promedio.textContent = t('gramos', { valor });
    };

    [peces, pesoTotal].forEach((campo) => campo.addEventListener('input', calcular));
    calcular();
});

// Sin conexión: franja de aviso (la página puede ser una copia guardada por el service worker; solo consulta)
const franjaSinConexion = document.querySelector('[data-sin-conexion]');
const mostrarConexion = () => { if (franjaSinConexion) franjaSinConexion.hidden = navigator.onLine; };
window.addEventListener('online', mostrarConexion);
window.addEventListener('offline', mostrarConexion);
mostrarConexion();

// Registros sin conexión (resources/js/sin-conexion.js): los formularios con data-guardar-sin-conexion se guardan en el
// teléfono y se envían al volver la señal. Los demás no se envían sin señal: lo escrito queda en el formulario.
const cola = iniciarCola(t);
// Paquete de trabajo para Registro rápido (/campo): se descarga solo en segundo plano (resources/js/paquete.js)
const paquete = iniciarPaquete();
// Pantalla Registro rápido (/campo): solo si la página la trae (resources/js/campo.js)
iniciarCampo(t, cola);

const hayConexion = async () => {
    if (!navigator.onLine) return false;
    try {
        const respuesta = await fetch('/up', { method: 'HEAD', cache: 'no-store', signal: AbortSignal.timeout(4000) });
        return respuesta.ok;
    } catch {
        return false;
    }
};

const avisarSinConexion = (formulario) => {
    let aviso = formulario.querySelector('[data-aviso-conexion]');
    if (!aviso) {
        aviso = document.createElement('p');
        aviso.setAttribute('data-aviso-conexion', '');
        aviso.setAttribute('role', 'alert');
        aviso.className = 'mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-base font-medium text-amber-900';
        formulario.prepend(aviso);
    }
    aviso.textContent = t('sin_conexion_guardar');
    aviso.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

document.addEventListener('submit', async (evento) => {
    const formulario = evento.target;
    // Los formularios de consulta (GET) y los que ya manejan su envío (asistente) no se tocan
    if (evento.defaultPrevented || (formulario.getAttribute('method') ?? 'get').toLowerCase() !== 'post') return;
    if (formulario.dataset.conexionVerificada) {
        delete formulario.dataset.conexionVerificada;
        return;
    }

    evento.preventDefault();

    // Cerrar sesión con registros sin enviar: se avisa antes (al salir se borran de este teléfono)
    if (formulario.action.endsWith('/logout') && !formulario.dataset.salirConfirmado && await cola.total() > 0) {
        const ventana = document.getElementById('salir-con-pendientes');
        if (ventana) {
            ventana.querySelector('[data-salir-total]').textContent = String(await cola.total());
            ventana.querySelector('[data-salir-borrar]').onclick = async () => {
                await cola.borrarTodo();
                formulario.dataset.salirConfirmado = '1';
                ventana.close();
                formulario.requestSubmit();
            };
            ventana.showModal();
            return;
        }
    }

    const boton = evento.submitter;
    if (boton) boton.disabled = true;
    const conectado = await hayConexion();
    if (boton) boton.disabled = false;

    if (!conectado) {
        if (formulario.hasAttribute('data-guardar-sin-conexion') && await cola.guardar(formulario)) return;
        avisarSinConexion(formulario);
        return;
    }

    formulario.querySelector('[data-aviso-conexion]')?.remove();
    // Al cerrar sesión se borran las pantallas guardadas, el paquete de trabajo y la sesión activa de este teléfono
    if (formulario.action.endsWith('/logout')) {
        navigator.serviceWorker?.controller?.postMessage({ tipo: 'limpiar' });
        await paquete.borrar();
    }
    formulario.dataset.conexionVerificada = '1';
    formulario.requestSubmit(boton ?? undefined);
});

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
        const pensando = agregar('assistant', t('pensando'), 'italic text-slate-500');

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
                    ?? t(respuesta.status === 429 ? 'muchas_preguntas' : 'no_se_pudo_enviar');
                agregar('assistant', error, 'bg-red-50 text-red-800');
            }
        } catch {
            pensando.remove();
            agregar('assistant', t('sin_conexion'), 'bg-red-50 text-red-800');
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


// Animaciones de la interfaz (estilos en resources/css/app.css). Quien pidió menos movimiento en su teléfono no las ve.
const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const conObservador = !sinMovimiento && 'IntersectionObserver' in window;

// Aparecer al bajar (data-revelar): lo que está fuera de la pantalla al cargar entra cuando se ve.
// Lo que ya está a la vista no se toca: lo anima la entrada de la página.
if (conObservador) {
    const observadorRevelar = new IntersectionObserver((entradas) => {
        entradas.filter((entrada) => entrada.isIntersecting).forEach((entrada, indice) => {
            entrada.target.style.animationDelay = `${Math.min(indice, 6) * 70}ms`;
            entrada.target.classList.replace('por-revelar', 'revelado');
            observadorRevelar.unobserve(entrada.target);
        });
    }, { rootMargin: '0px 0px -6% 0px' });

    document.querySelectorAll('[data-revelar]').forEach((elemento) => {
        if (elemento.getBoundingClientRect().top < window.innerHeight) return;
        elemento.classList.add('por-revelar');
        observadorRevelar.observe(elemento);
    });
}

// Números que cuentan hacia arriba (data-contar): el texto ya viene formateado por el servidor en el idioma de la finca.
// Se lee con los separadores de ese idioma; si el número es ambiguo no se anima. Al terminar queda el texto original.
const separadores = Object.fromEntries(new Intl.NumberFormat(document.documentElement.lang || undefined)
    .formatToParts(12345.6).filter((parte) => parte.type === 'group' || parte.type === 'decimal')
    .map((parte) => [parte.type, parte.value]));

const contarNumero = (elemento) => {
    const final = elemento.textContent;
    const encontrado = final.match(/-?\d[\d.,   ]*\d|\d/);
    if (elemento.children.length || !encontrado) return;

    const [entero, decimales = ''] = encontrado[0].trim().split(separadores.decimal ?? '.');
    const grupo = separadores.group ?? ',';
    const sinSigno = entero.replace(/^-/, '');
    const conGrupos = sinSigno.includes(grupo);
    const enteroValido = conGrupos
        ? sinSigno.split(grupo).every((bloque, i) => (i === 0 ? /^\d{1,3}$/ : /^\d{3}$/).test(bloque))
        : /^\d+$/.test(sinSigno);
    if (!enteroValido || !/^\d*$/.test(decimales)) return;

    const objetivo = Number(`${entero.split(grupo).join('')}.${decimales || '0'}`);
    if (!Number.isFinite(objetivo) || objetivo === 0) return;

    const formato = new Intl.NumberFormat(document.documentElement.lang || undefined, {
        minimumFractionDigits: decimales.length,
        maximumFractionDigits: decimales.length,
        useGrouping: conGrupos,
    });
    const antes = final.slice(0, encontrado.index);
    const despues = final.slice(encontrado.index + encontrado[0].trimEnd().length);
    const duracion = 900;
    let inicio;

    const paso = (momento) => {
        inicio ??= momento;
        const avance = Math.min((momento - inicio) / duracion, 1);
        if (avance >= 1) {
            elemento.textContent = final;
            return;
        }
        const suavizado = 1 - (1 - avance) ** 3;
        elemento.textContent = antes + formato.format(objetivo * suavizado) + despues;
        requestAnimationFrame(paso);
    };
    requestAnimationFrame(paso);
};

if (conObservador) {
    const observadorContar = new IntersectionObserver((entradas) => {
        entradas.filter((entrada) => entrada.isIntersecting).forEach((entrada) => {
            observadorContar.unobserve(entrada.target);
            contarNumero(entrada.target);
        });
    }, { threshold: 0.4 });
    document.querySelectorAll('[data-contar]').forEach((elemento) => observadorContar.observe(elemento));
}

// Onda al tocar (data-onda): un círculo que se expande desde el dedo
document.addEventListener('pointerdown', (evento) => {
    if (sinMovimiento) return;
    const elemento = evento.target.closest?.('[data-onda]');
    if (!elemento || elemento.disabled) return;

    const caja = elemento.getBoundingClientRect();
    const tamano = Math.hypot(caja.width, caja.height) * 2;
    const onda = document.createElement('span');
    onda.className = 'onda';
    onda.setAttribute('aria-hidden', 'true');
    onda.style.width = onda.style.height = `${tamano}px`;
    onda.style.left = `${evento.clientX - caja.left - tamano / 2}px`;
    onda.style.top = `${evento.clientY - caja.top - tamano / 2}px`;
    elemento.appendChild(onda);
    onda.addEventListener('animationend', () => onda.remove());
});

// Botón que envía: rueda de espera mientras carga la página siguiente. Va después del control de conexión:
// solo marca los envíos que de verdad salen. Si la página no cambia (descarga, error), se quita sola.
const quitarEnviando = (boton) => {
    boton.classList.remove('enviando');
    boton.removeAttribute('aria-busy');
};

document.addEventListener('submit', (evento) => {
    const boton = evento.submitter;
    if (evento.defaultPrevented || !boton || (evento.target.getAttribute('method') ?? 'get').toLowerCase() !== 'post') return;
    boton.classList.add('enviando');
    boton.setAttribute('aria-busy', 'true');
    setTimeout(() => quitarEnviando(boton), 10000);
});

// Al volver atrás el navegador puede mostrar la página guardada con el botón aún esperando
window.addEventListener('pageshow', () => document.querySelectorAll('.enviando').forEach(quitarEnviando));
