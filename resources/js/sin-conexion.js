/*
 * Registros hechos sin conexión (raciones, mortalidad, aviso de pesca, rondas y mediciones).
 * - Solo los formularios con data-guardar-sin-conexion (su valor es el resumen que se muestra en la lista).
 * - Se guardan en el IndexedDB del teléfono, separados por finca y persona (data-cola-clave del <body>).
 * - Se envían en orden al volver la señal, al abrir cualquier pantalla y con «Enviar ahora», con las cabeceras
 *   X-Registro-Sin-Conexion (uuid), X-Registrado-En (hora del registro) y X-Hora-Telefono (hora del envío, con la que
 *   el servidor corrige un reloj mal puesto). El servidor aplica sus reglas al llegar
 *   (app/Http/Middleware/RegistroSinConexion.php).
 * - Lo que el servidor rechaza queda en «Rechazados» con su motivo y sus datos, hasta que la persona lo descarta.
 */
import { camposParaGuardar, clasificar, fechaLocal, motivoRechazo, porVencer } from './sin-conexion-logica';

const BASE = 'sas-piscicola-sin-conexion';
const ALMACEN = 'registros';

const abrirBase = () => new Promise((resolver, rechazar) => {
    const peticion = indexedDB.open(BASE, 1);
    peticion.onupgradeneeded = () => {
        const almacen = peticion.result.createObjectStore(ALMACEN, { keyPath: 'uuid' });
        almacen.createIndex('clave', 'clave');
    };
    peticion.onsuccess = () => resolver(peticion.result);
    peticion.onerror = () => rechazar(peticion.error);
});

const operacion = async (modo, accion) => {
    const base = await abrirBase();
    return new Promise((resolver, rechazar) => {
        const transaccion = base.transaction(ALMACEN, modo);
        const resultado = accion(transaccion.objectStore(ALMACEN));
        transaccion.oncomplete = () => { base.close(); resolver(resultado?.result); };
        transaccion.onerror = () => { base.close(); rechazar(transaccion.error); };
        transaccion.onabort = () => { base.close(); rechazar(transaccion.error); };
    });
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
    const clave = document.body.dataset.colaClave;
    const horas = Number(document.body.dataset.colaHoras) || 48;
    const disponible = Boolean(clave) && 'indexedDB' in window && 'randomUUID' in crypto;

    const boton = document.querySelector('[data-cola-boton]');
    const ventana = document.getElementById('cola-sin-conexion');
    let enviando = false;

    const misRegistros = async () => {
        if (!disponible) return [];
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

    /** Guarda el formulario en el teléfono. Devuelve false si no se pudo (el formulario no se toca). */
    const guardar = async (formulario) => {
        if (!disponible) return false;
        const registro = {
            uuid: crypto.randomUUID(),
            clave,
            url: formulario.action,
            campos: camposParaGuardar([...new FormData(formulario).entries()]),
            detalle: detalleDe(formulario),
            resumen: formulario.dataset.guardarSinConexion,
            registradoEn: new Date().toISOString(),
            estado: 'pendiente',
            motivo: null,
        };

        try {
            await operacion('readwrite', (almacen) => almacen.add(registro));
        } catch {
            return false;
        }

        formulario.reset();
        formulario.closest('dialog')?.close();
        aviso(t('cola_guardado'));
        await pintar();
        return true;
    };

    const actualizar = (registro) => operacion('readwrite', (almacen) => almacen.put(registro));
    const quitar = (uuid) => operacion('readwrite', (almacen) => almacen.delete(uuid));

    /** Envía los pendientes en orden. Se detiene si no hay señal o la sesión venció (lo demás sigue en la cola). */
    const enviar = async ({ avisar = false } = {}) => {
        if (!disponible || enviando || !navigator.onLine) {
            if (avisar && !navigator.onLine) aviso(t('cola_sin_senal'), 'atencion');
            return;
        }
        enviando = true;
        let enviados = 0;
        let rechazados = 0;
        let detenido = null;

        try {
            for (const registro of (await misRegistros()).filter((r) => r.estado === 'pendiente')) {
                const datos = new FormData();
                registro.campos.forEach(([nombre, valor]) => datos.append(nombre, valor));
                datos.append('_token', document.querySelector('meta[name="csrf-token"]')?.content ?? '');

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

    if (disponible) {
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
    }

    return { disponible, guardar, enviar, total, borrarTodo };
};
