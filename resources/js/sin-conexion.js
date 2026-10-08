/*
 * Registros hechos sin conexión (raciones, mortalidad, aviso de pesca, rondas y mediciones).
 * - Los formularios con data-guardar-sin-conexion (su valor es el resumen que se muestra en la lista) y los registros
 *   que arma la pantalla Registro rápido (/campo) con guardarRegistro().
 * - Se guardan en el IndexedDB del teléfono (resources/js/almacen.js), separados por finca y persona: la clave sale de
 *   data-cola-clave del <body> o, en /campo (sin sesión en la página), de la sesión activa guardada.
 * - Antes de enviar se pide un token vigente a /campo/token (nunca el de una página guardada, que puede estar vencido).
 * - Se envían en orden al volver la señal, al abrir cualquier pantalla y con «Enviar ahora», con las cabeceras
 *   X-Registro-Sin-Conexion (uuid), X-Registrado-En (hora del registro) y X-Hora-Telefono (hora del envío, con la que
 *   el servidor corrige un reloj mal puesto). El servidor aplica sus reglas al llegar
 *   (app/Http/Middleware/RegistroSinConexion.php).
 * - Lo que el servidor rechaza queda en «Rechazados» con su motivo y sus datos, hasta que la persona lo descarta.
 */
import { leerSesionActiva, operacion as operacionBase, REGISTROS } from './almacen';
import { estadoToken } from './paquete-logica';
import { camposParaGuardar, clasificar, fechaLocal, motivoRechazo, porVencer } from './sin-conexion-logica';

const operacion = (modo, accion) => operacionBase(REGISTROS, modo, accion);

