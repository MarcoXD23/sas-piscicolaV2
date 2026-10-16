/*
 * Pantalla «Registro rápido» (/campo): registrar sin conexión con el paquete de trabajo guardado en el teléfono.
 * - La página es un cascarón sin datos (resources/views/campo/index.blade.php); todo se arma aquí con el paquete de
 *   la sesión activa (IndexedDB). Nada depende de páginas guardadas ni del token de la página.
 * - Cada registro se valida en el teléfono (campo-logica.js) y va a la cola de siempre (sin-conexion.js): uuid, hora
 *   real y corrección del reloj. Con señal se envía enseguida; sin señal, al volver (avisa «Se enviaron N registros»).
 * - Pensada para usar en el campo: textos de 16 px con buen contraste (sol fuerte), botones grandes (guantes), errores
 *   anunciados y con resumen enlazado, y sin animaciones si el teléfono pide reducir el movimiento.
 * - Todo texto que llega del paquete se escribe con textContent: nunca se interpreta como HTML.
 */
import { leerSesionActiva, operacion, PAQUETES } from './almacen';
import { antiguedad, armarRegistro, avisarFueraDeTurno, factoresMuestreo, opcionesLotes, promedioMuestreo, resumenErrores, SECCIONES, textoHace, validar } from './campo-logica';
import { centesimasDeGramos, leerRecordada, recordar, texto as textoPeso, unidadInicial } from './unidades-peso';
import { leerPaquete } from './paquete';
import { diaMesHora, formatoPagina, normalizar, textosHora } from './hora';
import { debeRenovar, paqueteValido } from './paquete-logica';
import { desplazar } from './sin-conexion';
import { fechaLocal } from './sin-conexion-logica';

