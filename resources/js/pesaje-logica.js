/*
 * Cuentas del pesaje de la cosecha, como en el cuaderno (sin DOM: se prueban con node --test, tests/js/pesaje.test.mjs).
 * Cada pesada: descuento = cantidad × peso de UNA vacía (se multiplica); peso entero = peso pesado − descuento.
 * Todo en centésimas de kg (enteros), igual que el servidor (CalculoPesaje), que vuelve a hacer las cuentas al guardar.
 * La pantalla (resources/js/pesaje.js) lee los campos, llama a estas funciones y escribe los resultados.
 */

export const TIPOS = ['canasta', 'canastilla'];

/** Número escrito con coma o punto decimal, o null. */
export const numero = (valor) => {
    const n = Number.parseFloat(String(valor ?? '').replace(',', '.'));
    return Number.isFinite(n) ? n : null;
};

export const centesimas = (valor) => {
    const n = numero(valor);
    return n === null ? null : Math.round(n * 100);
};

/** Cantidad de vacías de una pesada: entero de 1 en adelante, o null. */
export const cantidad = (fila) => {
    const n = numero(fila.cantidad);
    return n !== null && Number.isInteger(n) && n >= 1 ? n : null;
};

export const descuentoC = (fila, vacias) => {
    const cuantas = cantidad(fila);
    const vacia = centesimas(vacias[fila.tipo]);
    return cuantas === null || vacia === null ? null : cuantas * vacia;
};

export const enteroC = (fila, vacias) => {
    const pesado = centesimas(fila.peso);
    const descuento = descuentoC(fila, vacias);
    return pesado === null || descuento === null ? null : pesado - descuento;
};

/** Kg con los separadores de la finca y sin ceros de más (2; 35,5; 1.234,25). Sin dato: «—». */
export const kg = (c, separadores = { decimal: ',', miles: '.' }) => {
    if (c === null || c === undefined) return '—';
    const [entero, decimales] = (Math.abs(c) / 100).toFixed(2).split('.');
    const miles = entero.replace(/\B(?=(\d{3})+(?!\d))/g, separadores.miles);
    const resto = decimales.replace(/0+$/, '');
    return (c < 0 ? '-' : '') + miles + (resto ? separadores.decimal + resto : '');
};

/** Totales de las pesadas completas (las incompletas no suman). */
export const totales = (filas, vacias) => {
    const completas = filas.filter((fila) => enteroC(fila, vacias) !== null);
    const suma = (calculo) => completas.reduce((total, fila) => total + calculo(fila), 0);
    const porTipo = { canasta: 0, canastilla: 0 };
    const grupos = new Map();
    completas.forEach((fila) => {
        porTipo[fila.tipo] += cantidad(fila);
        const vacia = centesimas(vacias[fila.tipo]);
        const clave = `${fila.tipo}|${vacia}`;
        const grupo = grupos.get(clave) ?? { tipo: fila.tipo, vacia, cantidad: 0 };
        grupo.cantidad += cantidad(fila);
        grupos.set(clave, grupo);
    });

    return {
        pesadas: completas.length,
        porTipo,
        cantidad: porTipo.canasta + porTipo.canastilla,
        grupos: [...grupos.values()],
        pesado: suma((fila) => centesimas(fila.peso)),
        descuento: suma((fila) => descuentoC(fila, vacias)),
        entero: suma((fila) => enteroC(fila, vacias)),
    };
};

/** La línea del cuaderno: «3 canastas de 2 kg + 1 canastilla de 1,5 kg = 7,5 kg de descuento…». */
export const linea = (suma, t, separadores) => {
    if (suma.pesadas === 0) return '';
    const grupos = suma.grupos
        .map((g) => t(`pesaje_grupo_${g.tipo}_${g.cantidad === 1 ? 'uno' : 'varios'}`, { cantidad: g.cantidad, kg: kg(g.vacia, separadores) }))
        .join(' + ');
    return t('pesaje_linea', { grupos, descuento: kg(suma.descuento, separadores), pesado: kg(suma.pesado, separadores), entero: kg(suma.entero, separadores) });
};

/** Meta opcional en centésimas (mayor que 0), o null: se compara con el total ENTERO y solo ayuda en pantalla. */
export const metaC = (meta) => {
    const c = centesimas(meta);
    return c !== null && c > 0 ? c : null;
};

/** Avance hacia la meta, de 0 a 100. */
export const avance = (entero, meta) => (meta === null ? 0 : Math.min(100, Math.max(0, (entero / meta) * 100)));

export const metaTexto = (suma, meta, t, separadores) => {
    if (meta === null) return [];
    const { entero, pesadas } = suma;
    const k = (c) => kg(c, separadores);
    if (entero >= meta) {
        return [entero === meta ? t('meta_llegaste', { meta: k(meta) }) : t('meta_sobran', { meta: k(meta), kg: k(entero - meta) })];
    }
    const faltan = meta - entero;
    const textos = [t('meta_llevas', { entero: k(entero), meta: k(meta) }), t('meta_faltan', { kg: k(faltan) })];
    if (pesadas >= 2 && entero > 0) {
        const promedio = Math.round(entero / pesadas);
        const restantes = Math.ceil(faltan / (entero / pesadas));
        textos.push(t(restantes === 1 ? 'meta_promedio_una' : 'meta_promedio_varias', { promedio: k(promedio), pesadas: restantes }));
    }
    return textos;
};

/**
 * Antes de enviar: con el modo de pesadas no se envía con una vacía sin peso, una fila incompleta o una pesada que no
 * pasa del descuento. Devuelve { aviso, indice } (aviso vacío: se puede enviar; indice: la fila que hay que corregir).
 * El servidor valida todo de nuevo.
 */
export const validar = (modo, filas, vacias, t) => {
    if (modo !== 'pesadas') return { aviso: '', indice: null };
    if (!(centesimas(vacias.canasta) > 0) || !(centesimas(vacias.canastilla) > 0)) return { aviso: t('pesaje_vacias'), indice: null };

    const incompleta = filas.findIndex((fila) => enteroC(fila, vacias) === null);
    if (incompleta >= 0) return { aviso: t('pesaje_incompleta', { numero: incompleta + 1 }), indice: incompleta };

    const bajo = filas.findIndex((fila) => enteroC(fila, vacias) <= 0);
    if (bajo >= 0) return { aviso: t('pesaje_bajo_descuento', { numero: bajo + 1 }), indice: bajo };

    return { aviso: '', indice: null };
};

/**
 * Qué zona se ve y qué se envía en cada modo: el total entero (campo kg, obligatorio) o la tabla de pesadas. Lo de la
 * zona oculta queda deshabilitado y no se envía, para que el servidor reciba solo un modo.
 */
export const estadoModo = (modo) => {
    const total = modo === 'total';
    return { total: { visible: total, habilitado: total, requerido: total }, pesadas: { visible: !total, habilitado: !total } };
};

/** Fila nueva: del mismo tipo que la anterior (o canasta), vacía. */
export const filaNueva = (anterior) => ({ tipo: anterior?.tipo ?? 'canasta', cantidad: '', peso: '' });
