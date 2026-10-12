/*
 * Reglas de la pantalla Registro rápido (/campo), sin navegador (se prueban con node --test: tests/js/campo.test.mjs).
 * Validan en el teléfono con las mismas reglas del servidor y arman el registro para la cola. El servidor vuelve a
 * revisar todo al llegar (turno, bodega, peces vivos): esto solo evita enviar algo que seguro se rechaza.
 * Los errores son [clave de texto, datos]: la pantalla los traduce con t() (lang/es/js.php).
 */

export const SECCIONES = ['racion', 'mortalidad', 'muestreo', 'aviso_pesca', 'ronda', 'medicion'];

/** Número escrito en el teléfono (acepta coma decimal); NaN si no es un número. */
export const numero = (valor) => {
    const texto = String(valor ?? '').trim().replace(',', '.');
    return texto === '' || !/^-?\d+(\.\d+)?$/.test(texto) ? NaN : Number(texto);
};

const texto = (valor) => String(valor ?? '').trim();

/** Peso promedio del muestreo en gramos, con 2 decimales como lo calcula el servidor; null si falta un dato. */
export const promedioMuestreo = (peces, pesoTotal) => {
    const n = numero(peces);
    const gramos = numero(pesoTotal);
    if (!Number.isInteger(n) || n < 1 || !Number.isFinite(gramos) || gramos <= 0) return null;
    return Math.round((gramos / n) * 100) / 100;
};

/** Antigüedad del paquete: minutos, horas y si ya es viejo (más de «horasViejo»). */
export const antiguedad = (generadoAt, horasViejo = 12, ahora = new Date()) => {
    const minutos = Math.max(0, Math.floor((ahora.getTime() - new Date(generadoAt).getTime()) / 60_000));
    return { minutos, horas: Math.floor(minutos / 60), vieja: minutos >= horasViejo * 60 };
};

/** Clave de texto para «hace …» según la antigüedad. */
export const textoHace = ({ minutos, horas }) => {
    if (minutos < 1) return ['campo_hace_instante', {}];
    if (minutos < 60) return ['campo_hace_minutos', { n: minutos }];
    if (horas < 48) return ['campo_hace_horas', { n: horas }];
    return ['campo_hace_dias', { n: Math.floor(horas / 24) }];
};

/** Rol cuyo turno exige cada sección (solo para el aviso: el servidor decide). */
export const rolDeSeccion = (seccion) => (['ronda', 'medicion'].includes(seccion) ? 'celador' : 'trabajador');

/** ¿Está en turno ahora según los turnos guardados en el paquete? */
export const enTurno = (turnos, rol, ahora = new Date()) => (turnos ?? []).some((turno) => turno.rol === rol
    && new Date(turno.inicia_at).getTime() <= ahora.getTime() && ahora.getTime() <= new Date(turno.termina_at).getTime());

/** ¿Mostrar el aviso «no estás en turno»? Solo si la sección lo exige a esta persona y no hay turno ahora. */
export const avisarFueraDeTurno = (paquete, seccion, ahora = new Date()) => Boolean(paquete?.exige_turno?.[seccion])
    && !enTurno(paquete.turnos, rolDeSeccion(seccion), ahora);

const lagoDe = (paquete, id) => (paquete.lagos ?? []).find((lago) => String(lago.id) === String(id));
const siembraDe = (lago, id) => (lago?.siembras ?? []).find((siembra) => String(siembra.id) === String(id));

/**
 * Errores de una sección ({ campo: [clave, datos] }); vacío si se puede guardar.
 * hoy: fecha local del teléfono (YYYY-MM-DD).
 */
