/*
 * Base de IndexedDB del teléfono para el modo sin conexión (una sola, compartida):
 * - registros: la cola de registros hechos sin conexión (resources/js/sin-conexion.js), por uuid, con índice «clave».
 * - paquetes: el paquete de trabajo de cada finca|persona (resources/js/paquete.js), por «clave».
 * - sesion: la sesión activa ({ id: 'activa', clave }), para que /campo (sin sesión en la página) sepa de quién es.
 * La versión 2 agrega «paquetes» y «sesion» sin tocar los registros que ya estaban en la cola.
 */
export const BASE = 'sas-piscicola-sin-conexion';
export const REGISTROS = 'registros';
export const PAQUETES = 'paquetes';
export const SESION = 'sesion';

export const disponible = () => typeof indexedDB !== 'undefined';

const abrirBase = () => new Promise((resolver, rechazar) => {
    const peticion = indexedDB.open(BASE, 2);
    peticion.onupgradeneeded = () => {
        const base = peticion.result;
        if (!base.objectStoreNames.contains(REGISTROS)) {
            base.createObjectStore(REGISTROS, { keyPath: 'uuid' }).createIndex('clave', 'clave');
        }
        if (!base.objectStoreNames.contains(PAQUETES)) base.createObjectStore(PAQUETES, { keyPath: 'clave' });
        if (!base.objectStoreNames.contains(SESION)) base.createObjectStore(SESION, { keyPath: 'id' });
    };
    peticion.onsuccess = () => resolver(peticion.result);
    peticion.onerror = () => rechazar(peticion.error);
    // Otra pestaña con la versión vieja abierta: se espera a que la cierre (no se pierde nada)
    peticion.onblocked = () => {};
});

/** Corre una acción sobre un almacén y devuelve el resultado de su petición (si la hay). */
export const operacion = async (almacen, modo, accion) => {
    const base = await abrirBase();
    return new Promise((resolver, rechazar) => {
        const transaccion = base.transaction(almacen, modo);
        const resultado = accion(transaccion.objectStore(almacen));
        transaccion.oncomplete = () => { base.close(); resolver(resultado?.result); };
        transaccion.onerror = () => { base.close(); rechazar(transaccion.error); };
        transaccion.onabort = () => { base.close(); rechazar(transaccion.error); };
    });
};

/** Sesión activa en este teléfono (finca|persona) o null. */
export const leerSesionActiva = async () => {
    if (!disponible()) return null;
    try {
        return (await operacion(SESION, 'readonly', (almacen) => almacen.get('activa')))?.clave ?? null;
    } catch {
        return null;
    }
};
