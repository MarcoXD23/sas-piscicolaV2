/*
 * Unidades de peso (g, kg, lb) con aritmética ENTERA (BigInt), igual que App\Support\UnidadesPeso en el servidor: los
 * dos convierten y redondean exactamente igual (casos compartidos en tests/fixtures/unidades-peso.json).
 * - Pesos en centésimas de gramo. Cada unidad es una fracción exacta [numerador, denominador] de centésimas de gramo;
 *   los factores los manda el servidor (paquete de trabajo o el modal de Lagos). Sin factores, solo gramos.
 * - Redondeo: mitad hacia arriba. Valor válido: hasta 6 cifras enteras y 3 decimales, con coma o punto.
 * Sin navegador: se prueba con node --test (tests/js/unidades-peso.test.mjs).
 */

export const GRAMOS = 'g';

/** Factores por defecto (los mismos del servidor): con un paquete viejo, solo gramos. */
export const FACTORES_GRAMOS = { g: [100, 1] };

const FORMATO = /^\d{1,6}([.,]\d{1,3})?$/;

/** a ÷ b redondeado a la mitad hacia arriba, con enteros positivos. */
const dividirRedondeando = (a, b) => (2n * a + b) / (2n * b);

export const numeroValido = (valor) => (typeof valor === 'string' || typeof valor === 'number') && FORMATO.test(String(valor));

/** Valor escrito en la unidad → centésimas de gramo (Number entero), o null si no es válido o la unidad no existe. */
export const aCentesimas = (valor, unidad, factores = FACTORES_GRAMOS) => {
    const factor = factores?.[unidad];
    if (!numeroValido(valor) || !factor) return null;
    const [entero, decimales = ''] = String(valor).replace(',', '.').split('.');
    const mantisa = BigInt(entero + decimales);
    return Number(dividirRedondeando(mantisa * BigInt(factor[0]), (10n ** BigInt(decimales.length)) * BigInt(factor[1])));
};

/** Centésimas de gramo → milésimas de la unidad (para mostrar). */
export const aMilesimas = (centesimas, unidad, factores = FACTORES_GRAMOS) => {
    const [numerador, denominador] = factores[unidad];
    return Number(dividirRedondeando(BigInt(centesimas) * BigInt(denominador) * 1000n, BigInt(numerador)));
};

/** Promedio por pez en centésimas de gramo. */
export const promedio = (centesimas, peces) => Number(dividirRedondeando(BigInt(centesimas), BigInt(peces)));

/** Gramos (con hasta 2 decimales, como los guarda el servidor) → centésimas. */
export const centesimasDeGramos = (gramos) => Math.round(Number(gramos) * 100);

/** Mayor valor de la unidad (con 3 decimales) que no pasa de esas centésimas de gramo. */
export const maximoEnUnidad = (centesimas, unidad, factores = FACTORES_GRAMOS) => {
    const [numerador, denominador] = factores[unidad];
    return Number((BigInt(centesimas) * BigInt(denominador) * 1000n) / BigInt(numerador)) / 1000;
};

/** Texto «62,53 g» o, en otra unidad, «62,53 g (0,138 lb)». idioma: el de la página. */
export const texto = (centesimas, unidad, factores = FACTORES_GRAMOS, idioma = 'es') => {
    const gramos = `${(centesimas / 100).toLocaleString(idioma, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} g`;
    if (unidad === GRAMOS || !factores[unidad]) return gramos;
    const enUnidad = (aMilesimas(centesimas, unidad, factores) / 1000).toLocaleString(idioma, { minimumFractionDigits: 3, maximumFractionDigits: 3 });
    return `${gramos} (${enUnidad} ${unidad})`;
};

/** Unidad para mostrar al abrir: la recordada (si existe en los factores), la de la finca o gramos. */
export const unidadInicial = (recordada, porDefecto, factores = FACTORES_GRAMOS) => {
    if (recordada && factores[recordada]) return recordada;
    if (porDefecto && factores[porDefecto]) return porDefecto;
    return GRAMOS;
};

/** Clave de localStorage de la última unidad elegida, por finca|persona. */
export const claveRecordada = (clave) => `sas-unidad-muestreo|${clave ?? ''}`;

export const leerRecordada = (clave) => {
    try {
        return window.localStorage.getItem(claveRecordada(clave));
    } catch {
        return null;
    }
};

export const recordar = (clave, unidad) => {
    try {
        window.localStorage.setItem(claveRecordada(clave), unidad);
    } catch {
        // Sin almacenamiento (modo privado): la próxima vez sale la de la finca
    }
};
