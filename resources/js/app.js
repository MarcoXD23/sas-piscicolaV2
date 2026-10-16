import { iniciarPesaje } from './pesaje';
import { iniciarCampo } from './campo';
import { iniciarPaquete } from './paquete';
import { iniciarCola } from './sin-conexion';
import { iniciarVerContrasena } from './contrasena';
import { iniciarAyudaHora } from './hora';
import { aCentesimas, leerRecordada, promedio as promedioPeso, recordar, texto as textoPeso, unidadInicial } from './unidades-peso';

// Textos de la interfaz (lang/es/js.php). El layout los publica en <script type="application/json" id="textos-js">,
// que no se ejecuta: la Content-Security-Policy no permite scripts en línea. Se leen antes que todo lo demás.
try {
    window.textos = JSON.parse(document.getElementById('textos-js')?.textContent || '{}');
} catch {
    window.textos = {};
}

// Tabla de pesadas de la cosecha (sin Alpine: la CSP no permite evaluar código escrito en el HTML)
document.querySelectorAll('[data-pesaje]').forEach(iniciarPesaje);

// Pedir confirmación (data-confirmar en un formulario o en un botón) e imprimir (data-imprimir): sin onclick ni
// onsubmit en el HTML, que la CSP bloquea. La confirmación va en captura, antes que cualquier otro manejador del envío.
document.addEventListener('submit', (evento) => {
    // El envío pasa dos veces (la segunda, tras revisar la conexión, más abajo): se pregunta solo la primera
    if (evento.target.dataset?.conexionVerificada) return;
    const mensaje = evento.target.dataset?.confirmar ?? evento.submitter?.dataset?.confirmar;
    if (mensaje && !window.confirm(mensaje)) {
        evento.preventDefault();
        evento.stopImmediatePropagation();
    }
}, true);
document.addEventListener('click', (evento) => {
    if (evento.target.closest('[data-imprimir]')) window.print();
});

// La cifra sobre la columna más alta de <x-grafica-columnas> (data-abajo, en %): su posición necesita calc(), así que
// se pone por JS. Las barras son SVG con el tamaño como atributo; ninguna usa style en el HTML, que la CSP bloquea.
document.querySelectorAll('[data-abajo]').forEach((elemento) => { elemento.style.bottom = `calc(${elemento.dataset.abajo}% + 4px)`; });

// Textos de la interfaz con :marcadores reemplazados.
// Como el traductor del servidor (App\Support\Traductor): sin punto doble tras «a. m.» / «p. m.» al final de una frase
const t = (clave, datos = {}) => (window.textos?.[clave] ?? clave).replace(/:(\w+)/g, (marca, nombre) => datos[nombre] ?? marca)
    .replace(/([ap])\.\u00A0m\.((?:<\/[a-z]+>)*)\./g, '$1.\u00A0m.$2');

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

// Muestreo: peso promedio por pez en vivo, en gramos y en la unidad elegida, con el «antes» y la diferencia. Se calcula
// con enteros igual que el servidor (resources/js/unidades-peso.js). Solo informativo: lo que se guarda lo calcula el
// servidor. La unidad elegida se recuerda por persona (la primera vez, la de la finca).
document.querySelectorAll('[data-muestreo]').forEach((muestreo) => {
    const peces = muestreo.querySelector('[data-peces]');
    const pesoTotal = muestreo.querySelector('[data-peso-total]');
    const unidad = muestreo.querySelector('[data-unidad]');
    const promedio = muestreo.querySelector('[data-promedio]');
    const anteriorTexto = muestreo.querySelector('[data-anterior-texto]');
    const factores = JSON.parse(muestreo.dataset.factores || '{"g":[100,1]}');
    const anterior = Number(muestreo.dataset.anterior) || 0;
    const clave = document.body.dataset.colaClave;
    const idioma = document.documentElement.lang;
    unidad.value = unidadInicial(leerRecordada(clave), muestreo.dataset.unidadDefecto, factores);

    const calcular = () => {
        const cantidad = Number(String(peces.value).trim());
        const centesimas = aCentesimas(String(pesoTotal.value).trim(), unidad.value, factores);
        // Sin datos, con cero peces o valores no válidos no se divide
        if (!Number.isInteger(cantidad) || cantidad <= 0 || centesimas === null || centesimas <= 0) {
            promedio.textContent = '—';
            anteriorTexto.textContent = t('muestreo_antes', { anterior: textoPeso(anterior, unidad.value, factores, idioma) });
            return;
        }
        const valor = promedioPeso(centesimas, cantidad);
        promedio.textContent = textoPeso(valor, unidad.value, factores, idioma);
        anteriorTexto.textContent = t('muestreo_antes_y_diferencia', {
            anterior: textoPeso(anterior, unidad.value, factores, idioma),
            diferencia: (valor >= anterior ? '+' : '−') + textoPeso(Math.abs(valor - anterior), unidad.value, factores, idioma),
        });
    };

    [peces, pesoTotal].forEach((campo) => campo.addEventListener('input', calcular));
    unidad.addEventListener('change', () => {
        recordar(clave, unidad.value);
        calcular();
    });
    calcular();
});

