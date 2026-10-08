/*
 * Reglas de la cola de registros sin conexión, sin navegador (se prueban con node --test: tests/js/sin-conexion.test.mjs).
 * El servidor responde (middleware RegistroSinConexion): 201 guardado, 200 duplicado, 403/422 rechazado con su motivo.
 */

/** Qué hacer con un registro según la respuesta del servidor. */
export const clasificar = (estado) => {
    if (estado === 200 || estado === 201) return 'listo';          // guardado o ya recibido: sale de la cola
    if (estado === 401 || estado === 419) return 'sesion';         // sesión vencida: se detiene y se pide entrar de nuevo
    if ([403, 404, 409, 422].includes(estado)) return 'rechazado'; // las reglas de la finca no lo aceptan: no se reintenta
    return 'reintentar';                                           // 5xx, 429, red caída o respuesta rara: se intenta después
};

/** Motivo legible del rechazo: el primer error de validación o el mensaje del servidor. */
export const motivoRechazo = (datos, porDefecto) => {
    const errores = datos && typeof datos.errors === 'object' ? Object.values(datos.errors).flat() : [];
    const motivo = errores.find((texto) => typeof texto === 'string' && texto.trim() !== '') ?? datos?.message;

    return typeof motivo === 'string' && motivo.trim() !== '' ? motivo.trim() : porDefecto;
};

/** Horas que lleva guardado un registro. */
export const horasGuardado = (registradoEn, ahora = new Date()) => (ahora.getTime() - new Date(registradoEn).getTime()) / 3_600_000;

/** Le faltan menos de 8 horas para que el servidor ya no lo acepte (SIN_CONEXION_HORAS). */
export const porVencer = (registradoEn, horas, ahora = new Date()) => horasGuardado(registradoEn, ahora) >= Math.max(horas - 8, horas / 2);

/** Campos del formulario que viajan con el registro: sin el token (se pone uno vigente al enviar) ni la marca de la ventana. */
export const camposParaGuardar = (entradas) => entradas
    .filter(([nombre, valor]) => !['_token', '_modal'].includes(nombre) && typeof valor === 'string');

/** Fecha local del teléfono como YYYY-MM-DD (para los campos de fecha de una pantalla guardada de otro día). */
export const fechaLocal = (fecha = new Date()) => [
    fecha.getFullYear(),
    String(fecha.getMonth() + 1).padStart(2, '0'),
    String(fecha.getDate()).padStart(2, '0'),
].join('-');