export const validar = (seccion, datos, paquete, hoy) => {
    const errores = {};
    const lago = lagoDe(paquete, datos.lago_id);
    const fecha = (campo, desde = null) => {
        const valor = texto(datos[campo]);
        if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) errores[campo] = ['campo_error_fecha', {}];
        else if (valor > hoy) errores[campo] = ['campo_error_fecha_futura', {}];
        else if (desde && valor < desde) errores[campo] = ['campo_error_fecha_siembra', { fecha: desde }];
    };

    if (seccion === 'racion') {
        if (!lago?.url_racion) errores.lago_id = ['campo_error_lago', {}];
        const concentrado = (paquete.concentrados ?? []).find((c) => c.tipo === texto(datos.tipo_concentrado));
        const kg = numero(datos.cantidad_kg);
        if (!concentrado) errores.tipo_concentrado = ['campo_error_concentrado', {}];
        if (!Number.isFinite(kg) || kg < 0.1 || kg > 100000) errores.cantidad_kg = ['campo_error_kg', {}];
        else if (concentrado && kg > concentrado.kg) errores.cantidad_kg = ['campo_error_kg_bodega', { kg: concentrado.kg }];
        fecha('fecha');
    }

    if (seccion === 'mortalidad') {
        const siembra = siembraDe(lago, datos.siembra_id);
        if (!lago) errores.lago_id = ['campo_error_lago', {}];
        if (!siembra?.url_mortalidad) errores.siembra_id = ['campo_error_siembra', {}];
        const cantidad = numero(datos.cantidad);
        if (!Number.isInteger(cantidad) || cantidad < 1) errores.cantidad = ['campo_error_cantidad', {}];
        else if (siembra && cantidad > siembra.peces_vivos) errores.cantidad = ['campo_error_mas_que_vivos', { n: siembra.peces_vivos }];
        fecha('fecha', siembra?.fecha ?? null);
    }

    if (seccion === 'muestreo') {
        // Mismas reglas que MuestreoController (límites en el paquete: PaqueteTrabajo::RANGOS_MUESTREO)
        const siembra = siembraDe(lago, datos.siembra_id);
        const rangos = paquete.muestreo ?? {};
        if (!lago) errores.lago_id = ['campo_error_lago', {}];
        if (!siembra?.url_muestreo) errores.siembra_id = ['campo_error_siembra', {}];
        const peces = numero(datos.peces_pesados);
        const [minPeces, maxPeces] = [rangos.peces_pesados?.min ?? 1, rangos.peces_pesados?.max ?? 100000];
        if (!Number.isInteger(peces) || peces < minPeces || peces > maxPeces) errores.peces_pesados = ['campo_error_entero_rango', { min: minPeces, max: maxPeces }];
        const gramos = numero(datos.peso_total_g);
        const [minPeso, maxPeso] = [rangos.peso_total_g?.min ?? 0.1, rangos.peso_total_g?.max ?? 999999];
        if (!Number.isFinite(gramos) || gramos < minPeso || gramos > maxPeso) errores.peso_total_g = ['campo_error_rango', { min: minPeso, max: maxPeso }];
        fecha('fecha', siembra?.fecha ?? null);
    }

    if (seccion === 'aviso_pesca') {
        if (!lago?.url_aviso_pesca) errores.lago_id = ['campo_error_lago', {}];
        else if (lago.aviso_pendiente) errores.lago_id = ['campo_error_aviso_pendiente', {}];
        if (!siembraDe(lago, datos.siembra_id)) errores.siembra_id = ['campo_error_siembra', {}];
        if (texto(datos.nota).length > 500) errores.nota = ['campo_error_largo', { n: 500 }];
    }

    if (seccion === 'ronda') {
        const tipo = texto(datos.tipo);
        if (!(paquete.tipos_ronda ?? []).some((opcion) => opcion.codigo === tipo)) errores.tipo = ['campo_error_tipo_ronda', {}];
        if (texto(datos.lago_id) !== '' && !lago) errores.lago_id = ['campo_error_lago', {}];
        const descripcion = texto(datos.descripcion);
        if (tipo !== paquete.sin_novedad && tipo !== '' && descripcion === '') errores.descripcion = ['campo_error_descripcion', {}];
        if (descripcion.length > 1000) errores.descripcion = ['campo_error_largo', { n: 1000 }];
    }

    if (seccion === 'medicion') {
        if (!lago?.url_medicion) errores.lago_id = ['campo_error_lago', {}];
        let alguno = false;
        for (const parametro of paquete.medicion ?? []) {
            if (texto(datos[parametro.campo]) === '') continue;
            alguno = true;
            const valor = numero(datos[parametro.campo]);
            const entero = parametro.paso >= 1;
            if (!Number.isFinite(valor) || valor < parametro.min || valor > parametro.max || (entero && !Number.isInteger(valor))) {
                errores[parametro.campo] = ['campo_error_rango', { min: parametro.min, max: parametro.max }];
            }
        }
        if (!alguno) errores.medicion = ['campo_error_un_parametro', {}];
    }

    return errores;
};

/**
 * Registro para la cola: { url, campos, detalle, resumen }. Llamar solo si validar() no dio errores.
 * t: traductor de textos (lang/es/js.php).
 */
