/*
 * Paquete de trabajo del modo de campo (GET /campo/paquete): lo mínimo para registrar sin conexión en «Registro
 * rápido» (/campo), solo de la finca y del rol de la persona.
 * - Se descarga solo, en segundo plano, en cada pantalla con sesión y señal si no hay paquete o tiene más de
 *   data-paquete-minutos del <body>, y al volver la conexión. Se guarda en IndexedDB por finca|persona.
 * - Recuerda la sesión activa (para /campo) y borra los paquetes de otras personas del mismo teléfono.
 * - Al cerrar sesión, borrar() quita el paquete y la sesión activa.
 */
import { disponible, operacion, PAQUETES, SESION } from './almacen';
import { clavesAjenas, debeRenovar, paqueteValido } from './paquete-logica';

/** Paquete guardado de una finca|persona, o null. */
export const leerPaquete = async (clave) => {
    if (!clave || !disponible()) return null;
    try {
        return (await operacion(PAQUETES, 'readonly', (almacen) => almacen.get(clave))) ?? null;
    } catch {
        return null;
    }
};

export const iniciarPaquete = () => {
    const clave = document.body.dataset.colaClave;
    const minutos = Number(document.body.dataset.paqueteMinutos) || 15;
    if (!clave || !disponible()) return { actualizar: async () => {}, borrar: async () => {} };

    let descargando = false;

    const prepararTelefono = async () => {
        try {
            await operacion(SESION, 'readwrite', (almacen) => almacen.put({ id: 'activa', clave }));
            const claves = (await operacion(PAQUETES, 'readonly', (almacen) => almacen.getAllKeys())) ?? [];
            for (const ajena of clavesAjenas(claves, clave)) {
                await operacion(PAQUETES, 'readwrite', (almacen) => almacen.delete(ajena));
            }
        } catch {
            // Sin IndexedDB utilizable (modo privado, sin espacio): la app sigue funcionando con señal
        }
    };

    /** Descarga el paquete si hace falta. forzar: aunque sea reciente (por ejemplo, desde Registro rápido). */
    const actualizar = async ({ forzar = false } = {}) => {
        if (descargando || !navigator.onLine) return;
        if (!forzar && !debeRenovar(await leerPaquete(clave), clave, minutos)) return;
        descargando = true;
        try {
            const respuesta = await fetch('/campo/paquete', {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!respuesta.ok) return;
            const paquete = await respuesta.json();
            if (!paqueteValido(paquete, clave)) return;
            await operacion(PAQUETES, 'readwrite', (almacen) => almacen.put(paquete));
            // Que la pantalla Registro rápido guardada coincida con la versión de la app de ahora
            navigator.serviceWorker?.controller?.postMessage({ tipo: 'actualizar-campo' });
        } catch {
            // Sin señal a mitad de camino: se intenta en la próxima pantalla o al volver la conexión
        } finally {
            descargando = false;
        }
    };

    const borrar = async () => {
        try {
            await operacion(PAQUETES, 'readwrite', (almacen) => almacen.delete(clave));
            await operacion(SESION, 'readwrite', (almacen) => almacen.delete('activa'));
        } catch {
            // El servidor además envía Clear-Site-Data al cerrar sesión
        }
    };

    // En segundo plano: después de que la pantalla termine de cargar, sin estorbar
    const enSegundoPlano = (tarea) => ('requestIdleCallback' in window ? requestIdleCallback(tarea, { timeout: 5000 }) : setTimeout(tarea, 1500));
    prepararTelefono().then(() => enSegundoPlano(() => actualizar()));
    window.addEventListener('online', () => actualizar());

    return { actualizar, borrar };
};