const CLASE_CONTROL = 'block min-h-12 w-full rounded-ficha border border-renglon-fuerte bg-ficha px-4 py-3 text-base text-tinta transition-[border-color,box-shadow] duration-150 hover:border-tinta-suave focus:border-marca-700 focus:outline-none focus:ring-4 focus:ring-marca-200/70 aria-invalid:border-alerta aria-invalid:bg-alerta-fondo/40 aria-invalid:ring-4 aria-invalid:ring-alerta-borde/60';
const CLASE_AYUDA = 'mt-1.5 text-base text-tinta-suave';
const CLASE_ERROR = 'mt-1.5 text-base font-semibold text-alerta';
const CLASE_AVISO = 'rounded-ficha border border-atencion-borde bg-atencion-fondo p-3 text-base font-semibold text-atencion';
const CLASE_SECCION = 'flex min-h-20 flex-col items-center justify-center gap-1 rounded-ficha px-3 py-2 text-lg font-bold shadow-ficha ring-1 transition-[background-color,translate] duration-150 active:translate-y-px';
const CLASE_SECCION_ABIERTA = 'bg-marca-700 text-white ring-marca-700';
const CLASE_SECCION_CERRADA = 'bg-ficha text-tinta ring-renglon-fuerte hover:bg-papel-hondo active:bg-renglon';

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

    /** Mensaje de estado arriba de la pantalla, con una acción opcional ({ texto, alTocar }). */
    const mensaje = (texto, tipo = 'ok', accion = null) => {
        const caja = $('[data-campo-mensaje]');
        caja.hidden = false;
        caja.className = tipo === 'ok'
            ? 'mb-4 rounded-ficha border border-bien-borde bg-bien-fondo px-4 py-3 text-base font-semibold text-bien'
            : 'mb-4 rounded-ficha border border-atencion-borde bg-atencion-fondo px-4 py-3 text-base font-semibold text-atencion';
        const parrafo = el('p', '', texto);
        caja.replaceChildren(parrafo);
        if (accion) {
            const boton = el('button', 'mt-3 inline-flex min-h-12 items-center justify-center rounded-ficha bg-marca-700 px-5 py-3 text-base font-semibold text-white shadow-ficha transition-[background-color,translate] duration-150 hover:bg-marca-800 active:translate-y-px active:bg-marca-900', accion.texto);
            boton.type = 'button';
            boton.addEventListener('click', accion.alTocar);
            caja.append(boton);
        }
        desplazar(caja, 'nearest');
    };

    // --- Encabezado: quién registra, de cuándo son los datos y las secciones del rol ---

    const marcarSeccion = (abierta) => {
        $('[data-campo-secciones]').querySelectorAll('button[data-seccion]').forEach((boton) => {
            const esta = boton.dataset.seccion === abierta;
            boton.setAttribute('aria-pressed', esta ? 'true' : 'false');
            boton.className = `${CLASE_SECCION} ${esta ? CLASE_SECCION_ABIERTA : CLASE_SECCION_CERRADA}`;
        });
    };

    /** Ícono de la sección: copia de la plantilla que trae la página (los íconos propios de la app, en SVG). */
    const iconoDe = (seccion) => document.querySelector(`template[data-campo-icono="${seccion}"]`)?.content.cloneNode(true) ?? document.createDocumentFragment();

    const pintarEstado = () => {
        $('[data-campo-cargando]').hidden = true;
        $('[data-campo-sin-paquete]').hidden = Boolean(paquete);
        $('[data-campo-listo]').hidden = !paquete;
        $('[data-campo-actualizar]').hidden = !navigator.onLine || !clave;
        if (!paquete) return;

        $('[data-campo-quien]').textContent = t('campo_quien', { nombre: paquete.usuario?.nombre ?? '', finca: paquete.finca?.nombre ?? '' });
        const edad = antiguedad(paquete.generado_at, horasViejo);
        // Esta página no tiene sesión: el formato de hora de la persona viaja en el paquete (uno viejo: 24 horas)
        document.body.dataset.formatoHora = normalizar(paquete.formato_hora);
        const cuando = diaMesHora(new Date(paquete.generado_at), formatoPagina(), textosHora(t));
        const [claveHace, datosHace] = textoHace(edad);
        const caja = $('[data-campo-antiguedad]');
        caja.textContent = `${t('campo_datos_del', { momento: cuando, hace: t(claveHace, datosHace) })}${edad.vieja ? ` ${t('campo_datos_viejos')}` : ''}`;
        caja.className = edad.vieja ? CLASE_AVISO : 'text-base text-tinta-suave';
        $('[data-campo-truncado]').hidden = !paquete.lagos_truncados;
        $('[data-campo-truncado]').textContent = t('campo_lagos_truncados', { n: paquete.max_lagos });

        const botones = $('[data-campo-secciones]');
        const secciones = (paquete.secciones ?? []).filter((seccion) => SECCIONES.includes(seccion));
        const abierta = $('[data-campo-formulario] form')?.dataset.seccion ?? null;
        botones.replaceChildren(...secciones.map((seccion) => {
            const boton = el('button');
            boton.type = 'button';
            boton.dataset.seccion = seccion;
            boton.append(iconoDe(seccion), el('span', '', t(`campo_seccion_${seccion}`)));
            boton.addEventListener('click', () => abrir(seccion));
            return boton;
        }));
        marcarSeccion(abierta);
        $('[data-campo-sin-secciones]').hidden = secciones.length > 0;
    };

    // --- Formularios ---

    /** Campo con etiqueta visible, ayuda y mensaje de error conectados al control (aria-describedby). */
    const grupo = (nombre, etiqueta, control, ayuda = null) => {
        const caja = el('div');
        const titulo = el('label', 'mb-1.5 block text-base font-semibold text-tinta', etiqueta);
        titulo.htmlFor = `campo-${nombre}`;
        control.id = `campo-${nombre}`;
        control.name = nombre;
        caja.append(titulo, control);
        if (ayuda) {
            const parrafo = el('p', CLASE_AYUDA, ayuda);
            parrafo.id = `ayuda-${nombre}`;
            control.setAttribute('aria-describedby', parrafo.id);
            caja.append(parrafo);
        }
        const error = el('p', CLASE_ERROR);
        error.id = `error-${nombre}`;
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
        muestreo: (lago.siembras ?? []).some((siembra) => siembra.url_muestreo),
        aviso_pesca: Boolean(lago.url_aviso_pesca),
        ronda: true,
        medicion: Boolean(lago.url_medicion),
    })[seccion]);

    // Lotes para elegir: agrupados por especie cuando una especie tiene varios (con su número y su origen)
    const formatear = { fecha: fechaCorta, numero: (n) => n.toLocaleString(document.documentElement.lang) };
    const nodosLotes = (siembras) => opcionesLotes(siembras, t, formatear).map(({ grupo, opciones }) => {
        const nodos = opciones.map(([valor, texto]) => opcion(valor, texto));
        if (!grupo) return nodos;
        const optgroup = document.createElement('optgroup');
        optgroup.label = grupo;
        optgroup.append(...nodos);
        return [optgroup];
    }).flat();

    const camposDe = (seccion, formulario) => {
        const lagos = lagosPara(seccion);
        const textoLago = (lago) => (lago.fase ? t('campo_opcion_lago_fase', { nombre: lago.nombre, fase: lago.fase }) : lago.nombre);
        const selectorLago = lista(lagos.map((lago) => [lago.id, textoLago(lago)]), seccion === 'ronda' ? t('campo_toda_la_finca') : t('campo_elige'));
        const campos = [grupo('lago_id', t(seccion === 'ronda' ? 'campo_lago_opcional' : 'campo_lago'), selectorLago)];
        const fecha = () => grupo('fecha', t('campo_fecha'), entrada('date', { value: fechaLocal(), max: fechaLocal(), required: '' }));

        if (seccion === 'racion') {
            const etiquetaConcentrado = (c) => (c.nombre_corto && c.nombre_corto !== c.tipo ? `${c.nombre_corto} · ${c.tipo}` : c.tipo);
            const concentrados = lista((paquete.concentrados ?? []).map((c) => [c.tipo, t('campo_opcion_concentrado', { tipo: etiquetaConcentrado(c), kg: c.kg.toLocaleString(document.documentElement.lang) })]), t('campo_elige'));
            const kg = entrada('text', { inputmode: 'decimal', autocomplete: 'off' });
            const unidadRacion = lista([['kg', t('campo_unidad_kg')], ['bultos', t('campo_unidad_bultos')]]);
            const grupoKg = grupo('cantidad_kg', t('campo_kg'), kg, ' ');
            const grupoUnidadRacion = grupo('unidad_racion', t('campo_unidad'), unidadRacion);
            const cantidadYUnidad = el('div', 'grid grid-cols-[1fr_auto] items-start gap-3');
            cantidadYUnidad.append(grupoKg, grupoUnidadRacion);
            const ayuda = grupoKg.querySelector('#ayuda-cantidad_kg');
            ayuda.textContent = '';
            selectorLago.addEventListener('change', () => {
                const lago = lagos.find((uno) => String(uno.id) === selectorLago.value);
                const sugerida = lago?.racion_sugerida_kg;
                const sugerido = lago?.concentrado_sugerido;
                if (sugerida && sugerido) {
                    ayuda.textContent = t('campo_racion_sugerida_con', { kg: sugerida.toLocaleString(document.documentElement.lang), concentrado: sugerido });
                } else {
                    ayuda.textContent = sugerida ? t('campo_racion_sugerida', { kg: sugerida.toLocaleString(document.documentElement.lang) }) : '';
                }
                if (sugerida && kg.value === '' && unidadRacion.value === 'kg') kg.value = String(sugerida);
                if (sugerido && concentrados.value === '') {
                    const enBodega = (paquete.concentrados ?? []).find((c) => c.tipo === sugerido || c.nombre_corto === sugerido);
                    if (enBodega) concentrados.value = enBodega.tipo;
                }
            });
            if ((paquete.concentrados ?? []).length === 0) campos.push(el('p', 'rounded-ficha border border-alerta-borde bg-alerta-fondo p-4 text-base font-semibold text-alerta', t('campo_sin_concentrado')));
            campos.push(grupo('tipo_concentrado', t('campo_concentrado'), concentrados), cantidadYUnidad, fecha());
        }

        if (seccion === 'mortalidad' || seccion === 'muestreo' || seccion === 'aviso_pesca') {
            const lote = lista([], t('campo_elige_lago_primero'));
            const url = { mortalidad: 'url_mortalidad', muestreo: 'url_muestreo' }[seccion];
            selectorLago.addEventListener('change', () => {
                const lago = lagos.find((uno) => String(uno.id) === selectorLago.value);
                const siembras = (lago?.siembras ?? []).filter((siembra) => !url || siembra[url]);
                lote.replaceChildren(opcion('', t(lago ? 'campo_elige' : 'campo_elige_lago_primero')), ...nodosLotes(siembras));
                if (siembras.length === 1) lote.value = String(siembras[0].id);
                const aviso = formulario.querySelector('[data-campo-aviso-pendiente]');
                if (aviso) aviso.hidden = !lago?.aviso_pendiente;
            });
            campos.push(grupo('siembra_id', t('campo_lote'), lote));
            if (seccion === 'mortalidad') {
                campos.push(grupo('cantidad', t('campo_peces_muertos'), entrada('text', { inputmode: 'numeric', autocomplete: 'off' })), fecha());
            } else if (seccion === 'muestreo') {
                const peces = entrada('text', { inputmode: 'numeric', autocomplete: 'off' });
                const peso = entrada('text', { inputmode: 'decimal', autocomplete: 'off' });
                // Unidad del peso total: la última que eligió esta persona, la de la finca o gramos. Con un paquete viejo
                // (sin unidades) no hay selector: todo en gramos, como antes.
                const factores = factoresMuestreo(paquete);
                const unidades = (paquete.muestreo?.unidades ?? ['g']).filter((unidad) => factores[unidad]);
                const unidad = lista(unidades.map((codigo) => [codigo, t(`campo_unidad_${codigo}`)]));
                unidad.value = unidadInicial(leerRecordada(clave), paquete.muestreo?.unidad_defecto, factores);
                unidad.addEventListener('change', () => recordar(clave, unidad.value));
                const grupoUnidad = grupo('unidad', t('campo_unidad'), unidad);
                grupoUnidad.hidden = unidades.length < 2;
                const pesoYUnidad = el('div', 'grid grid-cols-[1fr_auto] items-start gap-3');
                pesoYUnidad.append(grupo('peso_total', t('campo_peso_total'), peso), grupoUnidad);
                // Promedio en vivo en gramos y en la unidad elegida, y diferencia con el peso del paquete
                // (con enteros, igual que el servidor; la que vale la calcula el servidor al llegar)
                const promedio = el('p', 'rounded-ficha bg-papel-hondo p-4 text-base text-tinta', t('campo_promedio_vacio'));
                promedio.setAttribute('aria-live', 'polite');
                const idioma = document.documentElement.lang;
                const calcular = () => {
                    const valor = promedioMuestreo(peces.value, peso.value, unidad.value, factores);
                    const siembra = lagos.find((uno) => String(uno.id) === selectorLago.value)?.siembras?.find((una) => String(una.id) === lote.value);
                    const enTexto = (centesimas) => textoPeso(centesimas, unidad.value, factores, idioma);
                    if (valor === null) {
                        promedio.textContent = t('campo_promedio_vacio');
                    } else if (siembra) {
                        const anterior = centesimasDeGramos(siembra.peso_actual_g);
                        promedio.textContent = t('campo_promedio_diferencia', {
                            promedio: enTexto(valor), anterior: enTexto(anterior), diferencia: (valor >= anterior ? '+' : '−') + enTexto(Math.abs(valor - anterior)),
                        });
                    } else {
                        promedio.textContent = t('campo_promedio', { promedio: enTexto(valor) });
                    }
                };
                [peces, peso].forEach((campo) => campo.addEventListener('input', calcular));
                [selectorLago, lote, unidad].forEach((campo) => campo.addEventListener('change', calcular));
                campos.push(grupo('peces_pesados', t('campo_peces_pesados'), peces), pesoYUnidad, promedio, fecha());
            } else {
                const pendiente = el('p', CLASE_AVISO, t('campo_error_aviso_pendiente'));
                pendiente.dataset.campoAvisoPendiente = '';
                pendiente.hidden = true;
                campos.push(pendiente, grupo('nota', t('campo_nota_opcional'), entrada('text', { maxlength: '500' })));
            }
        }

        if (seccion === 'ronda') {
            const tipos = el('fieldset');
            tipos.setAttribute('aria-describedby', 'error-tipo');
            tipos.append(el('legend', 'mb-1.5 text-base font-semibold text-tinta', t('campo_tipo_ronda')));
            const opciones = el('div', 'grid gap-2');
            (paquete.tipos_ronda ?? []).forEach((tipo, indice) => {
                const etiqueta = el('label', 'flex min-h-12 cursor-pointer items-center gap-3 rounded-ficha border border-renglon-fuerte px-4 text-base text-tinta transition-colors has-checked:border-marca-700 has-checked:bg-bien-fondo has-checked:font-bold');
                const radio = entrada('radio');
                radio.className = 'h-6 w-6 shrink-0 accent-marca-700';
                radio.name = 'tipo';
                radio.value = tipo.codigo;
                if (indice === 0) radio.id = 'campo-tipo';   // destino del enlace del resumen de errores
                radio.checked = tipo.codigo === paquete.sin_novedad;
                etiqueta.append(radio, document.createTextNode(tipo.texto));
                opciones.append(etiqueta);
            });
            const error = el('p', CLASE_ERROR);
            error.id = 'error-tipo';
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
            const ayuda = el('p', 'text-base text-tinta-suave', t('campo_medicion_ayuda'));
            const parametros = el('div', 'grid grid-cols-2 gap-4');
            (paquete.medicion ?? []).forEach((parametro) => parametros.append(grupo(parametro.campo, parametro.etiqueta,
                entrada('text', { inputmode: parametro.paso >= 1 ? 'numeric' : 'decimal', autocomplete: 'off', placeholder: `${parametro.min} – ${parametro.max}` }))));
            const error = el('p', CLASE_ERROR);
            error.id = 'error-medicion';
            error.dataset.errorDe = 'medicion';
            error.hidden = true;
            campos.push(ayuda, parametros, error);
        }

        return campos;
    };

    /** Marca los campos con error (aria-invalid + mensaje conectado) y arma el resumen enlazado arriba del formulario. */
    const mostrarErrores = (formulario, errores) => {
        const orden = [...formulario.querySelectorAll('[data-error-de]')].map((nodo) => nodo.dataset.errorDe);
        formulario.querySelectorAll('[data-error-de]').forEach((nodo) => {
            const error = errores[nodo.dataset.errorDe];
            nodo.hidden = !error;
            nodo.textContent = error ? t(error[0], error[1]) : '';
            const control = formulario.querySelector(`#campo-${nodo.dataset.errorDe}`);
            if (!control) return;
            const ayuda = formulario.querySelector(`#ayuda-${nodo.dataset.errorDe}`);
            control.setAttribute('aria-describedby', [ayuda?.id, error ? nodo.id : null].filter(Boolean).join(' '));
            if (error && control.type !== 'radio') control.setAttribute('aria-invalid', 'true');
            else control.removeAttribute('aria-invalid');
        });

        const resumen = formulario.querySelector('[data-campo-resumen]');
        const lista = resumenErrores(errores, orden);
        resumen.hidden = lista.length === 0;
        if (lista.length === 0) return;

        const titulo = el('h3', 'text-base font-bold text-alerta', t(lista.length === 1 ? 'campo_revisa_uno' : 'campo_revisa_varios', { n: lista.length }));
        titulo.id = 'campo-resumen-titulo';
        const enlaces = el('ul', 'mt-2 list-disc space-y-1 pl-5');
        lista.forEach(([campo, [claveError, datosError]]) => {
            // El error general de la medición lleva al primer parámetro
            const destino = formulario.querySelector(`#campo-${campo}`) ?? formulario.querySelector('[data-error-de="medicion"]')?.previousElementSibling?.querySelector('input');
            const item = el('li');
            const enlace = el('a', 'font-semibold text-alerta underline underline-offset-4', t(claveError, datosError));
            enlace.href = destino?.id ? `#${destino.id}` : '#';
            enlace.addEventListener('click', (evento) => {
                evento.preventDefault();
                destino?.focus();
                desplazar(destino, 'center');
            });
            item.append(enlace);
            enlaces.append(item);
        });
        resumen.replaceChildren(titulo, enlaces);
        resumen.focus();
        desplazar(resumen, 'center');
    };

    const cerrar = () => {
        $('[data-campo-formulario]').replaceChildren();
        marcarSeccion(null);
    };

    const abrir = (seccion) => {
        const contenedor = $('[data-campo-formulario]');
        const formulario = el('form', 'space-y-5 rounded-ficha bg-ficha p-5 shadow-ficha ring-1 ring-renglon');
        formulario.noValidate = true;   // se valida aquí, con los textos de la app
        formulario.dataset.seccion = seccion;
        formulario.append(el('h2', 'text-seccion font-bold text-tinta', t(`campo_seccion_${seccion}`)));

        // Resumen de errores: se llena al fallar el envío y recibe el foco (role=alert lo anuncia)
        const resumen = el('div', 'rounded-ficha border-2 border-alerta-borde bg-alerta-fondo p-4 focus:outline-none focus:ring-4 focus:ring-alerta-borde/60');
        resumen.dataset.campoResumen = '';
        resumen.setAttribute('role', 'alert');
        resumen.setAttribute('aria-labelledby', 'campo-resumen-titulo');
        resumen.tabIndex = -1;
        resumen.hidden = true;
        formulario.append(resumen);

        if (avisarFueraDeTurno(paquete, seccion)) {
            formulario.append(el('p', CLASE_AVISO, t('campo_fuera_de_turno')));
        }
        formulario.append(...camposDe(seccion, formulario));

        const botones = el('div', 'grid grid-cols-2 gap-3 pt-2');
        const cancelar = el('button', 'inline-flex min-h-12 items-center justify-center rounded-ficha bg-ficha px-5 py-3 text-base font-semibold text-tinta ring-1 ring-inset ring-renglon-fuerte transition-[background-color,translate] duration-150 hover:bg-papel-hondo active:translate-y-px', t('campo_cancelar'));
        cancelar.type = 'button';
        cancelar.addEventListener('click', cerrar);
        const guardar = el('button', 'inline-flex min-h-12 items-center justify-center rounded-ficha bg-marca-700 px-5 py-3 text-base font-semibold text-white shadow-ficha transition-[background-color,translate] duration-150 hover:bg-marca-800 active:translate-y-px active:bg-marca-900 disabled:cursor-not-allowed disabled:opacity-50', t('campo_guardar'));
        guardar.type = 'submit';
        botones.append(cancelar, guardar);
        formulario.append(botones);

        formulario.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            const datos = Object.fromEntries(new FormData(formulario).entries());
            const errores = validar(seccion, datos, paquete, fechaLocal());
            mostrarErrores(formulario, errores);
            if (Object.keys(errores).length > 0) return;

            // Mientras se guarda: el botón lo dice y no se puede tocar dos veces
            guardar.disabled = true;
            guardar.textContent = t('campo_guardando');
            const guardado = await cola.guardarRegistro(armarRegistro(seccion, datos, paquete, t));
            guardar.disabled = false;
            guardar.textContent = t('campo_guardar');
            if (!guardado) {
                mensaje(t('campo_no_se_pudo_guardar'), 'atencion');
                return;
            }
            cerrar();
            // En el campo es común registrar varias seguidas: «Registrar otra» reabre la misma sección
            mensaje(t(navigator.onLine ? 'campo_guardado_enviando' : 'cola_guardado'), 'ok', {
                texto: t('campo_registrar_otra', { seccion: t(`campo_seccion_${seccion}`) }),
                alTocar: () => abrir(seccion),
            });
            if (navigator.onLine) cola.enviar({ avisar: true });
        });

        contenedor.replaceChildren(formulario);
        marcarSeccion(seccion);
        desplazar(formulario, 'start');
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
            await cola.recordarHora(respuesta);
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
