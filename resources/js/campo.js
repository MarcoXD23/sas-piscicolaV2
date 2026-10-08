/*
 * Pantalla «Registro rápido» (/campo): registrar sin conexión con el paquete de trabajo guardado en el teléfono.
 * - La página es un cascarón sin datos (resources/views/campo/index.blade.php); todo se arma aquí con el paquete de
 *   la sesión activa (IndexedDB). Nada depende de páginas guardadas ni del token de la página.
 * - Cada registro se valida en el teléfono (campo-logica.js) y va a la cola de siempre (sin-conexion.js): uuid, hora
 *   real y corrección del reloj. Con señal se envía enseguida; sin señal, al volver (avisa «Se enviaron N registros»).
 * - Todo texto que llega del paquete se escribe con textContent: nunca se interpreta como HTML.
 */
import { operacion, PAQUETES, leerSesionActiva } from './almacen';
import { antiguedad, armarRegistro, avisarFueraDeTurno, SECCIONES, textoHace, validar } from './campo-logica';
import { leerPaquete } from './paquete';
import { debeRenovar, paqueteValido } from './paquete-logica';
import { fechaLocal } from './sin-conexion-logica';

const CLASE_CONTROL = 'block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base text-slate-900 shadow-sm focus:border-marca-500 focus:outline-none focus:ring-2 focus:ring-marca-200';

const el = (etiqueta, clase = '', texto = null) => {
    const nodo = document.createElement(etiqueta);
    if (clase) nodo.className = clase;
    if (texto !== null) nodo.textContent = texto;
    return nodo;
};

const opcion = (valor, texto) => {
    const nodo = el('option', '', texto);
    nodo.value = valor;
    return nodo;
};

const fechaCorta = (iso) => {
    const [anio, mes, dia] = String(iso).split('-');
    return `${dia}/${mes}/${anio}`;
};