// Ración en kilos o en bultos (Lagos › Alimentar): el campo dice la unidad elegida. Al pasar a bultos se vacía la
// cantidad sugerida en kilos (para no enviarla como bultos); al volver a kilos se recupera.
document.querySelectorAll('[data-racion-unidad]').forEach((grupo) => {
    const unidad = grupo.querySelector('[data-unidad-racion]');
    const cantidad = grupo.querySelector('input[name="cantidad_kg"]');
    const etiqueta = cantidad && grupo.querySelector(`label[for="${cantidad.id}"]`);
    if (!unidad || !cantidad || !etiqueta) return;
    const sugerida = cantidad.value;

    unidad.addEventListener('change', () => {
        const enBultos = unidad.value === 'bultos';
        etiqueta.firstChild.textContent = enBultos ? grupo.dataset.etiquetaBultos : grupo.dataset.etiquetaKg;
        if (enBultos && cantidad.value === sugerida) cantidad.value = '';
        if (!enBultos && cantidad.value === '') cantidad.value = sugerida;
    });
});

// Copiar un texto (el enlace de una invitación en el panel de plataforma). Sin portapapeles, queda seleccionado
document.querySelectorAll('[data-copiar-grupo]').forEach((grupo) => {
    const texto = grupo.querySelector('[data-copiar-texto]');
    const boton = grupo.querySelector('[data-copiar]');
    if (!texto || !boton) return;
    boton.addEventListener('click', async () => {
        texto.select();
        try {
            await navigator.clipboard.writeText(texto.value);
            boton.textContent = boton.dataset.copiado;
        } catch {
            // El texto ya quedó seleccionado para copiarlo a mano
        }
    });
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
// Botón de ojo de los campos de contraseña (<x-contrasena>): mostrar u ocultar, y volver a ocultar al enviar o salir
iniciarVerContrasena();
// Bajo los campos donde se escribe una hora: la hora elegida en el formato de la persona (12 o 24 horas)
iniciarAyudaHora(t);

const hayConexion = async () => {
    if (!navigator.onLine) return false;
    try {
        const respuesta = await fetch('/up', { method: 'HEAD', cache: 'no-store', signal: AbortSignal.timeout(4000) });
        cola.recordarHora(respuesta);
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
            ? 'ml-8 animate-aparecer rounded-ficha rounded-br-sm bg-marca-700 px-4 py-2.5 text-base text-white whitespace-pre-line'
            : `mr-8 animate-aparecer rounded-ficha rounded-bl-sm bg-papel-hondo px-4 py-2.5 text-base text-tinta whitespace-pre-line ${clase}`;
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
        const pensando = agregar('assistant', t('pensando'), 'animate-pulse italic text-tinta-suave');

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
                agregar('assistant', error, '!bg-alerta-fondo !text-alerta');
            }
        } catch {
            pensando.remove();
            agregar('assistant', t('sin_conexion'), '!bg-alerta-fondo !text-alerta');
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