/** Token CSRF vigente de la sesión ({ token }) o el motivo para no enviar todavía ({ estado: 'sesion' | 'reintentar' }). */
const tokenFresco = async () => {
    try {
        const respuesta = await fetch('/campo/token', {
            credentials: 'same-origin',
            cache: 'no-store',
            redirect: 'manual',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const cuerpo = await respuesta.json().catch(() => null);
        const estado = estadoToken(respuesta.status, cuerpo);
        return estado === 'listo' ? { token: cuerpo.token } : { estado };
    } catch {
        return { estado: 'reintentar' };
    }
};

/** Texto de la etiqueta de un campo (o de su grupo, en los botones de opción). */
const etiquetaDe = (campo) => (campo.type === 'radio' ? campo.closest('fieldset')?.querySelector('legend') : campo.labels?.[0])
    ?.textContent.replace(/\s+/g, ' ').trim() ?? campo.name;

/** Lo escrito en el formulario, legible, para volver a registrarlo si el servidor lo rechaza. */
const detalleDe = (formulario) => [...formulario.elements]
    .filter((campo) => campo.name && !['hidden', 'submit', 'button', 'file'].includes(campo.type) && !campo.disabled)
    .filter((campo) => campo.type !== 'radio' || campo.checked)
    .map((campo) => {
        let valor = campo.value;
        if (campo.tagName === 'SELECT') valor = campo.selectedOptions[0]?.textContent.trim() ?? '';
        if (campo.type === 'radio') valor = campo.labels?.[0]?.textContent.replace(/\s+/g, ' ').trim() ?? valor;
        return [etiquetaDe(campo), valor];
    })
    .filter(([, valor]) => String(valor).trim() !== '');

export const iniciarCola = (t) => {
    let clave = document.body.dataset.colaClave ?? null;
    const horas = Number(document.body.dataset.colaHoras) || 48;
    const puedeGuardar = 'indexedDB' in window && 'randomUUID' in crypto;
    // En /campo la página no trae la clave: se usa la de la sesión activa guardada en el teléfono
    const listo = (async () => { if (!clave && puedeGuardar) clave = await leerSesionActiva(); })();
    const disponible = () => Boolean(clave) && puedeGuardar;

    const boton = document.querySelector('[data-cola-boton]');
    const ventana = document.getElementById('cola-sin-conexion');
    let enviando = false;

    const misRegistros = async () => {
        await listo;
        if (!disponible()) return [];
        try {
            const todos = await operacion('readonly', (almacen) => almacen.index('clave').getAll(clave));
            return (todos ?? []).sort((a, b) => a.registradoEn.localeCompare(b.registradoEn));
        } catch {
            return [];
        }
    };

    const aviso = (texto, tipo = 'ok') => {
        let caja = document.querySelector('[data-cola-aviso]');
        if (!caja) {
            caja = document.createElement('div');
            caja.setAttribute('data-cola-aviso', '');
            caja.setAttribute('role', 'status');
            document.getElementById('contenido')?.prepend(caja);
        }
        caja.className = tipo === 'ok'
            ? 'mb-5 rounded-xl border border-marca-200 bg-marca-50 px-4 py-3 text-base font-medium text-marca-800'
            : 'mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-base font-medium text-amber-900';
        caja.textContent = texto;
        caja.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };

    const elemento = (etiqueta, clase, texto = '') => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        nodo.textContent = texto; // textContent: lo guardado nunca se interpreta como HTML
        return nodo;
    };

    const tarjeta = (registro) => {
        const li = elemento('li', 'rounded-xl border border-slate-200 p-4');
        li.appendChild(elemento('p', 'text-base font-semibold text-slate-900', registro.resumen));
        const momento = new Date(registro.registradoEn).toLocaleString(document.documentElement.lang, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        li.appendChild(elemento('p', 'text-sm text-slate-500', t('cola_registrado', { momento })));

        const lista = elemento('ul', 'mt-2 space-y-0.5 text-sm text-slate-700');
        registro.detalle.forEach(([etiqueta, valor]) => lista.appendChild(elemento('li', '', `${etiqueta}: ${valor}`)));
        li.appendChild(lista);

        if (registro.estado === 'rechazado') {
            li.appendChild(elemento('p', 'mt-2 rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700', t('cola_motivo', { motivo: registro.motivo })));
            const descartar = elemento('button', 'mt-3 min-h-11 rounded-xl px-4 text-base font-semibold text-red-700 ring-1 ring-inset ring-red-200 hover:bg-red-50', t('cola_descartar'));
            descartar.type = 'button';
            descartar.dataset.colaDescartar = registro.uuid;
            li.appendChild(descartar);
        } else if (porVencer(registro.registradoEn, horas)) {
            li.appendChild(elemento('p', 'mt-2 text-sm font-medium text-amber-800', t('cola_por_vencer', { horas })));
        }

        return li;
    };

    const pintar = async () => {
        if (!boton) return;
        const registros = await misRegistros();
        const pendientes = registros.filter((registro) => registro.estado === 'pendiente');
        const rechazados = registros.filter((registro) => registro.estado === 'rechazado');

        boton.hidden = registros.length === 0;
        boton.querySelector('[data-cola-total]').textContent = String(registros.length);
        boton.classList.toggle('bg-red-600', rechazados.length > 0);
        boton.classList.toggle('bg-amber-400', rechazados.length === 0);
        boton.classList.toggle('text-amber-950', rechazados.length === 0);

        if (!ventana) return;
        for (const [grupo, lista] of [['pendientes', pendientes], ['rechazados', rechazados]]) {
            const seccion = ventana.querySelector(`[data-cola-${grupo}]`);
            seccion.hidden = lista.length === 0;
            seccion.querySelector('ul').replaceChildren(...lista.map(tarjeta));
        }
        ventana.querySelector('[data-cola-vacia]').hidden = registros.length > 0;
        ventana.querySelector('[data-cola-enviar]').hidden = pendientes.length === 0;
    };

    /**
     * Guarda un registro en la cola: { url, campos: [[nombre, valor]], detalle: [[etiqueta, valor]], resumen }.
     * Lo usan los formularios de las pantallas y Registro rápido. Devuelve false si no se pudo guardar.
     */
    const guardarRegistro = async ({ url, campos, detalle, resumen }) => {
        await listo;
        if (!disponible()) return false;
        try {
            await operacion('readwrite', (almacen) => almacen.add({
                uuid: crypto.randomUUID(),
                clave,
                url,
                campos: camposParaGuardar(campos),
                detalle,
                resumen,
                registradoEn: new Date().toISOString(),
                estado: 'pendiente',
                motivo: null,
            }));
        } catch {
            return false;
        }
        await pintar();
        return true;
    };

    /** Guarda el formulario en el teléfono. Devuelve false si no se pudo (el formulario no se toca). */
    const guardar = async (formulario) => {
        const guardado = await guardarRegistro({
            url: formulario.action,
            campos: [...new FormData(formulario).entries()],
            detalle: detalleDe(formulario),
            resumen: formulario.dataset.guardarSinConexion,
        });
        if (!guardado) return false;

        formulario.reset();
        formulario.closest('dialog')?.close();
        aviso(t('cola_guardado'));
        return true;
    };

    const actualizar = (registro) => operacion('readwrite', (almacen) => almacen.put(registro));
    const quitar = (uuid) => operacion('readwrite', (almacen) => almacen.delete(uuid));

    /** Envía los pendientes en orden. Se detiene si no hay señal o la sesión venció (lo demás sigue en la cola). */
    const enviar = async ({ avisar = false } = {}) => {
        await listo;
        if (!disponible() || enviando || !navigator.onLine) {
            if (avisar && !navigator.onLine) aviso(t('cola_sin_senal'), 'atencion');
            return;
        }
        enviando = true;
        let enviados = 0;
        let rechazados = 0;
        let detenido = null;

        try {
            const pendientes = (await misRegistros()).filter((r) => r.estado === 'pendiente');
            // Token vigente de la sesión, pedido justo ahora (si la sesión venció, los registros esperan)
            const sesion = pendientes.length > 0 ? await tokenFresco() : { token: null };
            if (!sesion.token && pendientes.length > 0) detenido = sesion.estado;

            for (const registro of sesion.token ? pendientes : []) {
                const datos = new FormData();
                registro.campos.forEach(([nombre, valor]) => datos.append(nombre, valor));
                datos.append('_token', sesion.token);

                let estado = 0;
                let cuerpo = null;
                try {
                    const respuesta = await fetch(registro.url, {
                        method: 'POST',
                        body: datos,
                        credentials: 'same-origin',
                        redirect: 'manual',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': sesion.token,
                            'X-Registro-Sin-Conexion': registro.uuid,
                            'X-Registrado-En': registro.registradoEn,
                            'X-Hora-Telefono': new Date().toISOString(),
                        },
                    });
                    estado = respuesta.status;
                    cuerpo = await respuesta.json().catch(() => null);
                } catch {
                    estado = 0;
                }

                const decision = clasificar(estado);
                if (decision === 'listo') {
                    await quitar(registro.uuid);
                    enviados++;
                } else if (decision === 'rechazado') {
                    await actualizar({ ...registro, estado: 'rechazado', motivo: motivoRechazo(cuerpo, t('cola_rechazo_generico')) });
                    rechazados++;
                } else {
                    detenido = decision;
                    break;
                }
            }
        } finally {
            enviando = false;
        }

        if (enviados > 0) aviso(t(enviados === 1 ? 'cola_enviado_uno' : 'cola_enviados_varios', { cantidad: enviados }));
        if (rechazados > 0) aviso(t('cola_hay_rechazados'), 'atencion');
        if (detenido === 'sesion') aviso(t('cola_sesion'), 'atencion');
        else if (detenido && avisar) aviso(t('cola_sin_senal'), 'atencion');
        await pintar();
    };

    const total = async () => (await misRegistros()).length;

    const borrarTodo = async () => {
        for (const registro of await misRegistros()) await quitar(registro.uuid);
    };

    // Una pantalla guardada de otro día trae la fecha de ese día: se pone la de hoy si no la cambiaron
    const fechasDeHoy = () => document.querySelectorAll('form[data-guardar-sin-conexion] input[type="date"]').forEach((campo) => {
        if (campo.value === campo.defaultValue) campo.value = campo.defaultValue = fechaLocal();
    });

    listo.then(() => {
        if (!disponible()) return;
        ventana?.addEventListener('click', async (evento) => {
            const descartar = evento.target.closest('[data-cola-descartar]');
            if (descartar) {
                await quitar(descartar.dataset.colaDescartar);
                await pintar();
            }
            if (evento.target.closest('[data-cola-enviar]')) await enviar({ avisar: true });
        });
        window.addEventListener('online', () => enviar());
        window.addEventListener('offline', fechasDeHoy);
        if (!navigator.onLine) fechasDeHoy();
        pintar().then(() => enviar());
    });

    return { disponible, guardar, guardarRegistro, enviar, total, borrarTodo, misRegistros };
};