export const iniciarCampo = (t, cola) => {
    const raiz = document.querySelector('[data-campo]');
    if (!raiz) return;

    const horasViejo = Number(raiz.dataset.horasViejo) || 12;
    const minutosPaquete = Number(raiz.dataset.minutosPaquete) || 15;
    const $ = (selector) => raiz.querySelector(selector);
    let clave = null;
    let paquete = null;
    let actualizando = false;

    const mensaje = (texto, tipo = 'ok') => {
        const caja = $('[data-campo-mensaje]');
        caja.hidden = false;
        caja.className = tipo === 'ok'
            ? 'mb-4 rounded-xl border border-marca-200 bg-marca-50 px-4 py-3 text-base font-medium text-marca-800'
            : 'mb-4 rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-base font-medium text-amber-900';
        caja.textContent = texto;
        caja.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    };

    // --- Encabezado: quién registra y de cuándo son los datos ---

    const pintarEstado = () => {
        $('[data-campo-cargando]').hidden = true;
        $('[data-campo-sin-paquete]').hidden = Boolean(paquete);
        $('[data-campo-listo]').hidden = !paquete;
        $('[data-campo-actualizar]').hidden = !navigator.onLine || !clave;
        if (!paquete) return;

        $('[data-campo-quien]').textContent = t('campo_quien', { nombre: paquete.usuario?.nombre ?? '', finca: paquete.finca?.nombre ?? '' });
        const edad = antiguedad(paquete.generado_at, horasViejo);
        const generado = new Date(paquete.generado_at);
        const cuando = generado.toLocaleString(document.documentElement.lang, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        const [claveHace, datosHace] = textoHace(edad);
        const caja = $('[data-campo-antiguedad]');
        caja.textContent = `${t('campo_datos_del', { momento: cuando, hace: t(claveHace, datosHace) })}${edad.vieja ? ` ${t('campo_datos_viejos')}` : ''}`;
        caja.className = edad.vieja
            ? 'rounded-xl bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-900'
            : 'text-sm text-slate-600';
        $('[data-campo-truncado]').hidden = !paquete.lagos_truncados;
        $('[data-campo-truncado]').textContent = t('campo_lagos_truncados', { n: paquete.max_lagos });

        const botones = $('[data-campo-secciones]');
        const secciones = (paquete.secciones ?? []).filter((seccion) => SECCIONES.includes(seccion));
        botones.replaceChildren(...secciones.map((seccion) => {
            const boton = el('button', 'flex min-h-16 items-center justify-center rounded-2xl bg-white px-4 text-lg font-semibold text-marca-800 shadow-sm ring-1 ring-slate-200 hover:bg-marca-50', t(`campo_seccion_${seccion}`));
            boton.type = 'button';
            boton.addEventListener('click', () => abrir(seccion));
            return boton;
        }));
        $('[data-campo-sin-secciones]').hidden = secciones.length > 0;
    };

    // --- Formularios ---

    const grupo = (nombre, etiqueta, control, ayuda = null) => {
        const caja = el('div');
        const titulo = el('label', 'mb-1.5 block text-base font-medium text-slate-700', etiqueta);
        titulo.htmlFor = `campo-${nombre}`;
        control.id = `campo-${nombre}`;
        control.name = nombre;
        caja.append(titulo, control);
        if (ayuda) caja.append(el('p', 'mt-1 text-sm text-slate-500', ayuda));
        const error = el('p', 'mt-1.5 text-sm font-medium text-red-600');
        error.dataset.errorDe = nombre;
        error.hidden = true;
        caja.append(error);
        return caja;
    };

    const entrada = (tipo = 'text', atributos = {}) => {
        const nodo = el('input', CLASE_CONTROL);
        nodo.type = tipo;
        Object.entries(atributos).forEach(([nombre, valor]) => nodo.setAttribute(nombre, valor));
        return nodo;
    };

    const lista = (opciones, vacia = null) => {
        const nodo = el('select', CLASE_CONTROL);
        if (vacia !== null) nodo.append(opcion('', vacia));
        opciones.forEach(([valor, texto]) => nodo.append(opcion(valor, texto)));
        return nodo;
    };

    const lagosPara = (seccion) => (paquete.lagos ?? []).filter((lago) => ({
        racion: Boolean(lago.url_racion),
        mortalidad: (lago.siembras ?? []).some((siembra) => siembra.url_mortalidad),
        aviso_pesca: Boolean(lago.url_aviso_pesca),
        ronda: true,
        medicion: Boolean(lago.url_medicion),
    })[seccion]);

    const textoSiembra = (siembra) => t('campo_opcion_lote', { especie: siembra.especie, fecha: fechaCorta(siembra.fecha), vivos: siembra.peces_vivos.toLocaleString(document.documentElement.lang) });

    const camposDe = (seccion, formulario) => {
        const lagos = lagosPara(seccion);
        const selectorLago = lista(lagos.map((lago) => [lago.id, lago.nombre]), seccion === 'ronda' ? t('campo_toda_la_finca') : t('campo_elige'));
        const campos = [grupo('lago_id', t(seccion === 'ronda' ? 'campo_lago_opcional' : 'campo_lago'), selectorLago)];
        const fecha = () => grupo('fecha', t('campo_fecha'), entrada('date', { value: fechaLocal(), max: fechaLocal(), required: '' }));

        if (seccion === 'racion') {
            const concentrados = lista((paquete.concentrados ?? []).map((c) => [c.tipo, t('campo_opcion_concentrado', { tipo: c.tipo, kg: c.kg.toLocaleString(document.documentElement.lang) })]), t('campo_elige'));
            const kg = entrada('text', { inputmode: 'decimal', autocomplete: 'off' });
            const ayuda = el('p', 'mt-1 text-sm text-slate-500');
            selectorLago.addEventListener('change', () => {
                const sugerida = lagos.find((lago) => String(lago.id) === selectorLago.value)?.racion_sugerida_kg;
                ayuda.textContent = sugerida ? t('campo_racion_sugerida', { kg: sugerida.toLocaleString(document.documentElement.lang) }) : '';
                if (sugerida && kg.value === '') kg.value = String(sugerida);
            });
            const grupoKg = grupo('cantidad_kg', t('campo_kg'), kg);
            grupoKg.insertBefore(ayuda, grupoKg.lastChild);
            if ((paquete.concentrados ?? []).length === 0) campos.push(el('p', 'rounded-xl bg-red-50 p-4 text-base text-red-700', t('campo_sin_concentrado')));
            campos.push(grupo('tipo_concentrado', t('campo_concentrado'), concentrados), grupoKg, fecha());
        }

        if (seccion === 'mortalidad' || seccion === 'aviso_pesca') {
            const lote = lista([], t('campo_elige_lago_primero'));
            selectorLago.addEventListener('change', () => {
                const lago = lagos.find((uno) => String(uno.id) === selectorLago.value);
                const siembras = (lago?.siembras ?? []).filter((siembra) => seccion === 'aviso_pesca' || siembra.url_mortalidad);
                lote.replaceChildren(opcion('', t(lago ? 'campo_elige' : 'campo_elige_lago_primero')), ...siembras.map((siembra) => opcion(siembra.id, textoSiembra(siembra))));
                if (siembras.length === 1) lote.value = String(siembras[0].id);
                const aviso = formulario.querySelector('[data-campo-aviso-pendiente]');
                if (aviso) aviso.hidden = !lago?.aviso_pendiente;
            });
            campos.push(grupo('siembra_id', t('campo_lote'), lote));
            if (seccion === 'mortalidad') {
                campos.push(grupo('cantidad', t('campo_peces_muertos'), entrada('text', { inputmode: 'numeric', autocomplete: 'off' })), fecha());
            } else {
                const pendiente = el('p', 'rounded-xl bg-amber-50 p-3 text-sm font-medium text-amber-900', t('campo_error_aviso_pendiente'));
                pendiente.dataset.campoAvisoPendiente = '';
                pendiente.hidden = true;
                campos.push(pendiente, grupo('nota', t('campo_nota_opcional'), entrada('text', { maxlength: '500' })));
            }
        }

        if (seccion === 'ronda') {
            const tipos = el('fieldset');
            tipos.append(el('legend', 'mb-1.5 text-base font-medium text-slate-700', t('campo_tipo_ronda')));
            const opciones = el('div', 'grid gap-2');
            (paquete.tipos_ronda ?? []).forEach((tipo) => {
                const etiqueta = el('label', 'flex min-h-12 cursor-pointer items-center gap-3 rounded-xl border border-slate-300 px-4 text-base has-checked:border-marca-500 has-checked:bg-marca-50');
                const radio = entrada('radio');
                radio.className = 'h-5 w-5 accent-marca-600';
                radio.name = 'tipo';
                radio.value = tipo.codigo;
                radio.checked = tipo.codigo === paquete.sin_novedad;
                etiqueta.append(radio, document.createTextNode(tipo.texto));
                opciones.append(etiqueta);
            });
            const error = el('p', 'mt-1.5 text-sm font-medium text-red-600');
            error.dataset.errorDe = 'tipo';
            error.hidden = true;
            tipos.append(opciones, error);
            const descripcion = el('textarea', CLASE_CONTROL);
            descripcion.rows = 3;
            descripcion.maxLength = 1000;
            campos.unshift(tipos);
            campos.push(grupo('descripcion', t('campo_descripcion'), descripcion, t('campo_descripcion_ayuda')));
        }

        if (seccion === 'medicion') {
            const parametros = el('div', 'grid grid-cols-2 gap-4');
            (paquete.medicion ?? []).forEach((parametro) => parametros.append(grupo(parametro.campo, parametro.etiqueta,
                entrada('text', { inputmode: parametro.paso >= 1 ? 'numeric' : 'decimal', autocomplete: 'off', placeholder: `${parametro.min} – ${parametro.max}` }))));
            const error = el('p', 'text-sm font-medium text-red-600');
            error.dataset.errorDe = 'medicion';
            error.hidden = true;
            campos.push(el('p', 'text-sm text-slate-600', t('campo_medicion_ayuda')), parametros, error);
        }

        return campos;
    };

    const abrir = (seccion) => {
        const contenedor = $('[data-campo-formulario]');
        const formulario = el('form', 'space-y-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200');
        formulario.noValidate = true;   // se valida aquí, con los textos de la app
        formulario.append(el('h2', 'text-xl font-bold text-slate-900', t(`campo_seccion_${seccion}`)));

        if (avisarFueraDeTurno(paquete, seccion)) {
            formulario.append(el('p', 'rounded-xl bg-amber-50 p-3 text-sm font-medium text-amber-900', t('campo_fuera_de_turno')));
        }
        formulario.append(...camposDe(seccion, formulario));

        const botones = el('div', 'grid grid-cols-2 gap-3 pt-2');
        const cancelar = el('button', 'inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-5 py-3 text-base font-semibold text-marca-700 shadow-sm ring-1 ring-inset ring-marca-200 hover:bg-marca-50', t('campo_cancelar'));
        cancelar.type = 'button';
        cancelar.addEventListener('click', () => contenedor.replaceChildren());
        const guardar = el('button', 'inline-flex min-h-12 items-center justify-center rounded-xl bg-marca-600 px-5 py-3 text-base font-semibold text-white shadow-sm hover:bg-marca-700', t('campo_guardar'));
        guardar.type = 'submit';
        botones.append(cancelar, guardar);
        formulario.append(botones);

        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const datos = Object.fromEntries(new FormData(formulario).entries());
            const errores = validar(seccion, datos, paquete, fechaLocal());
            formulario.querySelectorAll('[data-error-de]').forEach((nodo) => {
                const error = errores[nodo.dataset.errorDe];
                nodo.hidden = !error;
                nodo.textContent = error ? t(error[0], error[1]) : '';
            });
            if (Object.keys(errores).length > 0) {
                formulario.querySelector('[data-error-de]:not([hidden])')?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                return;
            }

            guardar.disabled = true;
            const guardado = await cola.guardarRegistro(armarRegistro(seccion, datos, paquete, t));
            guardar.disabled = false;
            if (!guardado) {
                mensaje(t('campo_no_se_pudo_guardar'), 'atencion');
                return;
            }
            contenedor.replaceChildren();
            mensaje(t(navigator.onLine ? 'campo_guardado_enviando' : 'cola_guardado'));
            if (navigator.onLine) cola.enviar({ avisar: true });
        });

        contenedor.replaceChildren(formulario);
        formulario.scrollIntoView({ block: 'start', behavior: 'smooth' });
    };

    // --- Actualizar el paquete desde esta pantalla (con señal) ---

    const actualizar = async ({ avisar = false } = {}) => {
        if (!clave || actualizando || !navigator.onLine) return;
        actualizando = true;
        try {
            const respuesta = await fetch('/campo/paquete', {
                credentials: 'same-origin',
                cache: 'no-store',
                redirect: 'manual',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (respuesta.status === 401 || respuesta.status === 419 || respuesta.type === 'opaqueredirect') {
                if (avisar) mensaje(t('campo_sesion_vencida'), 'atencion');
                return;
            }
            if (!respuesta.ok) return;
            const nuevo = await respuesta.json();
            if (!paqueteValido(nuevo, clave)) {
                if (avisar) mensaje(t('campo_otra_sesion'), 'atencion');
                return;
            }
            await operacion(PAQUETES, 'readwrite', (almacen) => almacen.put(nuevo));
            paquete = nuevo;
            pintarEstado();
            if (avisar) mensaje(t('campo_datos_actualizados'));
        } catch {
            if (avisar) mensaje(t('cola_sin_senal'), 'atencion');
        } finally {
            actualizando = false;
        }
    };

    $('[data-campo-actualizar]').addEventListener('click', () => actualizar({ avisar: true }));
    window.addEventListener('online', () => {
        pintarEstado();
        if (debeRenovar(paquete, clave, minutosPaquete)) actualizar();
    });
    window.addEventListener('offline', () => { if (paquete) pintarEstado(); });

    (async () => {
        clave = await leerSesionActiva();
        paquete = clave ? await leerPaquete(clave) : null;
        pintarEstado();
        if (clave && navigator.onLine && debeRenovar(paquete, clave, minutosPaquete)) actualizar();
    })();
};