export const armarRegistro = (seccion, datos, paquete, t) => {
    const lago = lagoDe(paquete, datos.lago_id);
    const decimal = (valor) => String(numero(valor));

    if (seccion === 'racion') {
        return {
            url: lago.url_racion,
            campos: [['fecha', texto(datos.fecha)], ['tipo_concentrado', texto(datos.tipo_concentrado)], ['cantidad_kg', decimal(datos.cantidad_kg)]],
            detalle: [[t('campo_lago'), lago.nombre], [t('campo_fecha'), texto(datos.fecha)], [t('campo_concentrado'), texto(datos.tipo_concentrado)], [t('campo_kg'), decimal(datos.cantidad_kg)]],
            resumen: t('campo_resumen_racion', { lago: lago.nombre }),
        };
    }
    if (seccion === 'mortalidad') {
        const siembra = siembraDe(lago, datos.siembra_id);
        return {
            url: siembra.url_mortalidad,
            campos: [['fecha', texto(datos.fecha)], ['cantidad', String(numero(datos.cantidad))]],
            detalle: [[t('campo_lago'), lago.nombre], [t('campo_lote'), siembra.especie], [t('campo_fecha'), texto(datos.fecha)], [t('campo_peces_muertos'), String(numero(datos.cantidad))]],
            resumen: t('campo_resumen_mortalidad', { especie: siembra.especie, lago: lago.nombre }),
        };
    }
    if (seccion === 'muestreo') {
        const siembra = siembraDe(lago, datos.siembra_id);
        const peces = String(numero(datos.peces_pesados));
        const gramos = decimal(datos.peso_total_g);
        return {
            url: siembra.url_muestreo,
            campos: [['fecha', texto(datos.fecha)], ['peces_pesados', peces], ['peso_total_g', gramos]],
            detalle: [[t('campo_lago'), lago.nombre], [t('campo_lote'), siembra.especie], [t('campo_fecha'), texto(datos.fecha)],
                [t('campo_peces_pesados'), peces], [t('campo_peso_total_g'), gramos], [t('campo_peso_promedio_g'), String(promedioMuestreo(peces, gramos))]],
            resumen: t('campo_resumen_muestreo', { especie: siembra.especie, lago: lago.nombre }),
        };
    }
    if (seccion === 'aviso_pesca') {
        const siembra = siembraDe(lago, datos.siembra_id);
        return {
            url: lago.url_aviso_pesca,
            campos: [['siembra_id', String(siembra.id)], ['nota', texto(datos.nota)]],
            detalle: [[t('campo_lago'), lago.nombre], [t('campo_lote'), siembra.especie], [t('campo_nota'), texto(datos.nota)]].filter(([, valor]) => valor !== ''),
            resumen: t('campo_resumen_aviso_pesca', { lago: lago.nombre }),
        };
    }
    if (seccion === 'ronda') {
        const tipo = (paquete.tipos_ronda ?? []).find((opcion) => opcion.codigo === texto(datos.tipo));
        return {
            url: paquete.urls.ronda,
            campos: [['tipo', tipo.codigo], ['lago_id', lago ? String(lago.id) : ''], ['descripcion', texto(datos.descripcion)]],
            detalle: [[t('campo_tipo_ronda'), tipo.texto], [t('campo_lago'), lago?.nombre ?? t('campo_toda_la_finca')], [t('campo_descripcion'), texto(datos.descripcion)]].filter(([, valor]) => valor !== ''),
            resumen: t('campo_resumen_ronda'),
        };
    }
    // Medición: solo los parámetros escritos (la hora la pone el servidor con la del registro)
    const escritos = (paquete.medicion ?? []).filter((parametro) => texto(datos[parametro.campo]) !== '');
    return {
        url: lago.url_medicion,
        campos: escritos.map((parametro) => [parametro.campo, decimal(datos[parametro.campo])]),
        detalle: [[t('campo_lago'), lago.nombre], ...escritos.map((parametro) => [parametro.etiqueta, decimal(datos[parametro.campo])])],
        resumen: t('campo_resumen_medicion', { lago: lago.nombre }),
    };
};

/**
 * Resumen de errores para mostrar arriba del formulario: [campo, [clave, datos]] en el orden de los campos en
 * pantalla (orden: nombres de campo tal como aparecen). Los errores de campos que no están en «orden» van al final.
 */
export const resumenErrores = (errores, orden) => {
    const posicion = (campo) => {
        const indice = orden.indexOf(campo);
        return indice === -1 ? orden.length : indice;
    };
    return Object.entries(errores).sort(([a], [b]) => posicion(a) - posicion(b));
};
