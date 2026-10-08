/*
 * Reglas del paquete de trabajo, sin navegador (se prueban con node --test: tests/js/paquete.test.mjs).
 */

/** ¿Hay que descargarlo otra vez? Sin paquete, de otra persona, sin fecha o con más de «minutos». */
export const debeRenovar = (paquete, clave, minutos, ahora = new Date()) => {
    if (!paquete || paquete.clave !== clave) return true;
    const generado = new Date(paquete.generado_at).getTime();
    if (!Number.isFinite(generado)) return true;
    return ahora.getTime() - generado >= minutos * 60_000;
};

/** Solo se guarda un paquete de la persona y la finca de la sesión, con lo mínimo para armar la pantalla. */
export const paqueteValido = (paquete, clave) => Boolean(paquete)
    && typeof paquete === 'object'
    && paquete.clave === clave
    && Array.isArray(paquete.secciones)
    && Array.isArray(paquete.lagos)
    && Number.isFinite(new Date(paquete.generado_at).getTime());

/** Claves de paquetes de OTRAS personas o fincas guardados en este teléfono (se borran). */
export const clavesAjenas = (claves, clave) => claves.filter((otra) => otra !== clave);

/** Respuesta al pedir el token: 200 con token = listo; 401/419 = sesión vencida; lo demás, intentar después. */
export const estadoToken = (estado, cuerpo) => {
    if (estado === 200 && typeof cuerpo?.token === 'string' && cuerpo.token !== '') return 'listo';
    if (estado === 401 || estado === 419) return 'sesion';
    return 'reintentar';
};
